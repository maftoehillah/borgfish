<?php

namespace App\Services;

use App\Models\Escrow;
use App\Models\Transaksi;
use Illuminate\Support\Facades\DB;
use App\Events\EscrowReleased;

class EscrowService
{
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function holdForTransaction(Transaksi $transaksi, ?string $externalPaymentId = null, array $meta = []): Escrow
    {
        return DB::transaction(function () use ($transaksi, $externalPaymentId, $meta) {
            $escrow = Escrow::firstOrCreate([
                'transaction_id' => $transaksi->id,
            ], [
                'amount' => (float) ($transaksi->escrow_amount ?? $transaksi->harga_final ?? 0),
                'currency' => 'IDR',
                'status' => 'HELD',
                'held_at' => now(),
                'external_payment_id' => $externalPaymentId,
                'meta' => $meta,
            ]);

            return $escrow;
        });
    }

    public function releaseByTransaction(int $transactionId, string $actor = 'system')
    {
        return DB::transaction(function () use ($transactionId, $actor) {
            $escrow = Escrow::where('transaction_id', $transactionId)->lockForUpdate()->first();
            if (! $escrow || $escrow->status !== 'HELD') {
                return null;
            }

            $trx = Transaksi::find($transactionId);
            $escrow->status = 'RELEASED';
            $escrow->released_at = now();
            $escrow->released_by = $actor;
            $escrow->save();

            // update transaction state if model available
            if ($trx) {
                $trx->releaseEscrow();
                $trx->save();
            }

            AuditService::log('system', null, 'escrow.released', 'escrow', $escrow->id, ['transaction_id' => $transactionId]);

            // dispatch event for downstream processing
            event(new EscrowReleased($escrow));

            // In SIMULATION mode, reflect to seller wallet as representation
            if (config('wallet.mode') === 'SIMULATION' && $trx && $trx->ikan) {
                $sellerId = $trx->ikan->user_id;
                $this->walletService->creditAvailable($sellerId, (float) $escrow->amount, ['transaction_id' => $transactionId]);
            }

            return $escrow;
        });
    }
}
