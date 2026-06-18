<?php

namespace App\Services;

use App\Models\AuctionRanking;
use App\Models\InAppNotification;
use App\Models\Ikan;
use App\Models\NotificationOutbox;
use App\Models\SellerSettlement;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Support\Str;

class NotificationOutboxService
{
    public function queue(
        int $recipientUserId,
        string $category,
        string $title,
        string $message,
        array $payload = [],
        ?string $idempotencyKey = null,
    ): void {
        $key = $idempotencyKey ?: Str::uuid()->toString();

        NotificationOutbox::query()->firstOrCreate(
            ['idempotency_key' => $key],
            [
                'recipient_user_id' => $recipientUserId,
                'recipient_role' => null,
                'category' => $category,
                'title' => $title,
                'message' => $message,
                'payload' => empty($payload) ? null : $payload,
                'status' => 'pending',
                'attempts' => 0,
                'available_at' => now(),
            ]
        );
    }

    public function queueForFulfillmentTransition(
        Transaksi $transaksi,
        ?string $fromState,
        string $toState,
        string $eventName,
        ?string $reasonCode = null,
        ?string $reasonText = null,
    ): void {
        $transaksi->loadMissing(['ikan.user', 'pemenang']);

        $buyerId = (int) ($transaksi->pemenang_id ?? 0);
        $sellerId = (int) ($transaksi->ikan?->user_id ?? 0);
        $latestDisputeId = $this->resolveLatestDisputeId($transaksi);

        if ($toState === TransaksiFulfillmentService::STATE_DIBAYAR) {
            if ($buyerId > 0) {
                $this->queue(
                    $buyerId,
                    'pembayaran',
                    'Pembayaran berhasil',
                    'Pembayaran Anda telah dikonfirmasi. Transaksi masuk tahap diproses penjual, lalu lanjut isi data penjemput saat lot sudah siap dijemput.',
                    [
                        'transaksi_id' => $transaksi->id,
                        'state' => $toState,
                        'progress_label' => $transaksi->buyerProgressLabel(),
                    ],
                    $this->buildKey($transaksi->id, 'buyer', $buyerId, $eventName, $toState)
                );
            }

            if ($sellerId > 0) {
                $this->queue(
                    $sellerId,
                    'pesanan',
                    'Pesanan baru dibayar',
                    'Ada pesanan baru yang sudah dibayar. Segera lakukan packing agar transaksi bisa lanjut ke tahap siap dijemput.',
                    [
                        'transaksi_id' => $transaksi->id,
                        'state' => $toState,
                        'progress_label' => $transaksi->buyerProgressLabel(),
                    ],
                    $this->buildKey($transaksi->id, 'seller', $sellerId, $eventName, $toState)
                );
            }

            return;
        }

        if ($toState === TransaksiFulfillmentService::STATE_DIKIRIM) {
            if ($buyerId > 0) {
                $this->queue(
                    $buyerId,
                    'penjemputan',
                    'Penjemput datang',
                    'Penjual sudah memvalidasi penjemput di lokasi. Transaksi kini masuk tahap dalam penjemputan, lalu lanjutkan dengan konfirmasi selesai.',
                    [
                        'transaksi_id' => $transaksi->id,
                        'pickup_status' => $transaksi->pickup_status,
                        'state' => $toState,
                        'progress_label' => $transaksi->buyerProgressLabel(),
                    ],
                    $this->buildKey($transaksi->id, 'buyer', $buyerId, $eventName, $toState)
                );
            }

            return;
        }

        if (in_array($toState, [
            TransaksiFulfillmentService::STATE_DISENGKETAKAN,
            TransaksiFulfillmentService::STATE_GAGAL,
        ], true)) {
            if ($toState === TransaksiFulfillmentService::STATE_DISENGKETAKAN) {
                if ($buyerId > 0) {
                    $this->queue(
                        $buyerId,
                        'sengketa',
                        'Komplain diproses',
                        'Komplain Anda telah diterima dan sedang ditinjau admin.',
                        [
                            'transaksi_id' => $transaksi->id,
                            'dispute_id' => $latestDisputeId,
                            'state' => $toState,
                            'reason_code' => $reasonCode,
                        ],
                        $this->buildKey($transaksi->id, 'buyer', $buyerId, $eventName, $toState)
                    );
                }

                if ($sellerId > 0) {
                    $this->queue(
                        $sellerId,
                        'sengketa',
                        'Transaksi masuk sengketa',
                        'Ada transaksi yang perlu respons Anda karena masuk sengketa.',
                        [
                            'transaksi_id' => $transaksi->id,
                            'dispute_id' => $latestDisputeId,
                            'state' => $toState,
                            'reason_code' => $reasonCode,
                        ],
                        $this->buildKey($transaksi->id, 'seller', $sellerId, $eventName, $toState)
                    );
                }
            }

            $message = $toState === TransaksiFulfillmentService::STATE_DISENGKETAKAN
                ? 'Ada transaksi yang masuk sengketa dan perlu penanganan admin.'
                : 'Ada transaksi gagal yang perlu peninjauan admin.';

            $this->queueAdminBroadcast(
                category: 'operasional',
                title: 'Transaksi bermasalah',
                message: $message,
                payload: [
                    'transaksi_id' => $transaksi->id,
                    'dispute_id' => $latestDisputeId,
                    'state' => $toState,
                    'reason_code' => $reasonCode,
                    'reason_text' => $reasonText,
                    'from_state' => $fromState,
                ],
                keyPrefix: $this->buildKey($transaksi->id, 'admin', 0, $eventName, $toState)
            );
        }
    }

