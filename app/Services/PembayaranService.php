<?php

namespace App\Services;

use App\Models\PaymentAttempt;
use App\Models\Transaksi;
use App\Services\PaymentGateway\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PembayaranService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly OrderCodeService $codes,
        private readonly SystemSettingService $settings,
    ) {
    }

    public function availableMethods(): array
    {
        return $this->gateway->availableMethods();
    }

    public function defaultMethod(?array $methods = null): string
    {
        $methods ??= $this->availableMethods();

        foreach ([
            $this->settings->get('default_payment_method'),
            config('tripay.default_method', 'QRIS'),
            'QRIS',
            array_key_first($methods),
        ] as $candidate) {
            $resolved = $this->resolveAvailableMethodCode($candidate, $methods);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return 'QRIS';
    }

    public function resolvePaymentMethod(mixed $requestedMethod, ?array $methods = null): string
    {
        $methods ??= $this->availableMethods();
        $resolved = $this->resolveAvailableMethodCode($requestedMethod, $methods);

        return $resolved ?? $this->defaultMethod($methods);
    }

    public function createAttempt(Transaksi $order, string $methodCode): array
    {
        return DB::transaction(function () use ($order, $methodCode): array {
            $lockedOrder = Transaksi::query()
                ->with(['ikan', 'pemenang'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->isLunas()) {
                throw new \RuntimeException('Pesanan ini sudah dibayar.');
            }

            if ($lockedOrder->isKadaluarsa()) {
                app(LelangService::class)->handleExpiredTransaction($lockedOrder, 'payment_attempt');
                throw new \RuntimeException('Batas waktu pembayaran untuk pesanan ini sudah habis.');
            }

            if ($lockedOrder->order_code === null) {
                $lockedOrder->order_code = $this->codes->nextOrderCode();
                $lockedOrder->save();
            }

            /** @var PaymentAttempt|null $previous */
            $previous = $lockedOrder->paymentAttempts()
                ->where('status_code', 'pending')
                ->latest('id')
                ->first();

            if ($previous && $previous->checkout_url && $previous->checkout_expires_at && now()->lt($previous->checkout_expires_at)) {
                return [
                    'payment_id' => $previous->id,
                    'payment_code' => $previous->payment_code,
                    'checkout_url' => $previous->checkout_url,
                    'payment_method_code' => $previous->payment_method_code,
                    'payment_method_name' => $previous->payment_method_name,
                    'provider_transaction_id' => $previous->provider_transaction_id,
                    'status_code' => $previous->status_code,
                ];
            }

            $payment = new PaymentAttempt([
                'payment_code' => $this->codes->nextPaymentCode(),
                'provider' => $this->gateway->name(),
                'ikan_id' => $lockedOrder->ikan_id,
                'transaksi_id' => $lockedOrder->id,
                'bidder_id' => $lockedOrder->pemenang_id,
                'rank' => (int) ($lockedOrder->winner_rank ?? 1),
                'amount_due' => $lockedOrder->totalTagihan(),
                'status' => 'menunggu_pembayaran',
                'status_code' => 'pending',
                'bayar_sebelum' => $lockedOrder->bayar_sebelum,
                'assigned_at' => now(),
                'idempotency_key' => (string) Str::uuid(),
                'retry_of_payment_id' => $lockedOrder->paymentAttempts()->latest('id')->value('id'),
            ]);

            $payment->save();

            $response = $this->gateway->createPayment([
                'method' => $methodCode,
                'merchant_ref' => $payment->payment_code,
                'amount' => (int) round($lockedOrder->totalTagihan()),
                'customer_name' => (string) $lockedOrder->pemenang->name,
                'customer_email' => (string) $lockedOrder->pemenang->email,
                'customer_phone' => (string) $lockedOrder->pemenang->whatsapp_number,
                'callback_url' => config('tripay.callback_url') ?: route('tripay.callback'),
                'return_url' => route('pembayaran.selesai', ['transaksi' => $lockedOrder]),
                'expired_time' => $lockedOrder->bayar_sebelum?->timestamp,
                'order_items' => $this->buildOrderItems($lockedOrder),
            ]);

            $payment->payment_provider_ref = $response['merchant_ref'] ?? $payment->payment_code;
            $payment->provider_transaction_id = $response['provider_transaction_id'] ?? null;
            $payment->provider_status = $response['provider_status'] ?? 'UNPAID';
            $payment->payment_method_code = $response['payment_method_code'] ?? $methodCode;
            $payment->payment_method_name = $response['payment_method_name'] ?? ($this->availableMethods()[$methodCode] ?? $methodCode);
            $payment->checkout_url = $response['checkout_url'] ?? null;
            $payment->checkout_expires_at = $response['expires_at'] ?? $lockedOrder->bayar_sebelum;
            $payment->request_payload = $response['request_payload'] ?? null;
            $payment->markPending($this->gateway->name(), $payment->payment_method_code, $payment->payment_method_name);
            $payment->save();

            $lockedOrder->payment_status = 'pending';
            $lockedOrder->save();

            AuditService::log('user', (int) ($lockedOrder->pemenang_id ?? 0), 'payment.attempt_created', 'payment_attempts', (int) $payment->id, [
                'order_code' => $lockedOrder->order_code,
                'payment_code' => $payment->payment_code,
                'provider_transaction_id' => $payment->provider_transaction_id,
                'method' => $payment->payment_method_code,
            ]);

            return [
                'payment_id' => $payment->id,
                'payment_code' => $payment->payment_code,
                'checkout_url' => $payment->checkout_url,
                'payment_method_code' => $payment->payment_method_code,
                'payment_method_name' => $payment->payment_method_name,
                'provider_transaction_id' => $payment->provider_transaction_id,
                'status_code' => $payment->status_code,
            ];
        }, 3);
    }

    public function handleCallback(Request $request): array
    {
        $rawBody = $request->getContent();
        $headers = $request->headers->all();

        if (! $this->gateway->verifyCallback($rawBody, $headers)) {
            throw new \RuntimeException('Signature callback TriPay tidak valid.');
        }

        $callback = $this->gateway->parseCallback($rawBody, $headers);

        return DB::transaction(function () use ($callback, $rawBody): array {
            $payment = PaymentAttempt::query()
                ->with('transaksi.ikan')
                ->where(function ($query) use ($callback): void {
                    $query->where('payment_code', $callback['merchant_ref']);

                    if (! empty($callback['provider_transaction_id'])) {
                        $query->orWhere('provider_transaction_id', $callback['provider_transaction_id']);
                    }
                })
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw new \RuntimeException('Payment record tidak ditemukan untuk callback ini.');
            }

            $order = Transaksi::query()
                ->with(['ikan', 'pemenang'])
                ->lockForUpdate()
                ->findOrFail($payment->transaksi_id);

            $callbackIdempotency = sha1(implode('|', [
                (string) $callback['provider_transaction_id'],
                (string) $callback['status_code'],
                (string) ($callback['payload']['paid_at'] ?? ''),
            ]));

            if ($payment->callback_idempotency_key === $callbackIdempotency) {
                return ['status' => 'ok', 'idempotent' => true];
            }

            $payment->callback_signature = $callback['callback_signature'];
            $payment->callback_idempotency_key = $callbackIdempotency;
            $payment->callback_processed_at = now();
            $payment->callback_payload = $callback['payload'];
            $payment->provider_transaction_id = $callback['provider_transaction_id'] ?: $payment->provider_transaction_id;
            $payment->provider_status = $callback['provider_status'];
            $payment->payment_method_code = $callback['payment_method_code'] ?: $payment->payment_method_code;
            $payment->payment_method_name = $callback['payment_method_name'] ?: $payment->payment_method_name;

            return $this->applyProviderStatus(
                payment: $payment,
                order: $order,
                providerUpdate: $callback,
                source: 'callback',
                sourcePayload: [
                    'raw_body' => $rawBody,
                    'callback_event' => $callback['callback_event'] ?? null,
                ],
            );
        }, 3);
    }

    public function refreshPendingAttempt(Transaksi $order): array
    {
        $payment = $order->paymentAttempts()
            ->where('status_code', 'pending')
            ->latest('id')
            ->first();

        if (! $payment) {
            return ['status' => 'skipped', 'reason' => 'no_pending_payment'];
        }

        return $this->refreshPaymentAttempt($payment);
    }

    public function refreshPaymentAttempt(PaymentAttempt $payment): array
    {
        if ((string) $payment->status_code !== 'pending') {
            return ['status' => 'skipped', 'reason' => 'payment_not_pending'];
        }

        if (! $payment->provider_transaction_id) {
            return ['status' => 'skipped', 'reason' => 'provider_reference_missing'];
        }

        $reconcileAfterSeconds = (int) config('tripay.reconcile_pending_after_seconds', 90);
        $referenceTime = $payment->callback_processed_at
            ?? $payment->assigned_at
            ?? $payment->created_at;

        if ($referenceTime && $reconcileAfterSeconds > 0 && now()->diffInSeconds($referenceTime) < $reconcileAfterSeconds) {
            return ['status' => 'skipped', 'reason' => 'cooldown_active'];
        }

        $providerUpdate = $this->gateway->fetchPayment((string) $payment->provider_transaction_id);

        return DB::transaction(function () use ($payment, $providerUpdate): array {
            $lockedPayment = PaymentAttempt::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            $order = Transaksi::query()
                ->with(['ikan', 'pemenang'])
                ->lockForUpdate()
                ->findOrFail($lockedPayment->transaksi_id);

            return $this->applyProviderStatus(
                payment: $lockedPayment,
                order: $order,
                providerUpdate: $providerUpdate,
                source: 'reconcile',
            );
        }, 3);
    }

    private function buildOrderItems(Transaksi $order): array
    {
        $items = [[
            'sku' => $order->order_code ?: 'LOT-' . $order->ikan_id,
            'name' => mb_substr((string) ($order->ikan?->nama_ikan ?? 'Lot Lelang Borgfish'), 0, 120),
            'price' => (int) round((float) $order->harga_final),
            'quantity' => 1,
        ]];

        return $items;
    }

    private function applyProviderStatus(
        PaymentAttempt $payment,
        Transaksi $order,
        array $providerUpdate,
        string $source,
        array $sourcePayload = [],
    ): array {
        $statusCode = (string) ($providerUpdate['status_code'] ?? 'pending');

        $payment->provider_transaction_id = $providerUpdate['provider_transaction_id'] ?: $payment->provider_transaction_id;
        $payment->provider_status = $providerUpdate['provider_status'] ?? $payment->provider_status;
        $payment->payment_method_code = $providerUpdate['payment_method_code'] ?: $payment->payment_method_code;
        $payment->payment_method_name = $providerUpdate['payment_method_name'] ?: $payment->payment_method_name;
        $payment->checkout_url = $providerUpdate['checkout_url'] ?: $payment->checkout_url;

        if ($source === 'reconcile' && ($providerUpdate['payload'] ?? null) !== null) {
            $payment->callback_processed_at = now();
        }

        $stateChanged = false;

        if ($statusCode === 'paid' && (string) $payment->status_code !== 'paid') {
            $payment->markPaid($providerUpdate['provider_status'] ?? null);
            $order->markPaid($payment->payment_method_code ?: $this->gateway->name());
            $order->save();
            $payment->save();

            app(TransaksiFulfillmentService::class)->markPaid($order, $payment->provider_transaction_id);
            app(LelangService::class)->markTransactionAsPaid($order, $payment->provider_transaction_id);
            $stateChanged = true;
        } elseif ($statusCode === 'expired' && (string) $payment->status_code !== 'expired') {
            $payment->markExpired($providerUpdate['provider_status'] ?? null);
            $payment->save();

            $order->payment_status = 'expired';
            $order->save();

            app(LelangService::class)->handleExpiredTransaction($order, $source);
            $stateChanged = true;
        } elseif ($statusCode === 'failed' && (string) $payment->status_code !== 'failed') {
            $payment->markFailed($providerUpdate['provider_status'] ?? null);
            $payment->save();

            $order->payment_status = 'failed';
            $order->save();
            $stateChanged = true;
        } elseif ($statusCode === 'cancelled' && (string) $payment->status_code !== 'cancelled') {
            $payment->markCancelled($providerUpdate['provider_status'] ?? null);
            $payment->save();

            $order->payment_status = 'cancelled';
            $order->save();
            $stateChanged = true;
        } elseif ($statusCode === 'refunded' && (string) $payment->status_code !== 'refunded') {
            $payment->markRefunded($providerUpdate['provider_status'] ?? null);
            $payment->save();

            $order->payment_status = 'refunded';
            $order->save();
            $stateChanged = true;
        } else {
            $payment->save();
        }

        AuditService::log('system', null, 'payment.' . $source . '_processed', 'payment_attempts', (int) $payment->id, array_merge([
            'payment_code' => $payment->payment_code,
            'provider_transaction_id' => $payment->provider_transaction_id,
            'status_code' => $statusCode,
            'provider_status' => $payment->provider_status,
            'state_changed' => $stateChanged,
        ], $sourcePayload, [
            'payload' => $providerUpdate['payload'] ?? null,
        ]));

        if ($stateChanged) {
            app(NotificationOutboxService::class)->processPending(50);
        }

        return [
            'status' => 'ok',
            'idempotent' => ! $stateChanged,
            'payment_status' => $payment->status_code,
            'order_status' => $order->payment_status,
        ];
    }

    private function resolveAvailableMethodCode(mixed $candidate, array $methods): ?string
    {
        $candidate = strtoupper(trim((string) $candidate));

        if ($candidate === '') {
            return null;
        }

        foreach (array_keys($methods) as $code) {
            if (strtoupper((string) $code) === $candidate) {
                return (string) $code;
            }
        }

        return null;
    }
}
