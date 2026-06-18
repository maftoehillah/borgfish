<?php

namespace App\Services;

use App\Models\Withdrawal;
use App\Models\Wallet;
use App\Jobs\ProcessPayoutJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WithdrawService
{
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Create a withdrawal request. Reserves funds in wallet in SIMULATION mode.
     */
    public function requestWithdraw(int $userId, float $amount, string $idempotencyKey = null, array $meta = []): Withdrawal
    {
        return DB::transaction(function () use ($userId, $amount, $idempotencyKey, $meta) {
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();
            if (! $wallet) {
                throw new RuntimeException('Wallet not found');
            }

            // Ensure idempotency key exists; generate if not provided
            $idempotencyKey = $idempotencyKey ?: (string) Str::uuid();
            $existing = Withdrawal::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            // Always reserve funds at request time to prevent double-withdraw.
            // WalletService will throw on insufficient balance.
            $this->walletService->reserveForWithdraw($userId, $amount, $idempotencyKey, $meta);

            $withdrawal = Withdrawal::create([
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'fee' => $meta['fee'] ?? 0,
                'net_amount' => $meta['net_amount'] ?? $amount - ($meta['fee'] ?? 0),
                'status' => 'PENDING',
                'requested_at' => now(),
                'idempotency_key' => $idempotencyKey,
                'meta' => $meta,
            ]);

            AuditService::log('user', $userId, 'withdraw.requested', 'withdrawal', $withdrawal->id, ['amount' => $amount]);
            event(new \App\Events\WithdrawRequested($withdrawal));

            return $withdrawal;
        });
    }

    /**
     * Approve withdrawal (admin action). In SIMULATION flows this immediately marks PAID.
     */
    public function approveWithdraw(int $withdrawalId, int $adminId)
    {
        return DB::transaction(function () use ($withdrawalId, $adminId) {
            $w = Withdrawal::where('id', $withdrawalId)->lockForUpdate()->first();
            if (! $w || $w->status !== 'PENDING') {
                throw new RuntimeException('Invalid withdrawal');
            }

            $w->status = 'APPROVED';
            $w->approved_at = now();
            $w->admin_id = $adminId;
            $w->save();

            AuditService::log('admin', $adminId, 'withdraw.approved', 'withdrawal', $w->id, []);
            event(new \App\Events\WithdrawApproved($w));

            if (config('wallet.mode') === 'SIMULATION') {
                // immediately pay-out in-sim: deduct pending and mark PAID
                $this->walletService->finalizeWithdraw($w->wallet_id, (float) $w->amount, ['withdrawal_id' => $w->id]);
                $w->status = 'PAID';
                $w->processed_at = now();
                $w->save();
                AuditService::log('system', null, 'withdraw.paid', 'withdrawal', $w->id, []);
            } else {
                // REAL: enqueue payout job which will call gateway and set payout initiated
                // ensure idempotency_key exists for payout calls
                if (empty($w->idempotency_key)) {
                    $w->idempotency_key = (string) Str::uuid();
                    $w->save();
                }

                ProcessPayoutJob::dispatch($w->id);
            }

            return $w;
        });
    }
}
