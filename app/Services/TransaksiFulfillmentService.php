<?php

namespace App\Services;

use App\Models\Transaksi;
use App\Models\TransactionDispute;
use App\Models\TransactionStateLog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class TransaksiFulfillmentService
{
    public const STATE_DIBAYAR = 'DIBAYAR';

    public const STATE_DIPROSES_PENJUAL = 'DIPROSES_PENJUAL';

    public const STATE_DIKIRIM = 'DIKIRIM';

    public const STATE_SELESAI = 'SELESAI';

    public const STATE_GAGAL = 'GAGAL';

    public const STATE_DISENGKETAKAN = 'DISENGKETAKAN';

    public const ACTOR_SYSTEM = 'system';

    public const ACTOR_SELLER = 'seller';

    public const ACTOR_BUYER = 'buyer';

    public const ACTOR_ADMIN = 'admin';

    private const SELLER_ACK_DEADLINE_HOURS = 2;

    private const SELLER_PROCESS_DEADLINE_HOURS = 24;

    private const BUYER_CONFIRM_DEADLINE_HOURS = 72;

    public function markPaid(Transaksi $transaksi, ?string $providerRef = null): void
    {
        $currentState = (string) ($transaksi->fresh()?->fulfillment_state ?? '');
        if (in_array($currentState, [
            self::STATE_DIPROSES_PENJUAL,
            self::STATE_DIKIRIM,
            self::STATE_SELESAI,
            self::STATE_DISENGKETAKAN,
            self::STATE_GAGAL,
        ], true)) {
            return;
        }

        $this->transition(
            transaksiId: (int) $transaksi->id,
            toState: self::STATE_DIBAYAR,
            eventName: 'payment_settled',
            actorType: self::ACTOR_SYSTEM,
            actorId: null,
            reasonCode: 'PAYMENT_SETTLED',
            reasonText: 'Pembayaran telah dikonfirmasi.',
            metadata: ['provider_ref' => $providerRef]
        );
    }

    public function markSellerProcessing(Transaksi $transaksi, ?int $sellerId = null): void
    {
        $currentState = (string) ($transaksi->fresh()?->fulfillment_state ?? '');
        if (in_array($currentState, [self::STATE_DIKIRIM, self::STATE_SELESAI, self::STATE_GAGAL, self::STATE_DISENGKETAKAN], true)) {
            return;
        }

        $this->transition(
            transaksiId: (int) $transaksi->id,
            toState: self::STATE_DIPROSES_PENJUAL,
            eventName: 'seller_processing_started',
            actorType: self::ACTOR_SELLER,
            actorId: $sellerId,
            reasonCode: 'SELLER_ACKNOWLEDGED',
            reasonText: 'Penjual mulai memproses pesanan.'
        );
    }

    public function markPickupValidated(Transaksi $transaksi, ?int $sellerId = null): void
    {
        $currentState = (string) ($transaksi->fresh()?->fulfillment_state ?? '');
        if (in_array($currentState, [self::STATE_SELESAI, self::STATE_GAGAL, self::STATE_DISENGKETAKAN], true)) {
            return;
        }

        if ($currentState === self::STATE_DIBAYAR) {
            $this->markSellerProcessing($transaksi, $sellerId);
        }

        $this->transition(
            transaksiId: (int) $transaksi->id,
            toState: self::STATE_DIKIRIM,
            eventName: 'seller_validated_pickup',
            actorType: self::ACTOR_SELLER,
            actorId: $sellerId,
            reasonCode: 'PICKUP_VALIDATED',
            reasonText: 'Penjual memvalidasi penjemput datang.'
        );
    }

    public function markCompletedByBuyer(Transaksi $transaksi, int $buyerId): void
    {
        $currentState = (string) ($transaksi->fresh()?->fulfillment_state ?? '');
        if (in_array($currentState, [self::STATE_SELESAI, self::STATE_GAGAL, self::STATE_DISENGKETAKAN], true)) {
            return;
        }

        $this->transition(
            transaksiId: (int) $transaksi->id,
            toState: self::STATE_SELESAI,
            eventName: 'buyer_confirmed_received',
            actorType: self::ACTOR_BUYER,
            actorId: $buyerId,
            reasonCode: 'BUYER_CONFIRMED_RECEIVED',
                    reasonText: 'Pembeli mengonfirmasi transaksi selesai.'
        );
    }

    public function markCompletedBySystem(Transaksi $transaksi, string $reasonCode = 'AUTO_COMPLETED'): void
    {
        $currentState = (string) ($transaksi->fresh()?->fulfillment_state ?? '');
        if (in_array($currentState, [self::STATE_SELESAI, self::STATE_GAGAL], true)) {
            return;
        }

        $this->transition(
            transaksiId: (int) $transaksi->id,
            toState: self::STATE_SELESAI,
            eventName: 'auto_completed',
            actorType: self::ACTOR_SYSTEM,
            actorId: null,
            reasonCode: $reasonCode,
            reasonText: 'Sistem menyelesaikan transaksi otomatis setelah timeout.'
        );
    }

    public function markFailed(Transaksi $transaksi, string $reasonCode, string $reasonText, string $actorType = self::ACTOR_SYSTEM, ?int $actorId = null): void
    {
        $currentState = (string) ($transaksi->fresh()?->fulfillment_state ?? '');
        if (in_array($currentState, [self::STATE_GAGAL, self::STATE_SELESAI], true)) {
            return;
        }

        $this->transition(
            transaksiId: (int) $transaksi->id,
            toState: self::STATE_GAGAL,
            eventName: 'transaction_failed',
            actorType: $actorType,
            actorId: $actorId,
            reasonCode: $reasonCode,
            reasonText: $reasonText
        );
    }

    public function openDispute(Transaksi $transaksi, string $reasonCode, string $reasonText, string $actorType = self::ACTOR_SYSTEM, ?int $actorId = null): void
    {
        $trx = $transaksi->fresh(['ikan']);
        if (! $trx) {
            return;
        }

        $this->ensureOpenDisputeRecord($trx, $reasonCode, $reasonText, $actorType, $actorId);

        $currentState = (string) ($trx->fulfillment_state ?? '');
        if (in_array($currentState, [self::STATE_DISENGKETAKAN, self::STATE_SELESAI, self::STATE_GAGAL], true)) {
            return;
        }

        $this->transition(
            transaksiId: (int) $trx->id,
            toState: self::STATE_DISENGKETAKAN,
            eventName: 'transaction_disputed',
            actorType: $actorType,
            actorId: $actorId,
            reasonCode: $reasonCode,
            reasonText: $reasonText
        );

        if ($actorType === self::ACTOR_ADMIN && $actorId) {
            AuditService::log('admin', $actorId, 'transaction.dispute_opened', 'transaksis', (int) $trx->id, [
                'reason_code' => $reasonCode,
                'reason_text' => $reasonText,
                'fulfillment_state' => $trx->fresh()?->fulfillment_state,
            ]);
        }
    }

    public function createBuyerDispute(Transaksi $transaksi, int $buyerId, string $reason, ?string $detail = null): TransactionDispute
    {
        return DB::transaction(function () use ($transaksi, $buyerId, $reason, $detail): TransactionDispute {
            $trx = Transaksi::query()->lockForUpdate()->findOrFail($transaksi->id);

            if ((int) $trx->pemenang_id !== $buyerId) {
                throw new \RuntimeException('Anda tidak berhak mengajukan sengketa pada transaksi ini.');
            }

            if (in_array((string) $trx->fulfillment_state, [self::STATE_SELESAI, self::STATE_GAGAL], true)) {
                throw new \RuntimeException('Transaksi sudah final dan tidak bisa diajukan sengketa.');
            }

            $existingOpen = TransactionDispute::query()
                ->where('transaksi_id', $trx->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($existingOpen) {
                return $existingOpen;
            }

            $sellerId = (int) ($trx->ikan?->user_id ?? 0);
            if ($sellerId <= 0) {
                throw new \RuntimeException('Data penjual pada transaksi tidak valid.');
            }

            $dispute = TransactionDispute::create([
                'transaksi_id' => $trx->id,
                'ikan_id' => (int) $trx->ikan_id,
                'buyer_id' => (int) $trx->pemenang_id,
                'seller_id' => $sellerId,
                'status' => 'open',
                'complaint_reason' => $reason,
                'complaint_detail' => $detail,
                'opened_by_type' => self::ACTOR_BUYER,
                'opened_by_id' => $buyerId,
                'opened_at' => now(),
            ]);

            $this->openDispute(
                $trx,
                'BUYER_COMPLAINT',
                'Pembeli mengajukan komplain: ' . $reason,
                self::ACTOR_BUYER,
                $buyerId
            );

            return $dispute;
        }, 3);
    }

    public function resolveOpenDisputeByAdmin(Transaksi $transaksi, int $adminId, string $resolution, string $note): void
    {
        DB::transaction(function () use ($transaksi, $adminId, $resolution, $note): void {
            $trx = Transaksi::query()->lockForUpdate()->findOrFail($transaksi->id);

            $dispute = TransactionDispute::query()
                ->where('transaksi_id', $trx->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $dispute) {
                throw new \RuntimeException('Tidak ada sengketa aktif untuk diselesaikan.');
            }

            if ($resolution === 'completed') {
                $this->transition(
                    transaksiId: (int) $trx->id,
                    toState: self::STATE_SELESAI,
                    eventName: 'admin_resolve_dispute_completed',
                    actorType: self::ACTOR_ADMIN,
                    actorId: $adminId,
                    reasonCode: 'DISPUTE_RESOLVED_COMPLETED',
                    reasonText: $note,
                    metadata: ['dispute_id' => $dispute->id]
                );

                $dispute->status = 'resolved_completed';
            } elseif ($resolution === 'failed') {
                $this->transition(
                    transaksiId: (int) $trx->id,
                    toState: self::STATE_GAGAL,
                    eventName: 'admin_resolve_dispute_failed',
                    actorType: self::ACTOR_ADMIN,
                    actorId: $adminId,
                    reasonCode: 'DISPUTE_RESOLVED_FAILED',
                    reasonText: $note,
                    metadata: ['dispute_id' => $dispute->id]
                );

                $dispute->status = 'resolved_failed';
            } else {
                throw new \RuntimeException('Resolusi sengketa tidak valid.');
            }

            $dispute->resolved_by_id = $adminId;
            $dispute->resolution_note = $note;
            $dispute->resolved_at = now();
            $dispute->save();

            AuditService::log('admin', $adminId, 'transaction.dispute_resolved', 'transaksis', (int) $trx->id, [
                'dispute_id' => (int) $dispute->id,
                'resolution' => $resolution,
                'note' => $note,
                'dispute_status' => $dispute->status,
                'fulfillment_state' => $trx->fresh()?->fulfillment_state,
            ]);
        }, 3);
    }

    private function ensureOpenDisputeRecord(Transaksi $transaksi, string $reasonCode, string $reasonText, string $actorType, ?int $actorId): void
    {
        $exists = TransactionDispute::query()
            ->where('transaksi_id', $transaksi->id)
            ->where('status', 'open')
            ->exists();

        if ($exists) {
            return;
        }

        $sellerId = (int) ($transaksi->ikan?->user_id ?? 0);
        if ($sellerId <= 0) {
            return;
        }

        TransactionDispute::create([
            'transaksi_id' => $transaksi->id,
            'ikan_id' => (int) $transaksi->ikan_id,
            'buyer_id' => (int) $transaksi->pemenang_id,
            'seller_id' => $sellerId,
            'status' => 'open',
            'complaint_reason' => mb_substr(strtolower($reasonCode), 0, 64),
            'complaint_detail' => mb_substr($reasonText, 0, 500),
            'opened_by_type' => $actorType,
            'opened_by_id' => $actorId,
            'opened_at' => now(),
        ]);
    }

    public function syncSlaTimeouts(): void
    {
        Transaksi::query()
            ->where('fulfillment_state', self::STATE_DIBAYAR)
            ->whereNotNull('seller_ack_deadline_at')
            ->where('seller_ack_deadline_at', '<', now())
            ->each(function (Transaksi $transaksi): void {
                $this->openDispute(
                    $transaksi,
                    'SELLER_ACK_TIMEOUT',
                    'Penjual tidak merespons pembayaran dalam SLA.'
                );
            });

        Transaksi::query()
            ->where('fulfillment_state', self::STATE_DIPROSES_PENJUAL)
            ->whereNotNull('seller_process_deadline_at')
            ->where('seller_process_deadline_at', '<', now())
            ->each(function (Transaksi $transaksi): void {
                $this->openDispute(
                    $transaksi,
                    'SELLER_PICKUP_TIMEOUT',
                    'Penjual melewati batas waktu proses packing atau penjemputan.'
                );
            });

        Transaksi::query()
            ->where('fulfillment_state', self::STATE_DIKIRIM)
            ->whereNotNull('buyer_confirm_deadline_at')
            ->where('buyer_confirm_deadline_at', '<', now())
            ->whereDoesntHave('stateLogs', fn ($query) => $query->where('to_state', self::STATE_DISENGKETAKAN))
            ->each(function (Transaksi $transaksi): void {
                $this->markCompletedBySystem($transaksi, 'BUYER_CONFIRM_TIMEOUT');
            });
    }

    private function transition(
        int $transaksiId,
        string $toState,
        string $eventName,
        string $actorType,
        ?int $actorId = null,
        ?string $reasonCode = null,
        ?string $reasonText = null,
        array $metadata = [],
    ): void {
        DB::transaction(function () use (
            $transaksiId,
            $toState,
            $eventName,
            $actorType,
            $actorId,
            $reasonCode,
            $reasonText,
            $metadata,
        ): void {
            $transaksi = Transaksi::query()
                ->lockForUpdate()
                ->with('ikan')
                ->find($transaksiId);

            if (! $transaksi) {
                throw new ModelNotFoundException('Transaksi tidak ditemukan.');
            }

            $fromState = $transaksi->fulfillment_state;

            if ($fromState === $toState) {
                return;
            }

            if (! $this->isAllowedTransition($fromState, $toState)) {
                throw new \RuntimeException("Transisi fulfillment tidak valid dari {$fromState} ke {$toState}.");
            }

            if (! $this->isAllowedActor($toState, $actorType)) {
                throw new \RuntimeException("Aktor {$actorType} tidak diizinkan mengubah ke state {$toState}.");
            }

            $this->applyStateSideEffects($transaksi, $toState);
            $this->syncOrderColumns($transaksi, $toState);

            $transaksi->fulfillment_state = $toState;
            $transaksi->state_version = ((int) ($transaksi->state_version ?? 0)) + 1;
            $transaksi->state_reason_code = $reasonCode;
            $transaksi->state_reason_text = $reasonText;
            $transaksi->save();

            TransactionStateLog::create([
                'transaksi_id' => $transaksi->id,
                'from_state' => $fromState,
                'to_state' => $toState,
                'event_name' => $eventName,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'reason_code' => $reasonCode,
                'reason_text' => $reasonText,
                'metadata' => empty($metadata) ? null : $metadata,
                'created_at' => now(),
            ]);

            app(NotificationOutboxService::class)->queueForFulfillmentTransition(
                $transaksi,
                $fromState,
                $toState,
                $eventName,
                $reasonCode,
                $reasonText
            );

            if ($toState === self::STATE_SELESAI) {
                app(SellerSettlementService::class)->ensureAutoCreatedForCompletedTransaction(
                    $transaksi,
                    $actorId
                );
            }
        }, 3);
    }

    private function applyStateSideEffects(Transaksi $transaksi, string $toState): void
    {
        if ($toState === self::STATE_DIBAYAR) {
            $transaksi->paid_at = $transaksi->paid_at ?? $transaksi->dibayar_pada ?? now();
            $transaksi->seller_ack_deadline_at = $transaksi->seller_ack_deadline_at ?? now()->addHours(self::SELLER_ACK_DEADLINE_HOURS);
            $transaksi->seller_process_deadline_at = $transaksi->seller_process_deadline_at ?? now()->addHours(self::SELLER_PROCESS_DEADLINE_HOURS);
            $transaksi->failed_at = null;
            $transaksi->disputed_at = null;

            return;
        }

        if ($toState === self::STATE_DIPROSES_PENJUAL) {
            $transaksi->seller_ack_at = $transaksi->seller_ack_at ?? now();
            $transaksi->seller_process_deadline_at = $transaksi->seller_process_deadline_at ?? now()->addHours(self::SELLER_PROCESS_DEADLINE_HOURS);
            $transaksi->failed_at = null;

            return;
        }

        if ($toState === self::STATE_DIKIRIM) {
            $transaksi->buyer_confirm_deadline_at = $transaksi->buyer_confirm_deadline_at ?? now()->addHours(self::BUYER_CONFIRM_DEADLINE_HOURS);
            $transaksi->failed_at = null;

            return;
        }

        if ($toState === self::STATE_SELESAI) {
            $transaksi->completed_at = $transaksi->completed_at ?? now();
            $transaksi->failed_at = null;
            $transaksi->disputed_at = null;

            return;
        }

        if ($toState === self::STATE_GAGAL) {
            $transaksi->failed_at = $transaksi->failed_at ?? now();

            return;
        }

        if ($toState === self::STATE_DISENGKETAKAN) {
            $transaksi->disputed_at = $transaksi->disputed_at ?? now();
        }
    }

    private function syncOrderColumns(Transaksi $transaksi, string $toState): void
    {
        if ($toState === self::STATE_DIBAYAR) {
            if ($transaksi->status !== 'lunas') {
                $transaksi->status = 'lunas';
            }

            return;
        }

        if ($toState === self::STATE_DIPROSES_PENJUAL) {
            $transaksi->status = 'lunas';

            return;
        }

        if ($toState === self::STATE_DIKIRIM) {
            $transaksi->status = 'lunas';

            return;
        }

        if ($toState === self::STATE_SELESAI) {
            $transaksi->status = 'lunas';

            return;
        }

        if ($toState === self::STATE_DISENGKETAKAN) {
            $transaksi->status = 'proses';

            return;
        }

        if ($toState === self::STATE_GAGAL) {
            $transaksi->status = 'gagal';
        }
    }

    private function isAllowedTransition(?string $fromState, string $toState): bool
    {
        $map = [
            null => [self::STATE_DIBAYAR, self::STATE_DIPROSES_PENJUAL, self::STATE_DIKIRIM, self::STATE_SELESAI, self::STATE_GAGAL, self::STATE_DISENGKETAKAN],
            self::STATE_DIBAYAR => [self::STATE_DIPROSES_PENJUAL, self::STATE_DISENGKETAKAN, self::STATE_GAGAL],
            self::STATE_DIPROSES_PENJUAL => [self::STATE_DIKIRIM, self::STATE_DISENGKETAKAN, self::STATE_GAGAL],
            self::STATE_DIKIRIM => [self::STATE_SELESAI, self::STATE_DISENGKETAKAN, self::STATE_GAGAL],
            self::STATE_DISENGKETAKAN => [self::STATE_SELESAI, self::STATE_GAGAL],
            self::STATE_SELESAI => [],
            self::STATE_GAGAL => [],
        ];

        return in_array($toState, $map[$fromState] ?? [], true);
    }

    private function isAllowedActor(string $toState, string $actorType): bool
    {
        $allowed = [
            self::STATE_DIBAYAR => [self::ACTOR_SYSTEM, self::ACTOR_ADMIN],
            self::STATE_DIPROSES_PENJUAL => [self::ACTOR_SELLER, self::ACTOR_SYSTEM, self::ACTOR_ADMIN],
            self::STATE_DIKIRIM => [self::ACTOR_SELLER, self::ACTOR_SYSTEM, self::ACTOR_ADMIN],
            self::STATE_SELESAI => [self::ACTOR_BUYER, self::ACTOR_SYSTEM, self::ACTOR_ADMIN],
            self::STATE_GAGAL => [self::ACTOR_SYSTEM, self::ACTOR_ADMIN],
            self::STATE_DISENGKETAKAN => [self::ACTOR_SYSTEM, self::ACTOR_ADMIN, self::ACTOR_BUYER],
        ];

        return in_array($actorType, $allowed[$toState] ?? [], true);
    }
}
