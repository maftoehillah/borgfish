<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\Http;

class FonnteWhatsappProvider extends AbstractWhatsappProvider
{
    public function name(): string
    {
        return 'fonnte';
    }

    public function sendMessage(string $phone, string $message): WhatsappSendResult
    {
        $endpoint = trim((string) config('whatsapp.fonnte.endpoint'));
        $token = trim((string) config('whatsapp.fonnte.token'));

        if ($endpoint === '' || $token === '') {
            throw new \RuntimeException('Konfigurasi Fonnte belum lengkap. Isi FONNTE_TOKEN di .env.');
        }

        $response = Http::timeout(15)
            ->asForm()
            ->acceptJson()
            ->withHeaders(['Authorization' => $token])
            ->post($endpoint, [
                'target' => $phone,
                'message' => $message,
            ]);

        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [];

        $this->ensureProviderAccepted($payload ?: null, $response->successful(), 'Fonnte');

        return new WhatsappSendResult(
            provider: $this->name(),
            providerReference: $this->providerReference($payload),
            payload: $payload,
        );
    }
}
