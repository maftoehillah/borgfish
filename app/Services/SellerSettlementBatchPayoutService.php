<?php

namespace App\Services;

use App\Models\SellerSettlementBatch;
use App\Models\SellerSettlement;
use Illuminate\Support\Facades\DB;

class SellerSettlementBatchPayoutService
{
    /**
     * @param  array<int>  $settlementIds
     */
    public function markAsPaid(
        array $settlementIds,
        ?int $actorId = null,
        ?string $transferReference = null,
        ?string $transferProofPath = null,
        ?string $adminNote = null,
    ): ?SellerSettlementBatch {
        if (! filled($transferReference) && ! filled($transferProofPath)) {
            throw new \InvalidArgumentException('Referensi transfer atau bukti transfer wajib diisi sebelum settlement ditandai dibayar.');
        }

        $ids = collect($settlementIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return null;
        }

        $batch = DB::transaction(function () use ($ids, $actorId, $transferReference, $transferProofPath, $adminNote): ?SellerSettlementBatch {
            $settlements = SellerSettlement::query()
                ->whereIn('id', $ids->all())
                ->where('status', 'ready_to_pay')
                ->lockForUpdate()
                ->get();

            if ($settlements->isEmpty()) {
                return null;
            }

            $batch = SellerSettlementBatch::create([
                'batch_number' => $this->generateBatchNumber(),
                'status' => 'paid',
                'transfer_reference' => $transferReference,
                'transfer_proof_path' => $transferProofPath,
                'admin_note' => $adminNote,
                'created_by_id' => $actorId,
                'processed_at' => now(),
                'total_amount' => (float) $settlements->sum('amount'),
                'settlement_count' => $settlements->count(),
            ]);

            $settlements->each(function (SellerSettlement $settlement) use (
                $batch,
                $actorId,
                $transferReference,
                $transferProofPath,
                $adminNote
            ): void {
                $settlement->batch_id = $batch->id;
                $settlement->status = 'paid';
                $settlement->paid_at = $settlement->paid_at ?? now();
                $settlement->updated_by_id = $actorId;
                $settlement->hold_reason = null;

                if (filled($transferReference)) {
                    $settlement->transfer_reference = (string) $transferReference;
                }

                if (filled($transferProofPath)) {
                    $settlement->transfer_proof_path = (string) $transferProofPath;
                }

                if (filled($adminNote)) {
                    $existing = trim((string) ($settlement->admin_note ?? ''));
                    $note = trim((string) $adminNote);
                    $settlement->admin_note = $existing === '' ? $note : "{$existing}\n\n{$note}";
                }

                $settlement->save();
            });

            return $batch;
        }, 3);

        if ($batch) {
            AuditService::log('admin', $actorId, 'seller_settlement.batch_paid', 'seller_settlement_batches', (int) $batch->id, [
                'settlement_ids' => $batch->settlements()->pluck('seller_settlements.id')->all(),
                'settlement_count' => (int) $batch->settlement_count,
                'total_amount' => (float) $batch->total_amount,
                'transfer_reference' => $batch->transfer_reference,
            ]);

            $notificationService = app(NotificationOutboxService::class);

            $batch->settlements()
                ->with(['transaksi.ikan', 'seller'])
                ->get()
                ->each(function (SellerSettlement $settlement) use ($notificationService): void {
                    $notificationService->queueForSellerSettlementPaid($settlement);
                });

            $notificationService->processPending(100);
        }

        return $batch;
    }

    private function generateBatchNumber(): string
    {
        $prefix = 'SET-' . now()->format('Ymd-His');
        $sequence = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);

        return "{$prefix}-{$sequence}";
    }
}
