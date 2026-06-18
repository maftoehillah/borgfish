<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\Http;

class WablasWhatsappProvider extends AbstractWhatsappProvider
{
    public function name(): string
    {
        return 'wablas';
    }

    public function sendMessage(string $phone, string $message): WhatsappSendResult
    {
        $endpoint = trim((string) config('whatsapp.wablas.endpoint'));
        $token = trim((string) config('whatsapp.wablas.token'));
        $secretKey = trim((string) config('whatsapp.wablas.secret_key'));

        if ($endpoint === '' || $token === '' || $secretKey === '') {
            throw new \RuntimeException('Konfigurasi Wablas belum lengkap.');
        }

        $authToken = $token . '.' . $secretKey;
        $response = Http::timeout(15)
            ->acceptJson()
            ->withHeaders(['Authorization' => $authToken])
            ->get($endpoint, [
                'token' => $authToken,
                'phone' => $phone,
                'message' => $message,
                'flag' => 'instant',
            ]);

        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [];

        $this->ensureProviderAccepted($payload ?: null, $response->successful(), 'Wablas');

        return new WhatsappSendResult(
            provider: $this->name(),
            providerReference: $this->providerReference($payload),
            payload: $payload,
        );
    }
}
