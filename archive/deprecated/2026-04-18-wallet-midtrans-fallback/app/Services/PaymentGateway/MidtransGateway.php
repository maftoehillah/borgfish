<?php

namespace App\Services\PaymentGateway;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MidtransGateway implements PaymentGatewayInterface
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

        // For REAL mode: implement charge creation if needed (Snap / Core API)
        // TODO: call Midtrans charge endpoint using server key
        return [
            'error' => 'not_implemented',
        ];
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

        $serverKey = config('wallet.midtrans.server_key');
        $url = config('wallet.midtrans.payout_url');

        if (empty($serverKey) || empty($url)) {
            return ['error' => 'midtrans_not_configured'];
        }

        $idempotency = $data['idempotency_key'] ?? $data['reference'] ?? null;

        $http = Http::withBasicAuth($serverKey, '');
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

        $serverKey = config('wallet.midtrans.server_key');
        $payload = $request->all();

        // Preferred: Midtrans sends 'signature_key' in payload: sha512(order_id+status_code+gross_amount+server_key)
        if (isset($payload['signature_key'])) {
            $expected = hash('sha512', ($payload['order_id'] ?? '') . ($payload['status_code'] ?? '') . ($payload['gross_amount'] ?? '') . $serverKey);
            return hash_equals($expected, (string) ($payload['signature_key'] ?? ''));
        }

        // Fallback: header signature (custom mapping) - compute over raw body + server_key
        $header = $request->header('X-Midtrans-Signature') ?? $request->header('X-Signature');
        if ($header) {
            $raw = $request->getContent();
            $expected = hash('sha512', $raw . $serverKey);
            return hash_equals($expected, (string) $header);
        }

        return false;
    }

    public function handleWebhookPayload(array $payload): array
    {
        // Map common midtrans fields to normalized event
        if (isset($payload['transaction_status'])) {
            return ['event' => 'payment.' . $payload['transaction_status'], 'data' => $payload];
        }

        // Disbursement / payout style notifications
        if (isset($payload['status']) && (isset($payload['id']) || isset($payload['payout_id']))) {
            return ['event' => 'payout.' . $payload['status'], 'data' => $payload];
        }

        if (isset($payload['event']) && isset($payload['data'])) {
            return ['event' => $payload['event'], 'data' => $payload['data']];
        }

        return ['event' => 'unknown', 'data' => $payload];
    }

    public function fetchPayoutStatus(string $externalId): array
    {
        if (config('wallet.mode') === 'SIMULATION') {
            return ['status' => 'PAID', 'raw' => ['id' => $externalId]];
        }

        $serverKey = config('wallet.midtrans.server_key');
        $base = config('wallet.midtrans.payout_url');

        if (empty($serverKey) || empty($base)) {
            return ['error' => 'midtrans_not_configured'];
        }

        $url = rtrim($base, '/') . '/' . rawurlencode($externalId);
        $resp = Http::withBasicAuth($serverKey, '')->get($url);

        if ($resp->successful()) {
            $json = $resp->json();
            // try to normalize common fields
            $status = $json['status'] ?? ($json['transaction_status'] ?? null);
            if (! $status && isset($json['payout_status'])) {
                $status = $json['payout_status'];
            }
            return ['status' => strtoupper((string) ($status ?? 'UNKNOWN')), 'raw' => $json];
        }

        return ['error' => 'http_error', 'status' => $resp->status(), 'body' => $resp->body()];
    }
}