    public function queueForPaymentExpiry(
        Transaksi $transaksi,
        int $expiredWinnerId,
        ?int $newWinnerId,
        ?int $failedRank = null,
        ?int $newWinnerRank = null,
        string $source = 'system',
    ): void {
        $transaksi->loadMissing(['ikan.user']);

        $ikan = $transaksi->ikan;
        if (! $ikan) {
            return;
        }

        $sellerId = (int) ($ikan->user_id ?? 0);
        $lotName = trim((string) ($ikan->nama_ikan ?? 'lot lelang'));
        if ($lotName === '') {
            $lotName = 'lot lelang';
        }

        if ($expiredWinnerId > 0) {
            $this->queue(
                $expiredWinnerId,
                'pembayaran',
                'Waktu pembayaran habis',
                "Waktu pembayaran untuk {$lotName} sudah habis. Status transaksi menjadi gagal bayar dan pelanggaran otomatis tercatat.",
                [
                    'transaksi_id' => $transaksi->id,
                    'ikan_id' => $ikan->id,
                    'event' => 'payment_expired',
                    'source' => $source,
                    'failed_rank' => $failedRank,
                ],
                $this->buildKey(
                    $transaksi->id,
                    'buyer-expired',
                    $expiredWinnerId,
                    'payment_expired',
                    'failed'
                )
            );
        }

        if ($sellerId > 0) {
            $this->queue(
                $sellerId,
                'operasional',
                'Pemenang gagal bayar',
                "Pemenang {$lotName} melewati batas waktu pembayaran. Transaksi ditandai gagal bayar dan pelanggaran otomatis dicatat.",
                [
                    'transaksi_id' => $transaksi->id,
                    'ikan_id' => $ikan->id,
                    'event' => 'payment_expired',
                    'source' => $source,
                    'failed_rank' => $failedRank,
                ],
                $this->buildKey(
                    $transaksi->id,
                    'seller',
                    $sellerId,
                    'payment_expired',
                    'failed'
                )
            );
        }
    }

