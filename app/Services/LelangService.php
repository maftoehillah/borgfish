<?php

namespace App\Services;

use App\Models\AuctionRanking;
use App\Models\AuctionStateLog;
use App\Models\Bid;
use App\Models\Ikan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LelangService
{
    public const STATE_AKTIF = 'AKTIF';

    public const STATE_SELESAI = 'SELESAI';

    public const STATE_MENUNGGU_PEMBAYARAN = 'MENUNGGU_PEMBAYARAN';

    public const STATE_DIBAYAR = 'DIBAYAR';

    public const STATE_KADALUARSA = 'KADALUARSA';

    public const STATE_GAGAL_TOTAL = 'GAGAL_TOTAL';

    public function __construct(
        private readonly OrderCodeService $codes,
        private readonly ViolationService $violations,
    ) {
    }

    public function aktifkanYangBelumMulai(): void
    {
        Ikan::query()
            ->where('status', 'menunggu')
            ->where('waktu_mulai', '<=', now())
            ->where('waktu_selesai', '>', now())
            ->each(function (Ikan $ikan): void {
                $ikan->status = 'aktif';
                $ikan->auction_state = self::STATE_AKTIF;
                $ikan->bumpStateVersion();
                $ikan->save();
            });
    }

    public function cekDanTutupSemua(): void
    {
        Ikan::query()
            ->where('status', 'aktif')
            ->where('waktu_selesai', '<=', now())
            ->each(fn (Ikan $ikan) => $this->tutupLelang($ikan));

        $this->prosesOtomatisTransaksi();
    }

    public function tutupLelang(Ikan $ikan): void
    {
        DB::transaction(function () use ($ikan): void {
            $lot = Ikan::query()
                ->lockForUpdate()
                ->find($ikan->id);

            if (! $lot) {
                return;
            }

            if (now()->lt($lot->waktu_selesai)) {
                return;
            }

            $existingOrder = Transaksi::query()
                ->where('ikan_id', $lot->id)
                ->lockForUpdate()
                ->first();

            if ($existingOrder && in_array((string) $existingOrder->status, ['menunggu_bayar', 'lunas'], true)) {
                if ((string) $lot->status !== 'terbayar') {
                    $lot->status = $existingOrder->isLunas() ? 'terbayar' : 'selesai';
                    $lot->save();
                }

                return;
            }

            $lot->status = 'selesai';
            $this->freezeRankingSnapshot($lot);

            $winner = AuctionRanking::query()
                ->where('ikan_id', $lot->id)
                ->where('rank', 1)
                ->first();

            if (! $winner || $this->isBidBelowReserve($lot, (float) $winner->bid_amount)) {
                $lot->hard_stop_reason = $winner ? 'reserve_not_met' : 'no_valid_bidder';
                $lot->current_winner_rank = null;
                $this->persistAuctionState(
                    $lot,
                    self::STATE_GAGAL_TOTAL,
                    $winner ? 'reserve_not_met' : 'no_valid_bidder'
                );

                app(NotificationOutboxService::class)->queueForAuctionOutcome(
                    $lot,
                    null,
                    null,
                    null,
                    $lot->hard_stop_reason
                );

                return;
            }

            $order = $this->assignWinner(
                $lot,
                (int) $winner->bidder_id,
                (float) $winner->bid_amount,
                1,
                'auction_closed'
            );

            app(NotificationOutboxService::class)->queueForAuctionOutcome(
                $lot,
                $order,
                (int) $order->pemenang_id,
                1,
                null
            );
        }, 3);

        app(NotificationOutboxService::class)->processPending(100);
    }

    public function selesaikanDenganBuyNow(Ikan $ikan, int $pemenangId, ?string $bidderIp = null, ?string $userAgent = null): ?Transaksi
    {
        $result = DB::transaction(function () use ($ikan, $pemenangId, $bidderIp, $userAgent): ?Transaksi {
            $lot = Ikan::query()
                ->lockForUpdate()
                ->find($ikan->id);

            if (! $lot || ! $lot->canBuyNow()) {
                return null;
            }

            $existingOrder = Transaksi::query()
                ->where('ikan_id', $lot->id)
                ->lockForUpdate()
                ->first();

            if ($existingOrder && in_array((string) $existingOrder->status, ['menunggu_bayar', 'lunas'], true)) {
                return $existingOrder;
            }

            $price = (float) ($lot->buyNowTarget() ?? 0);
            if ($price <= 0) {
                return null;
            }

            $normalizedIp = Bid::normalizeIp($bidderIp);
            [$isSuspicious, $suspicionReason] = $this->detectBidAnomaly($lot, $pemenangId, $price, $normalizedIp);

            Bid::query()->create([
                'ikan_id' => $lot->id,
                'user_id' => $pemenangId,
                'jumlah_bid' => $price,
                'bidder_ip' => $normalizedIp,
                'bidder_user_agent' => $userAgent ? substr($userAgent, 0, 255) : null,
                'is_suspicious' => $isSuspicious,
                'suspicion_reason' => $suspicionReason,
            ]);

            $lot->harga_tertinggi = $price;
            $lot->status = 'selesai';
            $lot->waktu_selesai = now();
            $lot->last_bidder_id = $pemenangId;
            $lot->last_bid_at = now();

            $this->freezeRankingSnapshot($lot);

            $order = $this->assignWinner($lot, $pemenangId, $price, 1, 'buy_now_closed');

            app(NotificationOutboxService::class)->queueForAuctionOutcome(
                $lot,
                $order,
                $pemenangId,
                1,
                null
            );

            return $order;
        }, 3);

        if ($result) {
            app(NotificationOutboxService::class)->processPending(100);
        }

        return $result;
    }

    public function prosesOtomatisTransaksi(): void
    {
        Transaksi::query()
            ->where('status', 'menunggu_bayar')
            ->whereNotNull('bayar_sebelum')
            ->where('bayar_sebelum', '<', now())
            ->each(fn (Transaksi $transaksi) => $this->handleExpiredTransaction($transaksi, 'scheduler'));

        Transaksi::query()
            ->where('status', 'lunas')
            ->where('pickup_status', 'completed')
            ->where('fulfillment_state', '!=', TransaksiFulfillmentService::STATE_SELESAI)
            ->each(function (Transaksi $transaksi): void {
                app(TransaksiFulfillmentService::class)->markCompletedBySystem($transaksi, 'BUYER_CONFIRMED_COMPLETED');
            });

        app(NotificationOutboxService::class)->processPending(100);
    }

    public function handleExpiredTransaction(Transaksi $transaksi, string $source = 'system'): void
    {
        DB::transaction(function () use ($transaksi, $source): void {
            $order = Transaksi::query()
                ->with(['ikan', 'pemenang'])
                ->lockForUpdate()
                ->find($transaksi->id);

            if (! $order || $order->isLunas()) {
                return;
            }

            $isExpired = $order->bayar_sebelum !== null && now()->gt($order->bayar_sebelum);
            if (! $isExpired && $order->status !== 'kadaluarsa') {
                return;
            }

            $order->markPaymentExpired();
            $order->save();

            $latestAttempt = $order->paymentAttempts()
                ->where('status_code', 'pending')
                ->latest('id')
                ->first();

            if ($latestAttempt) {
                $latestAttempt->markExpired($source);
                $latestAttempt->save();
            }

            $lot = Ikan::query()
                ->lockForUpdate()
                ->find($order->ikan_id);

            if ($lot) {
                $lot->status = 'selesai';
                $lot->hard_stop_reason = 'payment_expired';
                $lot->current_winner_rank = $order->winner_rank;
                $this->persistAuctionState($lot, self::STATE_KADALUARSA, 'winner_payment_expired', $source, (int) $order->pemenang_id);
            }

            $winner = $order->pemenang;
            if ($winner instanceof User) {
                $this->violations->recordBuyerNoPayment($winner, $order, $lot);
            }

            app(NotificationOutboxService::class)->queueForPaymentExpiry(
                $order,
                (int) ($order->pemenang_id ?? 0),
                null,
                (int) ($order->winner_rank ?? 1),
                null,
                $source
            );
        }, 3);

        app(NotificationOutboxService::class)->processPending(100);
    }

    public function markTransactionAsPaid(Transaksi $transaksi, ?string $providerRef = null): void
    {
        DB::transaction(function () use ($transaksi, $providerRef): void {
            $order = Transaksi::query()
                ->lockForUpdate()
                ->find($transaksi->id);

            if (! $order || ! $order->isLunas()) {
                return;
            }

            $lot = Ikan::query()
                ->lockForUpdate()
                ->find($order->ikan_id);

            if (! $lot) {
                return;
            }

            $lot->status = 'terbayar';
            $lot->hard_stop_reason = null;
            $this->persistAuctionState(
                $lot,
                self::STATE_DIBAYAR,
                'winner_payment_settled',
                'webhook',
                (int) $order->pemenang_id,
                ['provider_ref' => $providerRef]
            );
        }, 3);
    }

    private function assignWinner(Ikan $lot, int $winnerId, float $amount, int $rank, string $eventName): Transaksi
    {
        $order = Transaksi::query()
            ->where('ikan_id', $lot->id)
            ->lockForUpdate()
            ->first() ?? new Transaksi([
                'ikan_id' => $lot->id,
                'order_code' => $this->codes->nextOrderCode(),
            ]);

        $deadline = now()->addMinutes($lot->resolvePaymentDeadlineMinutes());

        $order->pemenang_id = $winnerId;
        $order->harga_final = $amount;
        $order->metode_pembayaran = null;
        $order->assigned_at = now();
        $order->markWaitingPayment($rank, $deadline);
        $order->save();

        $lot->current_winner_rank = $rank;
        $lot->hard_stop_reason = null;
        $this->persistAuctionState(
            $lot,
            self::STATE_MENUNGGU_PEMBAYARAN,
            $eventName,
            'system',
            $winnerId,
            [
                'rank' => $rank,
                'amount' => $amount,
                'payment_deadline_at' => $deadline->toIso8601String(),
            ]
        );

        return $order->fresh(['ikan', 'pemenang']);
    }

    private function freezeRankingSnapshot(Ikan $lot): void
    {
        AuctionRanking::query()
            ->where('ikan_id', $lot->id)
            ->delete();

        $sortedBids = Bid::query()
            ->where('ikan_id', $lot->id)
            ->orderByDesc('jumlah_bid')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $rank = 1;
        $seenBidderIds = [];

        foreach ($sortedBids as $bid) {
            $bidderId = (int) $bid->user_id;

            if (isset($seenBidderIds[$bidderId])) {
                continue;
            }

            $seenBidderIds[$bidderId] = true;

            AuctionRanking::query()->create([
                'ikan_id' => $lot->id,
                'rank' => $rank,
                'bidder_id' => $bidderId,
                'bid_id' => $bid->id,
                'bid_amount' => $bid->jumlah_bid,
                'bid_created_at' => $bid->created_at,
                'snapshot_hash' => sha1(implode('|', [
                    $lot->id,
                    $rank,
                    $bidderId,
                    $bid->id,
                    (float) $bid->jumlah_bid,
                    $bid->created_at?->toIso8601String() ?? '',
                ])),
            ]);

            $rank++;
        }

        $lot->ranking_frozen_at = now();
        $lot->save();
    }

    private function persistAuctionState(
        Ikan $lot,
        string $toState,
        string $eventName,
        string $actorType = 'system',
        ?int $actorId = null,
        array $metadata = []
    ): void {
        $fromState = $lot->auction_state;
        $lot->auction_state = $toState;
        $lot->bumpStateVersion();
        $lot->save();

        AuctionStateLog::query()->create([
            'ikan_id' => $lot->id,
            'from_state' => $fromState,
            'to_state' => $toState,
            'event_name' => $eventName,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'metadata' => empty($metadata) ? null : $metadata,
            'created_at' => now(),
        ]);
    }

    private function isBidBelowReserve(Ikan $lot, float $candidateAmount): bool
    {
        if (! $lot->isLelangTurun()) {
            return false;
        }

        if ($lot->reserve_price === null) {
            return false;
        }

        return $candidateAmount < (float) $lot->reserve_price;
    }

    private function detectBidAnomaly(Ikan $lot, int $userId, float $amount, ?string $normalizedIp): array
    {
        $recentBidCount = Bid::query()
            ->where('ikan_id', $lot->id)
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subMinute())
            ->count();

        $ipCandidates = Bid::ipCandidates($normalizedIp);

        $ipDipakaiAkunLainLot = ! empty($ipCandidates)
            && Bid::query()
                ->where('ikan_id', $lot->id)
                ->whereIn('bidder_ip', $ipCandidates)
                ->where('user_id', '!=', $userId)
                ->exists();

        $ipDipakaiAkunLainGlobal = ! empty($ipCandidates)
            && Bid::query()
                ->whereIn('bidder_ip', $ipCandidates)
                ->where('user_id', '!=', $userId)
                ->exists();

        return Bid::deteksiAnomali(
            $amount,
            (float) $lot->harga_tertinggi,
            $recentBidCount,
            $ipDipakaiAkunLainLot,
            $ipDipakaiAkunLainGlobal
        );
    }
}
