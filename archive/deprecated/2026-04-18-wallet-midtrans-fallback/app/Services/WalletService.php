<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use RuntimeException;

class WalletService
{
    public function getOrCreate(int $userId): Wallet
    {
        return Wallet::firstOrCreate(['user_id' => $userId], ['currency' => 'IDR']);
    }

    protected function createTx(Wallet $wallet, string $type, float $amount, float $balanceAfter, string $status = 'completed', array $meta = [], ?string $idempotencyKey = null, ?string $relatedType = null, ?int $relatedId = null): WalletTransaction
    {
        if ($idempotencyKey) {
            $existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        try {
            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'status' => $status,
                'idempotency_key' => $idempotencyKey,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'meta' => $meta,
            ]);
        } catch (QueryException $e) {
            // possible unique constraint race on idempotency_key: fetch existing
            if ($idempotencyKey) {
                $existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            throw $e;
        }
    }

    public function creditAvailable(int $userId, float $amount, array $meta = [], ?string $idempotencyKey = null): WalletTransaction
    {
        return DB::transaction(function () use ($userId, $amount, $meta, $idempotencyKey) {
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();
            if (! $wallet) {
                $wallet = Wallet::create(['user_id' => $userId, 'currency' => 'IDR']);
            }

            $wallet->balance_available = (float) $wallet->balance_available + (float) $amount;
            $wallet->version = ((int) $wallet->version) + 1;
            $wallet->save();

            $tx = $this->createTx($wallet, 'credit', $amount, (float) $wallet->balance_available, 'completed', $meta, $idempotencyKey);
            AuditService::log('system', null, 'wallet.credit', 'wallet', $wallet->id, ['amount' => $amount, 'meta' => $meta]);

            return $tx;
        });
    }

    public function reserveForWithdraw(int $userId, float $amount, ?string $idempotencyKey = null, array $meta = []): WalletTransaction
    {
        return DB::transaction(function () use ($userId, $amount, $idempotencyKey, $meta) {
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();
            if (! $wallet) {
                throw new RuntimeException('Wallet not found');
            }

            if ((float) $wallet->balance_available < (float) $amount) {
                throw new RuntimeException('Insufficient balance');
            }

            $wallet->balance_available = (float) $wallet->balance_available - (float) $amount;
            $wallet->balance_pending = (float) $wallet->balance_pending + (float) $amount;
            $wallet->version = ((int) $wallet->version) + 1;
            $wallet->save();

            $tx = $this->createTx($wallet, 'withdraw_reserve', -1 * $amount, (float) $wallet->balance_available, 'pending', $meta, $idempotencyKey);
            AuditService::log('system', null, 'wallet.reserve_withdraw', 'wallet', $wallet->id, ['amount' => $amount]);

            return $tx;
        });
    }

    public function finalizeWithdraw(int $walletId, float $amount, array $meta = []): WalletTransaction
    {
        return DB::transaction(function () use ($walletId, $amount, $meta) {
            $wallet = Wallet::where('id', $walletId)->lockForUpdate()->first();
            if (! $wallet) {
                throw new RuntimeException('Wallet not found');
            }

            $wallet->balance_pending = max(0, (float) $wallet->balance_pending - (float) $amount);
            // balance_available already deducted at reserve time
            $wallet->version = ((int) $wallet->version) + 1;
            $wallet->save();

            $tx = $this->createTx($wallet, 'withdrawal', -1 * $amount, (float) $wallet->balance_available, 'completed', $meta);
            AuditService::log('system', null, 'wallet.withdraw', 'wallet', $wallet->id, ['amount' => $amount]);

            return $tx;
        });
    }

    public function restoreReserved(int $walletId, float $amount, array $meta = []): WalletTransaction
    {
        return DB::transaction(function () use ($walletId, $amount, $meta) {
            $wallet = Wallet::where('id', $walletId)->lockForUpdate()->first();
            if (! $wallet) {
                throw new RuntimeException('Wallet not found');
            }

            $wallet->balance_pending = max(0, (float) $wallet->balance_pending - (float) $amount);
            $wallet->balance_available = (float) $wallet->balance_available + (float) $amount;
            $wallet->version = ((int) $wallet->version) + 1;
            $wallet->save();

            $tx = $this->createTx(
                $wallet,
                'withdraw_reserve_released',
                $amount,
                (float) $wallet->balance_available,
                'completed',
                $meta,
                null,
                'withdrawals',
                $meta['withdrawal_id'] ?? null
            );

            AuditService::log('system', null, 'wallet.restore_reserve', 'wallet', $wallet->id, ['amount' => $amount, 'meta' => $meta]);

            return $tx;
        });
    }
}