    public function queueForSellerSettlementReady(SellerSettlement $settlement): void
    {
        $settlement->loadMissing(['transaksi.ikan', 'seller', 'batch']);

        $sellerId = (int) ($settlement->seller_id ?? 0);
        if ($sellerId <= 0) {
            return;
        }

        $lotName = trim((string) ($settlement->transaksi?->ikan?->nama_ikan ?? 'hasil penjualan'));
        if ($lotName === '') {
            $lotName = 'hasil penjualan';
        }

        $message = "Dana penjualan untuk {$lotName} sudah masuk antrean pencairan dan sedang disiapkan admin.";

        $this->queue(
            $sellerId,
            'operasional',
            'Settlement siap diproses',
            $message,
            [
                'event' => 'seller_settlement_ready',
                'settlement_id' => $settlement->id,
                'transaksi_id' => $settlement->transaksi_id,
                'batch_id' => $settlement->batch_id,
                'status' => $settlement->status,
            ],
            $this->buildSettlementKey(
                (int) $settlement->id,
                $sellerId,
                'seller_settlement_ready',
                (string) ($settlement->ready_to_pay_at?->timestamp ?? now()->timestamp)
            )
        );
    }

    public function queueForSellerSettlementPaid(SellerSettlement $settlement): void
    {
        $settlement->loadMissing(['transaksi.ikan', 'seller', 'batch']);

        $sellerId = (int) ($settlement->seller_id ?? 0);
        if ($sellerId <= 0) {
            return;
        }

        $lotName = trim((string) ($settlement->transaksi?->ikan?->nama_ikan ?? 'hasil penjualan'));
        if ($lotName === '') {
            $lotName = 'hasil penjualan';
        }

        $referenceText = filled($settlement->transfer_reference)
            ? " Referensi transfer: {$settlement->transfer_reference}."
            : '';

        $this->queue(
            $sellerId,
            'operasional',
            'Settlement sudah dibayar',
            "Dana penjualan untuk {$lotName} telah dibayarkan ke rekening terdaftar.{$referenceText}",
            [
                'event' => 'seller_settlement_paid',
                'settlement_id' => $settlement->id,
                'transaksi_id' => $settlement->transaksi_id,
                'batch_id' => $settlement->batch_id,
                'status' => $settlement->status,
                'transfer_reference' => $settlement->transfer_reference,
            ],
            $this->buildSettlementKey(
                (int) $settlement->id,
                $sellerId,
                'seller_settlement_paid',
                (string) ($settlement->paid_at?->timestamp ?? now()->timestamp)
            )
        );
    }

    public function queuePaymentDeadlineReminders(?int $withinMinutes = null): int
    {
        $window = max(1, $withinMinutes ?? (int) config('marketplace.payment_deadline_reminder_minutes', 10));
        $queued = 0;

        Transaksi::query()
            ->with('ikan')
            ->where('status', 'menunggu_bayar')
            ->where('payment_status', 'pending')
            ->whereNotNull('bayar_sebelum')
            ->where('bayar_sebelum', '>', now())
            ->where('bayar_sebelum', '<=', now()->addMinutes($window))
            ->orderBy('bayar_sebelum')
            ->chunkById(100, function ($orders) use (&$queued): void {
                foreach ($orders as $transaksi) {
                    $buyerId = (int) ($transaksi->pemenang_id ?? 0);
                    if ($buyerId <= 0) {
                        continue;
                    }

                    $lotName = trim((string) ($transaksi->ikan?->nama_ikan ?? 'lot lelang'));
                    if ($lotName === '') {
                        $lotName = 'lot lelang';
                    }

                    $deadline = $transaksi->bayar_sebelum?->format('d M Y H:i');
                    $this->queue(
                        $buyerId,
                        'pembayaran',
                        'Deadline pembayaran segera berakhir',
                        "Batas pembayaran untuk {$lotName} segera berakhir" . ($deadline ? " pada {$deadline}." : '.'),
                        [
                            'event' => 'payment_deadline_reminder',
                            'transaksi_id' => $transaksi->id,
                            'ikan_id' => $transaksi->ikan_id,
                            'bayar_sebelum' => $transaksi->bayar_sebelum?->toIso8601String(),
                        ],
                        $this->buildKey(
                            (int) $transaksi->id,
                            'buyer-deadline',
                            $buyerId,
                            'payment_deadline_reminder',
                            (string) $transaksi->bayar_sebelum?->timestamp
                        )
                    );

                    $queued++;
                }
            });

        return $queued;
    }

