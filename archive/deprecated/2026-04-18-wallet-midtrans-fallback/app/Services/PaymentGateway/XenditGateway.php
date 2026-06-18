<?php

namespace App\Services\PaymentGateway;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class XenditGateway implements PaymentGatewayInterface
{
    public function createCharge(array $data): array
    {
        if (config('wallet.mode') === 'SIMULATION') {
            return [
                'id' => 'sim-charge-'.(int)($data['id'] ?? time()),
                'status' => 'pending',
                'raw' => $data,
            ];
        }

        // TODO: implement Xendit charge if needed
        return ['error' => 'not_implemented'];
    }

    public function payout(array $data): array
    {
        if (config('wallet.mode') === 'SIMULATION') {
            return [
                'id' => 'sim-payout-'.(int)($data['id'] ?? time()),
                'status' => 'initiated',
                'raw' => $data,
            ];
        }

        $secret = config('wallet.xendit.secret');
        $url = config('wallet.xendit.payout_url');

        if (empty($secret) || empty($url)) {
            return ['error' => 'xendit_not_configured'];
        }

        $idempotency = $data['idempotency_key'] ?? $data['reference'] ?? null;
        $http = Http::withBasicAuth($secret, '');
        if ($idempotency) {
            $http = $http->withHeaders(['Idempotency-Key' => (string) $idempotency]);
        }

        $resp = $http->post($url, $data);

        if ($resp->successful()) {
            return $resp->json();
        }

        return ['error' => 'http_error', 'status' => $resp->status(), 'body' => $resp->body()];
    }

    public function verifySignature(Request $request): bool
    {
        if (config('wallet.mode') === 'SIMULATION') {
            return true;
        }

        // Preferred simple callback token
        $token = config('wallet.xendit.callback_token');
        if ($token && $request->header('X-Callback-Token') === $token) {
            return true;
        }

        // HMAC-SHA256 signature verification
        $sigHeader = $request->header('X-Signature-SHA256') ?? $request->header('X-Signature');
        $secret = config('wallet.xendit.secret');

        if ($sigHeader && $secret) {
            $raw = $request->getContent();
            $expected = hash_hmac('sha256', $raw, $secret);
            return hash_equals($expected, $sigHeader);
        }

        return false;
    }

    public function handleWebhookPayload(array $payload): array
    {
        // Normalize Xendit webhook payloads
        if (isset($payload['type'])) {
            return ['event' => $payload['type'], 'data' => $payload];
        }

        if (isset($payload['status']) && isset($payload['id'])) {
            return ['event' => 'payout.' . $payload['status'], 'data' => $payload];
        }

        return ['event' => 'unknown', 'data' => $payload];
    }

    public function fetchPayoutStatus(string $externalId): array
    {
        if (config('wallet.mode') === 'SIMULATION') {
            return ['status' => 'PAID', 'raw' => ['id' => $externalId]];
        }

        $secret = config('wallet.xendit.secret');
        $base = config('wallet.xendit.payout_url');

        if (empty($secret) || empty($base)) {
            return ['error' => 'xendit_not_configured'];
        }

        $url = rtrim($base, '/') . '/' . rawurlencode($externalId);
        $resp = Http::withBasicAuth($secret, '')->get($url);

        if ($resp->successful()) {
            $json = $resp->json();
            $status = $json['status'] ?? null;
            return ['status' => strtoupper((string) ($status ?? 'UNKNOWN')), 'raw' => $json];
        }

        return ['error' => 'http_error', 'status' => $resp->status(), 'body' => $resp->body()];
    }
}
