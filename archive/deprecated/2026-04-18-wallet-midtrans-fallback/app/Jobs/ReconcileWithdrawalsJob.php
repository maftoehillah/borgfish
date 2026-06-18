<?php

namespace App\Jobs;

use App\Models\Withdrawal;
use App\Services\PaymentGateway\PaymentGatewayInterface;
use App\Services\SellerWalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationOutboxService;

class ReconcileWithdrawalsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        /** @var PaymentGatewayInterface $gateway */
        $gateway = app(PaymentGatewayInterface::class);
        $sellerSvc = app(SellerWalletService::class);

        // Find withdrawals that were initiated with provider but not finalized
        Withdrawal::query()
            ->where('status', 'PAYOUT_INITIATED')
            ->whereNotNull('payout_external_id')
            ->chunkById(50, function ($rows) use ($gateway, $sellerSvc) {
                foreach ($rows as $w) {
                    try {
                        $extId = (string) $w->payout_external_id;
                        $res = $gateway->fetchPayoutStatus($extId);

                        if (isset($res['error'])) {
                            \App\Services\AuditService::log('system', null, 'reconcile.fetch_error', 'withdrawal', $w->id, $res);
                            continue;
                        }

                        $status = strtoupper((string) ($res['status'] ?? 'UNKNOWN'));

                        if (in_array($status, ['PAID', 'COMPLETED', 'SETTLED'], true)) {
                            DB::transaction(function () use ($w, $res, $sellerSvc) {
                                $locked = Withdrawal::whereKey($w->id)->lockForUpdate()->first();
                                if (! $locked) {
                                    return;
                                }
                                if ($locked->status === 'PAID') {
                                    return;
                                }

                                $locked->status = 'PAID';
                                $locked->processed_at = now();
                                $locked->save();
                                \App\Services\AuditService::log('system', null, 'reconcile.paid', 'withdrawal', $locked->id, ['provider' => $locked->payout_provider, 'raw' => $res]);

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

                                if (! empty($locked->meta['seller_withdrawal_id'])) {
                                    try {
                                        $seller = \App\Models\SellerWithdrawal::find($locked->meta['seller_withdrawal_id']);
                                        if ($seller) {
                                            $sellerSvc->markWithdrawalPaidByPayout($seller);
                                        }
                                    } catch (\Throwable $e) {
                                        \App\Services\AuditService::log('system', null, 'reconcile.seller_mark_error', 'withdrawal', $locked->id, ['error' => $e->getMessage()]);
                                    }
                                }
                            });
                            // For generic wallet withdrawals, ensure pending funds are finalized
                            DB::transaction(function () use ($w, $res, $sellerSvc) {
                                $locked = Withdrawal::whereKey($w->id)->lockForUpdate()->first();
                                if (! $locked) {
                                    return;
                                }
                                try {
                                    if (! empty($locked->wallet_id) && $locked->status === 'PAID') {
                                        app(\App\Services\WalletService::class)->finalizeWithdraw($locked->wallet_id, (float) $locked->amount, ['withdrawal_id' => $locked->id]);
                                    }
                                } catch (\Throwable $e) {
                                    \App\Services\AuditService::log('system', null, 'reconcile.finalize_error', 'withdrawal', $locked->id, ['error' => $e->getMessage()]);
                                }
                            });
                        } elseif (in_array($status, ['FAILED', 'CANCELLED', 'DECLINED'], true)) {
                            DB::transaction(function () use ($w, $res, $sellerSvc) {
                                $locked = Withdrawal::whereKey($w->id)->lockForUpdate()->first();
                                if (! $locked) {
                                    return;
                                }
                                if ($locked->status === 'FAILED') {
                                    return;
                                }

                                $locked->status = 'FAILED';
                                $locked->save();
                                \App\Services\AuditService::log('system', null, 'reconcile.failed', 'withdrawal', $locked->id, ['provider' => $locked->payout_provider, 'raw' => $res]);

                                try {
                                    app(NotificationOutboxService::class)->queue(
                                        (int) $locked->user_id,
                                        'saldo',
                                        'Withdraw gagal',
                                        'Pencairan ' . formatRupiah((float) $locked->amount) . ' gagal diproses. Dana telah dikembalikan ke saldo Anda.',
                                        ['withdrawal_id' => $locked->id, 'status' => 'failed'],
                                        'withdrawal:failed:' . $locked->id
                                    );
                                } catch (\Throwable $e) {
                                    \App\Services\AuditService::log('system', null, 'notify.outbox_error', 'withdrawal', $locked->id, ['error' => $e->getMessage()]);
                                }

                                if (! empty($locked->meta['seller_withdrawal_id'])) {
                                    try {
                                        $seller = \App\Models\SellerWithdrawal::find($locked->meta['seller_withdrawal_id']);
                                        if ($seller) {
                                            $sellerSvc->rejectWithdrawalByPayout($seller, 'Reconcile: provider failed');
                                        }
                                    } catch (\Throwable $e) {
                                        \App\Services\AuditService::log('system', null, 'reconcile.seller_reject_error', 'withdrawal', $locked->id, ['error' => $e->getMessage()]);
                                    }
                                }
                            });
                            // For generic wallet withdrawals, restore reserved funds
                            DB::transaction(function () use ($w, $res, $sellerSvc) {
                                $locked = Withdrawal::whereKey($w->id)->lockForUpdate()->first();
                                if (! $locked) {
                                    return;
                                }
                                try {
                                    if (! empty($locked->wallet_id)) {
                                        app(\App\Services\WalletService::class)->restoreReserved($locked->wallet_id, (float) $locked->amount, ['withdrawal_id' => $locked->id]);
                                    }
                                } catch (\Throwable $e) {
                                    \App\Services\AuditService::log('system', null, 'reconcile.restore_error', 'withdrawal', $locked->id, ['error' => $e->getMessage()]);
                                }
                            });
                        } else {
                            // still pending, record a heartbeat
                            \App\Services\AuditService::log('system', null, 'reconcile.pending', 'withdrawal', $w->id, ['status' => $status]);
                        }
                    } catch (\Throwable $e) {
                        \App\Services\AuditService::log('system', null, 'reconcile.exception', 'withdrawal', $w->id, ['error' => $e->getMessage()]);
                    }
                }
            });
    }
}
