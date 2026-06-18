<?php

namespace App\Services;

use App\Models\SellerSettlement;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SellerSettlementService
{
    public function canAutoCreateForCompletedTransaction(Transaksi $transaksi): bool
    {
        $settings = app(SystemSettingService::class);
        if (! $settings->settlementAutoCreateEnabled()) {
            return false;
        }

        $trx = $transaksi->fresh(['ikan.user.sellerProfile', 'sellerSettlement']);

        if (! $trx || ! $trx->isCompletedForSettlement() || $trx->sellerSettlement) {
            return false;
        }

        $profile = $trx->ikan?->user?->sellerProfile;

        return $profile
            && filled($profile->bank_name)
            && filled($profile->bank_account_number)
            && filled($profile->bank_account_name);
    }

    public function ensureAutoCreatedForCompletedTransaction(Transaksi $transaksi, ?int $actorId = null): ?SellerSettlement
    {
        $trx = $transaksi->fresh(['ikan.user.sellerProfile', 'sellerSettlement']);

        if (! $trx || ! $trx->isCompletedForSettlement() || $trx->sellerSettlement) {
            return $trx?->sellerSettlement;
        }

        $seller = $trx->ikan?->user;
        $profile = $seller?->sellerProfile;

        if (! $seller || ! $profile) {
            return null;
        }

        if (! $this->canAutoCreateForCompletedTransaction($trx)) {
            return null;
        }

        $rules = $this->resolveSettlementRules($trx, $seller);

        return SellerSettlement::query()->firstOrCreate(
            ['transaksi_id' => (int) $trx->id],
            [
                'seller_id' => (int) $seller->id,
                'amount' => (float) $trx->harga_final,
                'status' => $rules['status'],
                'bank_name' => (string) $profile->bank_name,
                'bank_account_number' => (string) $profile->bank_account_number,
                'bank_account_name' => (string) $profile->bank_account_name,
                'admin_note' => $rules['admin_note'],
                'hold_reason' => $rules['hold_reason'],
                'created_by_id' => $actorId,
                'updated_by_id' => $actorId,
                'ready_to_pay_at' => $rules['ready_to_pay_at'],
                'held_at' => $rules['held_at'],
            ]
        );
    }

    public function promoteReadySettlements(int $limit = 100): int
    {
        $limit = max(1, $limit);

        $settlementIds = DB::transaction(function () use ($limit): array {
            $settlements = SellerSettlement::query()
                ->where('status', 'pending')
                ->whereNotNull('ready_to_pay_at')
                ->where('ready_to_pay_at', '<=', now())
                ->orderBy('ready_to_pay_at')
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            $settlements->each(function (SellerSettlement $settlement): void {
                $settlement->status = 'ready_to_pay';
                $settlement->hold_reason = null;
                $settlement->admin_note = trim((string) ($settlement->admin_note ?? '') . "\n\nSettlement otomatis siap dibayar setelah delay pencairan selesai.");
                $settlement->save();

                AuditService::log('system', null, 'seller_settlement.auto_ready_to_pay', 'seller_settlements', (int) $settlement->id, [
                    'transaksi_id' => (int) $settlement->transaksi_id,
                    'seller_id' => (int) $settlement->seller_id,
                    'ready_to_pay_at' => $settlement->ready_to_pay_at?->toIso8601String(),
                ]);
            });

            return $settlements->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }, 3);

        if ($settlementIds !== []) {
            $notificationService = app(NotificationOutboxService::class);

            SellerSettlement::query()
                ->with(['transaksi.ikan', 'seller'])
                ->whereIn('id', $settlementIds)
                ->get()
                ->each(function (SellerSettlement $settlement) use ($notificationService): void {
                    $notificationService->queueForSellerSettlementReady($settlement);
                });

            $notificationService->processPending(100);
        }

        return count($settlementIds);
    }

    /**
     * @return array{status:string, admin_note:?string, hold_reason:?string, ready_to_pay_at:mixed, held_at:mixed}
     */
    private function resolveSettlementRules(Transaksi $transaksi, User $seller): array
    {
        $settings = app(SystemSettingService::class);
        $amount = (float) $transaksi->harga_final;
        $minPayout = (float) $settings->settlementMinPayoutAmount();
        $payoutDelayDays = $settings->settlementPayoutDelayDays();

        if ($minPayout > 0 && $amount < $minPayout) {
            return [
                'status' => 'held',
                'admin_note' => 'Settlement ditahan karena nominal belum memenuhi minimum payout.',
                'hold_reason' => 'Nominal settlement di bawah minimum payout.',
                'ready_to_pay_at' => null,
                'held_at' => now(),
            ];
        }

        if (
            $settings->settlementHoldOnDispute()
            && (string) $transaksi->fulfillment_state === 'DISENGKETAKAN'
            && $transaksi->disputes()->where('status', 'open')->exists()
        ) {
            return [
                'status' => 'held',
                'admin_note' => 'Settlement ditahan karena transaksi masih memiliki sengketa aktif.',
                'hold_reason' => 'Transaksi masih memiliki sengketa aktif.',
                'ready_to_pay_at' => null,
                'held_at' => now(),
            ];
        }

        if ($settings->settlementHoldOnViolation() && $seller->violations()->where('status', 'active')->exists()) {
            return [
                'status' => 'held',
                'admin_note' => 'Settlement ditahan karena seller masih memiliki pelanggaran aktif.',
                'hold_reason' => 'Seller memiliki pelanggaran aktif.',
                'ready_to_pay_at' => null,
                'held_at' => now(),
            ];
        }

        if ($settings->settlementRequiresAdminReview()) {
            $notes = [];

            $notes[] = 'Settlement menunggu review admin.';

            if ($payoutDelayDays > 0) {
                $notes[] = 'Delay pencairan ' . $payoutDelayDays . ' hari setelah transaksi selesai.';
            }

            return [
                'status' => 'pending',
                'admin_note' => implode(' ', $notes),
                'hold_reason' => null,
                'ready_to_pay_at' => null,
                'held_at' => null,
            ];
        }

        if ($payoutDelayDays > 0) {
            $completedAt = $transaksi->completed_at
                ?? $transaksi->completed_by_buyer_at
                ?? $transaksi->updated_at
                ?? now();

            return [
                'status' => 'pending',
                'admin_note' => 'Delay pencairan ' . $payoutDelayDays . ' hari setelah transaksi selesai.',
                'hold_reason' => null,
                'ready_to_pay_at' => $completedAt->copy()->addDays($payoutDelayDays),
                'held_at' => null,
            ];
        }

        return [
            'status' => 'ready_to_pay',
            'admin_note' => 'Settlement siap diproses otomatis sesuai aturan sistem.',
            'hold_reason' => null,
            'ready_to_pay_at' => now(),
            'held_at' => null,
        ];
    }
}
