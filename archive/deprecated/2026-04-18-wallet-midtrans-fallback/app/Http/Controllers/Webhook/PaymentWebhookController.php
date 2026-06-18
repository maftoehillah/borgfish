<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\PaymentGateway\PaymentGatewayInterface;
use App\Models\Withdrawal;
use App\Services\SellerWalletService;
use App\Models\SellerWithdrawal;
use App\Services\NotificationOutboxService;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Resolve configured gateway adapter and verify signature using it
        try {
            /** @var \App\Services\PaymentGateway\PaymentGatewayInterface $gateway */
            $gateway = app(\App\Services\PaymentGateway\PaymentGatewayInterface::class);

            if (! $gateway->verifySignature($request)) {
                return response('invalid signature', 403);
            }

            $payload = $request->all();
            $normalized = $gateway->handleWebhookPayload($payload);
            $event = $normalized['event'] ?? 'unknown';
            $data = $normalized['data'] ?? [];
        } catch (\Throwable $e) {
            Log::error('Webhook handling failed', ['err' => $e->getMessage()]);
            return response('error', 500);
        }

        // handle payout notifications
        if (str_starts_with($event, 'payout') || str_contains($event, 'disburse')) {
            $externalId = $data['id'] ?? $data['payout_id'] ?? ($data['external_id'] ?? null);
            $status = $data['status'] ?? ($data['transaction_status'] ?? null);

            if ($externalId) {
                $w = Withdrawal::where('payout_external_id', $externalId)->first();
                if ($w) {
                    $gatewayKey = config('wallet.gateway');

                    if (in_array($status, ['success','PAID','paid','COMPLETED','settlement'], true)) {
                        $w->status = 'PAID';
                        $w->processed_at = now();
                        $w->save();
                        \App\Services\AuditService::log('system', null, 'withdraw.paid.webhook', 'withdrawal', $w->id, ['payload' => $data]);

                        try {
                            app(NotificationOutboxService::class)->queue(
                                (int) $w->user_id,
                                'saldo',
                                'Withdraw sudah dibayar',
                                'Pencairan ' . formatRupiah((float) $w->amount) . ' telah berhasil diproses.',
                                ['withdrawal_id' => $w->id, 'status' => 'paid'],
                                'withdrawal:paid:' . $w->id
                            );
                        } catch (\Throwable $e) {
                            \App\Services\AuditService::log('system', null, 'notify.outbox_error', 'withdrawal', $w->id, ['error' => $e->getMessage()]);
                        }

                        // If this external withdrawal references a SellerWithdrawal, mark it paid
                        $meta = (array) ($w->meta ?? []);
                        if (isset($meta['seller_withdrawal_id'])) {
                            $sellerId = (int) $meta['seller_withdrawal_id'];
                            $sellerWithdrawal = SellerWithdrawal::find($sellerId);
                            if ($sellerWithdrawal) {
                                app(SellerWalletService::class)->markWithdrawalPaidByPayout($sellerWithdrawal, $w->payout_external_id ?? $externalId, json_encode($data));
                            }
                        }

                        return response('ok', 200);
                    }

                    if (in_array($status, ['failed','FAILED','error'], true)) {
                        $w->status = 'FAILED';
                        $w->save();
                        \App\Services\AuditService::log('system', null, 'withdraw.failed.webhook', 'withdrawal', $w->id, ['payload' => $data]);

                        try {
                            app(NotificationOutboxService::class)->queue(
                                (int) $w->user_id,
                                'saldo',
                                'Withdraw gagal',
                                'Pencairan ' . formatRupiah((float) $w->amount) . ' gagal diproses. Dana telah dikembalikan ke saldo Anda.',
                                ['withdrawal_id' => $w->id, 'status' => 'failed'],
                                'withdrawal:failed:' . $w->id
                            );
                        } catch (\Throwable $e) {
                            \App\Services\AuditService::log('system', null, 'notify.outbox_error', 'withdrawal', $w->id, ['error' => $e->getMessage()]);
                        }
                        $meta = (array) ($w->meta ?? []);
                        if (isset($meta['seller_withdrawal_id'])) {
                            $sellerId = (int) $meta['seller_withdrawal_id'];
                            $sellerWithdrawal = SellerWithdrawal::find($sellerId);
                            if ($sellerWithdrawal) {
                                app(SellerWalletService::class)->rejectWithdrawalByPayout($sellerWithdrawal, json_encode($data));
                            }
                        }

                        return response('ok', 200);
                    }
                }
            }
        }

        // default
        Log::info('Unhandled payment webhook', ['provider' => config('wallet.gateway'), 'event' => $event]);
        return response('ignored', 200);
    }
}
