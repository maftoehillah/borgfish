<?php

namespace App\Jobs;

use App\Models\Withdrawal;
use App\Services\PaymentGateway\PaymentGatewayInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationOutboxService;

class ProcessPayoutJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected int $withdrawalId;

    public function __construct(int $withdrawalId)
    {
        $this->withdrawalId = $withdrawalId;
    }

    public function handle(): void
    {
        // Step 1: lock and validate, then release transaction before network call
        // Reserve the workflow by persistently marking the withdrawal as initiated so
        // concurrent job executions don't both call the gateway.
        $w = DB::transaction(function () {
            $w = Withdrawal::where('id', $this->withdrawalId)->lockForUpdate()->first();
            if (! $w || $w->status !== 'APPROVED') {
                return null;
            }

            // Ensure idempotency key exists
            if (empty($w->idempotency_key)) {
                $w->idempotency_key = (string) \Illuminate\Support\Str::uuid();
            }

            // Mark as initiated so other workers won't attempt the same payout
            $w->status = 'PAYOUT_INITIATED';
            $w->payout_provider = config('wallet.gateway');
            $w->save();

            return $w->refresh();
        });

        if (! $w) {
            return;
        }

        // SIMULATION mode: finalize locally
        if (config('wallet.mode') === 'SIMULATION') {
            app(\App\Services\WalletService::class)->finalizeWithdraw($w->wallet_id, (float) $w->amount, ['withdrawal_id' => $w->id]);
            $w->status = 'PAID';
            $w->processed_at = now();
            $w->save();
            return;
        }

        /** @var PaymentGatewayInterface $gateway */
        $gateway = app(PaymentGatewayInterface::class);

        $idempotency = $w->idempotency_key ?: ('withdraw-' . $w->id);

        $payload = [
            'id' => $w->id,
            'amount' => (float) $w->net_amount,
            'beneficiary' => $w->meta['beneficiary'] ?? null,
            'reference' => $idempotency,
            'idempotency_key' => $idempotency,
        ];

        try {
            $resp = $gateway->payout($payload);
        } catch (\Throwable $e) {
            // Allow retries; if we've retried too many times, mark FAILED and audit
            $max = (int) config('wallet.max_payout_attempts', 3);
            $attempts = method_exists($this, 'attempts') ? $this->attempts() : 1;
            if ($attempts >= $max) {
                DB::transaction(function () use ($w, $e) {
                    $w = Withdrawal::where('id', $this->withdrawalId)->lockForUpdate()->first();
                    if ($w && in_array($w->status, ['APPROVED', 'PAYOUT_INITIATED'], true)) {
                        // restore reserved funds for generic wallet
                        try {
                            app(\App\Services\WalletService::class)->restoreReserved($w->wallet_id, (float) $w->amount, ['withdrawal_id' => $w->id]);
                        } catch (\Throwable $ex) {
                            \App\Services\AuditService::log('system', null, 'withdraw.restore_failed', 'withdrawal', $w->id, ['error' => $ex->getMessage()]);
                        }

                        $w->status = 'FAILED';
                        $w->save();
                        \App\Services\AuditService::log('system', null, 'withdraw.payout_failed', 'withdrawal', $w->id, ['error' => $e->getMessage()]);
                        try {
                            app(NotificationOutboxService::class)->queue(
                                (int) $w->user_id,
                                'saldo',
                                'Withdraw gagal',
                                'Pencairan ' . formatRupiah((float) $w->amount) . ' gagal diproses. Dana telah dikembalikan ke saldo Anda.',
                                ['withdrawal_id' => $w->id, 'status' => 'failed'],
                                'withdrawal:failed:' . $w->id
                            );
                        } catch (\Throwable $ex) {
                            \App\Services\AuditService::log('system', null, 'notify.outbox_error', 'withdrawal', $w->id, ['error' => $ex->getMessage()]);
                        }
                    }
                });
                return;
            }

            throw $e; // bubble up for queue retry/backoff
        }

        // Persist payout initiation info and handle immediate completion if provider returns final status
        $externalId = $resp['id'] ?? $resp['payout_id'] ?? null;
        $provStatus = null;
        if (isset($resp['status'])) {
            $provStatus = strtoupper((string) $resp['status']);
        } elseif (isset($resp['transaction_status'])) {
            $provStatus = strtoupper((string) $resp['transaction_status']);
        } elseif (isset($resp['payout_status'])) {
            $provStatus = strtoupper((string) $resp['payout_status']);
        }

        $finalStatuses = ['PAID', 'COMPLETED', 'SETTLED', 'SUCCESS', 'SUCCEEDED'];

        if ($provStatus !== null && in_array($provStatus, $finalStatuses, true)) {
            DB::transaction(function () use ($w, $resp, $externalId) {
                $locked = Withdrawal::where('id', $this->withdrawalId)->lockForUpdate()->first();
                if (! $locked) {
                    return;
                }
                if ($locked->status === 'PAID') {
                    return;
                }

                $locked->payout_provider = config('wallet.gateway');
                $locked->payout_external_id = $externalId;
                $locked->status = 'PAID';
                $locked->processed_at = now();
                $locked->save();
                \App\Services\AuditService::log('system', null, 'withdraw.paid_instant', 'withdrawal', $locked->id, ['response' => $resp]);

                // queue notification to user that withdrawal was paid
                try {
                    app(NotificationOutboxService::class)->queue(
                        (int) $locked->user_id,
                        'saldo',
                        'Withdraw sudah dibayar',
                        'Pencairan ' . formatRupiah((float) $locked->amount) . ' telah berhasil diproses.',
                        ['withdrawal_id' => $locked->id, 'status' => 'paid'],
                        'withdrawal:paid:' . $locked->id
                    );
                } catch (\Throwable $e) {
                    \App\Services\AuditService::log('system', null, 'notify.outbox_error', 'withdrawal', $locked->id, ['error' => $e->getMessage()]);
                }

                // finalize reserved funds in wallet
                try {
                    app(\App\Services\WalletService::class)->finalizeWithdraw($locked->wallet_id, (float) $locked->amount, ['withdrawal_id' => $locked->id]);
                } catch (\Throwable $e) {
                    \App\Services\AuditService::log('system', null, 'withdraw.finalize_error', 'withdrawal', $locked->id, ['error' => $e->getMessage()]);
                }

                if (! empty($locked->meta['seller_withdrawal_id'])) {
                    try {
                        $seller = \App\Models\SellerWithdrawal::find($locked->meta['seller_withdrawal_id']);
                        if ($seller) {
                            app(\App\Services\SellerWalletService::class)->markWithdrawalPaidByPayout($seller);
                        }
                    } catch (\Throwable $e) {
                        \App\Services\AuditService::log('system', null, 'reconcile.seller_mark_error', 'withdrawal', $locked->id, ['error' => $e->getMessage()]);
                    }
                }
            });

            return;
        }

        // Otherwise persist as initiated
        DB::transaction(function () use ($w, $resp, $externalId) {
            $w = Withdrawal::where('id', $this->withdrawalId)->lockForUpdate()->first();
            if (! $w) {
                return;
            }

            $w->payout_provider = config('wallet.gateway');
            $w->payout_external_id = $externalId;
            $w->status = 'PAYOUT_INITIATED';
            $w->save();
            \App\Services\AuditService::log('system', null, 'withdraw.payout_initiated', 'withdrawal', $w->id, ['response' => $resp]);

            try {
                app(NotificationOutboxService::class)->queue(
                    (int) $w->user_id,
                    'saldo',
                    'Withdraw diproses',
                    'Permintaan pencairan Anda sebesar ' . formatRupiah((float) $w->amount) . ' sedang diproses.',
                    ['withdrawal_id' => $w->id, 'status' => 'initiated'],
                    'withdrawal:init:' . $w->id
                );
            } catch (\Throwable $e) {
                \App\Services\AuditService::log('system', null, 'notify.outbox_error', 'withdrawal', $w->id, ['error' => $e->getMessage()]);
            }
        });
    }
}