    public function queueForAuctionOutcome(
        Ikan $ikan,
        ?Transaksi $transaksi,
        ?int $winnerUserId,
        ?int $winnerRank = null,
        ?string $hardStopReason = null,
    ): void {
        $sellerId = (int) ($ikan->user_id ?? 0);
        $lotName = trim((string) ($ikan->nama_ikan ?? 'lot lelang'));
        if ($lotName === '') {
            $lotName = 'lot lelang';
        }

        $transaksiId = (int) ($transaksi?->id ?? 0);
        $participantIds = AuctionRanking::query()
            ->where('ikan_id', (int) $ikan->id)
            ->pluck('bidder_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($winnerUserId && $winnerUserId > 0) {
            $deadlineText = $transaksi?->bayar_sebelum
                ? ' Batas pembayaran: ' . $transaksi->bayar_sebelum->format('d M Y H:i') . '.'
                : '';
            $winnerMessage = "Anda ditetapkan sebagai pemenang untuk {$lotName}. Segera selesaikan pembayaran sebelum batas waktu berakhir.{$deadlineText}";

            $this->queue(
                $winnerUserId,
                'lelang',
                'Selamat, Anda menang lelang',
                $winnerMessage,
                [
                    'event' => 'auction_won',
                    'ikan_id' => (int) $ikan->id,
                    'transaksi_id' => $transaksiId > 0 ? $transaksiId : null,
                    'winner_id' => $winnerUserId,
                    'winner_rank' => $winnerRank,
                    'bayar_sebelum' => $transaksi?->bayar_sebelum?->toIso8601String(),
                ],
                $this->buildAuctionKey((int) $ikan->id, 'buyer-winner', $winnerUserId, 'auction_won')
            );

            foreach ($participantIds as $participantId) {
                if ($participantId === $winnerUserId) {
                    continue;
                }

                $this->queue(
                    $participantId,
                    'lelang',
                    'Lelang selesai, Anda belum menang',
                    "Lelang {$lotName} telah selesai dan pemenang ditetapkan pada peserta lain. Tetap semangat di lot berikutnya.",
                    [
                        'event' => 'auction_lost',
                        'ikan_id' => (int) $ikan->id,
                        'transaksi_id' => $transaksiId > 0 ? $transaksiId : null,
                        'winner_id' => $winnerUserId,
                        'winner_rank' => $winnerRank,
                    ],
                    $this->buildAuctionKey((int) $ikan->id, 'buyer-loser', $participantId, 'auction_lost')
                );
            }

            if ($sellerId > 0) {
                $sellerMessage = "Lelang {$lotName} telah ditutup dan pemenang sudah ditetapkan. Silakan pantau status pembayaran pembeli.";

                $this->queue(
                    $sellerId,
                    'lelang',
                    'Lelang selesai, pemenang ditemukan',
                    $sellerMessage,
                    [
                        'event' => 'auction_closed_with_winner',
                        'ikan_id' => (int) $ikan->id,
                        'transaksi_id' => $transaksiId > 0 ? $transaksiId : null,
                        'winner_id' => $winnerUserId,
                        'winner_rank' => $winnerRank,
                        'participant_count' => $participantIds->count(),
                    ],
                    $this->buildAuctionKey((int) $ikan->id, 'seller', $sellerId, 'auction_closed_with_winner')
                );
            }

            return;
        }

        $reasonText = $this->hardStopReasonText($hardStopReason);

        if ($sellerId > 0) {
            $this->queue(
                $sellerId,
                'lelang',
                'Lelang selesai tanpa pemenang',
                "Lelang {$lotName} berakhir tanpa pemenang. {$reasonText}",
                [
                    'event' => 'auction_no_winner',
                    'ikan_id' => (int) $ikan->id,
                    'hard_stop_reason' => $hardStopReason,
                ],
                $this->buildAuctionKey((int) $ikan->id, 'seller', $sellerId, 'auction_no_winner:' . (string) $hardStopReason)
            );
        }

        foreach ($participantIds as $participantId) {
            $this->queue(
                $participantId,
                'lelang',
                'Lelang selesai tanpa pemenang',
                "Lelang {$lotName} berakhir tanpa pemenang. {$reasonText}",
                [
                    'event' => 'auction_no_winner',
                    'ikan_id' => (int) $ikan->id,
                    'hard_stop_reason' => $hardStopReason,
                ],
                $this->buildAuctionKey((int) $ikan->id, 'buyer', $participantId, 'auction_no_winner:' . (string) $hardStopReason)
            );
        }
    }

