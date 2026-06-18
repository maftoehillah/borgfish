<?php

namespace App\Services\PaymentGateway;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class TriPayGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'tripay';
    }

    public function availableMethods(): array
    {
        $fallbackMethods = config('tripay.methods', []);

        if (! (bool) config('tripay.sync_methods', true)) {
            return $fallbackMethods;
        }

        $apiKey = trim((string) config('tripay.api_key'));

        if ($apiKey === '') {
            return $fallbackMethods;
        }

        $cacheKey = 'tripay:payment-methods:' . config('tripay.environment', 'sandbox');
        $ttl = now()->addMinutes((int) config('tripay.method_cache_minutes', 30));

        return Cache::remember($cacheKey, $ttl, function () use ($apiKey, $fallbackMethods): array {
            $response = Http::timeout(20)
                ->acceptJson()
                ->withToken($apiKey)
                ->get($this->baseUrl() . '/merchant/payment-channel');

            $json = $response->json();

            if (! $response->successful() || ! ($json['success'] ?? false)) {
                return $fallbackMethods;
            }

            $methods = collect($json['data'] ?? [])
                ->filter(fn (array $channel): bool => (bool) ($channel['active'] ?? false))
                ->mapWithKeys(function (array $channel): array {
                    $code = (string) ($channel['code'] ?? '');
                    $name = trim((string) ($channel['name'] ?? $code));

                    if ($code === '' || $name === '') {
                        return [];
                    }

                    return [$code => $name];
                })
                ->all();

            return $methods !== [] ? $methods : $fallbackMethods;
        });
    }

    public function createPayment(array $payload): array
    {
        $apiKey = trim((string) config('tripay.api_key'));
        $privateKey = trim((string) config('tripay.private_key'));
        $merchantCode = trim((string) config('tripay.merchant_code'));
        $method = (string) ($payload['method'] ?? config('tripay.default_method', 'QRIS'));
        $merchantRef = (string) $payload['merchant_ref'];
        $amount = (int) round((float) $payload['amount']);

        if ($apiKey === '' || $privateKey === '' || $merchantCode === '') {
            throw new \RuntimeException('Konfigurasi TriPay belum lengkap. Isi API key, private key, dan merchant code.');
        }

        $body = [
            'method' => $method,
            'merchant_ref' => $merchantRef,
            'amount' => $amount,
            'customer_name' => (string) $payload['customer_name'],
            'customer_email' => (string) $payload['customer_email'],
            'customer_phone' => (string) ($payload['customer_phone'] ?? ''),
            'order_items' => $payload['order_items'] ?? [],
            'callback_url' => (string) ($payload['callback_url'] ?? config('tripay.callback_url')),
            'return_url' => (string) ($payload['return_url'] ?? config('tripay.return_url')),
            'expired_time' => (int) ($payload['expired_time'] ?? now()->addMinutes((int) config('marketplace.payment_deadline_minutes', 30))->timestamp),
            'signature' => hash_hmac('sha256', $merchantCode . $merchantRef . $amount, $privateKey),
        ];

        $response = Http::timeout(20)
            ->asForm()
            ->acceptJson()
            ->withToken($apiKey)
            ->post($this->baseUrl() . '/transaction/create', $body);

        $json = $response->json();

        if (! $response->successful() || ! ($json['success'] ?? false)) {
            throw new \RuntimeException((string) ($json['message'] ?? 'TriPay gagal membuat transaksi pembayaran.'));
        }

        $data = $json['data'] ?? [];

        return [
            'provider_transaction_id' => (string) ($data['reference'] ?? ''),
            'merchant_ref' => (string) ($data['merchant_ref'] ?? $merchantRef),
            'checkout_url' => (string) ($data['checkout_url'] ?? ''),
            'payment_method_code' => (string) ($data['payment_method_code'] ?? $method),
            'payment_method_name' => (string) ($data['payment_method'] ?? ($this->availableMethods()[$method] ?? $method)),
            'provider_status' => (string) ($data['status'] ?? 'UNPAID'),
            'expires_at' => isset($data['expired_time']) ? now()->createFromTimestamp((int) $data['expired_time']) : null,
            'request_payload' => $body,
            'response_payload' => $json,
        ];
    }

    public function verifyCallback(string $rawBody, array $headers): bool
    {
        $signature = (string) ($headers['x-callback-signature'][0] ?? $headers['X-Callback-Signature'][0] ?? '');
        $event = (string) ($headers['x-callback-event'][0] ?? $headers['X-Callback-Event'][0] ?? '');
        $privateKey = trim((string) config('tripay.private_key'));

        if ($signature === '' || $event !== 'payment_status' || $privateKey === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $privateKey);

        return hash_equals($expected, $signature);
    }

    public function parseCallback(string $rawBody, array $headers): array
    {
        $payload = json_decode($rawBody, true, flags: JSON_THROW_ON_ERROR);
        $providerStatus = strtoupper((string) ($payload['status'] ?? ''));

        return [
            'provider_transaction_id' => (string) ($payload['reference'] ?? ''),
            'merchant_ref' => (string) ($payload['merchant_ref'] ?? ''),
            'payment_method_code' => (string) ($payload['payment_method_code'] ?? ''),
            'payment_method_name' => (string) ($payload['payment_method'] ?? ''),
            'provider_status' => $providerStatus,
            'status_code' => $this->normalizeStatus($providerStatus),
            'callback_signature' => (string) ($headers['x-callback-signature'][0] ?? $headers['X-Callback-Signature'][0] ?? ''),
            'callback_event' => (string) ($headers['x-callback-event'][0] ?? $headers['X-Callback-Event'][0] ?? ''),
            'payload' => $payload,
            'paid_at' => isset($payload['paid_at']) && $payload['paid_at'] ? now()->createFromTimestamp((int) $payload['paid_at']) : null,
        ];
    }

    public function fetchPayment(string $providerTransactionId): array
    {
        $apiKey = trim((string) config('tripay.api_key'));

        if ($apiKey === '') {
            throw new \RuntimeException('API key TriPay belum diatur.');
        }

        $response = Http::timeout(20)
            ->acceptJson()
            ->withToken($apiKey)
            ->get($this->baseUrl() . '/transaction/detail', [
                'reference' => $providerTransactionId,
            ]);

        $json = $response->json();

        if (! $response->successful() || ! ($json['success'] ?? false)) {
            throw new \RuntimeException((string) ($json['message'] ?? 'Gagal mengambil status pembayaran TriPay.'));
        }

        $data = $json['data'] ?? [];
        $providerStatus = strtoupper((string) ($data['status'] ?? ''));

        return [
            'provider_transaction_id' => (string) ($data['reference'] ?? $providerTransactionId),
            'merchant_ref' => (string) ($data['merchant_ref'] ?? ''),
            'provider_status' => $providerStatus,
            'status_code' => $this->normalizeStatus($providerStatus),
            'payment_method_code' => (string) ($data['payment_method_code'] ?? ''),
            'payment_method_name' => (string) ($data['payment_method'] ?? ''),
            'checkout_url' => (string) ($data['checkout_url'] ?? ''),
            'paid_at' => isset($data['paid_at']) && $data['paid_at'] ? now()->createFromTimestamp((int) $data['paid_at']) : null,
            'payload' => $json,
        ];
    }

    private function baseUrl(): string
    {
        return (string) (config('tripay.environment') === 'live'
            ? config('tripay.live_base_url')
            : config('tripay.sandbox_base_url'));
    }

    private function normalizeStatus(string $providerStatus): string
    {
        return match ($providerStatus) {
            'PAID' => 'paid',
            'FAILED' => 'failed',
            'EXPIRED' => 'expired',
            'REFUND', 'REFUNDED' => 'refunded',
            'CANCELLED', 'CANCELED' => 'cancelled',
            default => 'pending',
        };
    }
}