    private function resolveLatestDisputeId(Transaksi $transaksi): ?int
    {
        $latest = $transaksi->disputes()->latest('id')->value('id');

        return $latest ? (int) $latest : null;
    }

    public function processPending(int $limit = 200): int
    {
        $rows = NotificationOutbox::query()
            ->where('status', 'pending')
            ->where(function ($query): void {
                $query->whereNull('available_at')
                    ->orWhere('available_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;

        foreach ($rows as $row) {
            try {
                if (! $row->recipient_user_id) {
                    throw new \RuntimeException('Recipient user id tidak tersedia.');
                }

                InAppNotification::create([
                    'user_id' => $row->recipient_user_id,
                    'category' => $row->category,
                    'title' => $row->title,
                    'message' => $row->message,
                    'payload' => $row->payload,
                    'read_at' => null,
                ]);

                $row->status = 'sent';
                $row->attempts = ((int) $row->attempts) + 1;
                $row->last_error = null;
                $row->processed_at = now();
                $row->save();

                $processed++;
            } catch (\Throwable $e) {
                report($e);

                $row->attempts = ((int) $row->attempts) + 1;
                $row->last_error = mb_substr($e->getMessage(), 0, 500);
                $row->status = ((int) $row->attempts >= 3) ? 'failed' : 'pending';
                $row->available_at = now()->addMinutes(5);
                $row->save();
            }
        }

        return $processed;
    }

    private function queueAdminBroadcast(string $category, string $title, string $message, array $payload, string $keyPrefix): void
    {
        $adminEmails = collect(config('marketplace.admin_whitelist', []))
            ->push((string) config('app.superadmin_email', User::SUPERADMIN_EMAIL))
            ->map(fn (string $value): string => strtolower(trim($value)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        User::query()
            ->where('is_admin', true)
            ->whereIn('email', $adminEmails)
            ->select(['id'])
            ->each(function (User $admin) use ($category, $title, $message, $payload, $keyPrefix): void {
                $this->queue(
                    (int) $admin->id,
                    $category,
                    $title,
                    $message,
                    $payload,
                    $keyPrefix . ':u' . $admin->id
                );
            });
    }

    private function buildKey(int $transaksiId, string $target, int $recipientId, string $eventName, string $toState): string
    {
        return implode(':', [
            'trx',
            $transaksiId,
            $target,
            $recipientId,
            $eventName,
            $toState,
        ]);
    }

    private function buildAuctionKey(int $ikanId, string $target, int $recipientId, string $eventName): string
    {
        return implode(':', [
            'lot',
            $ikanId,
            $target,
            $recipientId,
            $eventName,
        ]);
    }

    private function buildSettlementKey(int $settlementId, int $recipientId, string $eventName, string $version): string
    {
        return implode(':', [
            'settlement',
            $settlementId,
            'seller',
            $recipientId,
            $eventName,
            $version,
        ]);
    }

    private function hardStopReasonText(?string $reason): string
    {
        return match ((string) $reason) {
            'no_valid_bidder' => 'Belum ada penawaran valid pada lot ini.',
            'reserve_not_met' => 'Penawaran yang masuk belum memenuhi batas harga minimum.',
            'all_bidder_failed', 'hard_stop_assignment_failed' => 'Kandidat pemenang tidak dapat diproses.',
            'payment_window_expired' => 'Batas total waktu pembayaran setelah lelang ditutup sudah terlewati.',
            'payment_expired' => 'Pemenang melewati batas pembayaran dan transaksi ditandai gagal bayar.',
            default => 'Silakan evaluasi lot dan atur ulang strategi lelang untuk percobaan berikutnya.',
        };
    }
}
