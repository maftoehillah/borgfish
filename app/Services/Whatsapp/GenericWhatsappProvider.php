<?php

namespace App\Services\Whatsapp;

use App\Services\SystemSettingService;
use Illuminate\Support\Facades\Http;

class GenericWhatsappProvider extends AbstractWhatsappProvider
{
    public function name(): string
    {
        return 'generic';
    }

    public function sendMessage(string $phone, string $message): WhatsappSendResult
    {
        $endpoint = trim((string) config('whatsapp.generic.endpoint'));
        $token = trim((string) config('whatsapp.generic.token'));

        if ($endpoint === '') {
            throw new \RuntimeException('Endpoint WhatsApp generic belum diatur.');
        }

        $headers = [];
        if ($token !== '') {
            $header = trim((string) config('whatsapp.generic.auth_header', 'Authorization')) ?: 'Authorization';
            $scheme = trim((string) config('whatsapp.generic.auth_scheme', 'Bearer'));
            $headers[$header] = $scheme === '' ? $token : $scheme . ' ' . $token;
        }

        $recipientKey = trim((string) config('whatsapp.generic.recipient_key', 'to')) ?: 'to';
        $messageKey = trim((string) config('whatsapp.generic.message_key', 'message')) ?: 'message';

        $response = Http::timeout(15)
            ->acceptJson()
            ->withHeaders($headers)
            ->post($endpoint, [
                $recipientKey => $phone,
                $messageKey => $message,
                'sender' => app(SystemSettingService::class)->whatsappSenderName(),
            ]);

        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [];

        $this->ensureProviderAccepted($payload ?: null, $response->successful(), 'WhatsApp generic');

        return new WhatsappSendResult(
            provider: $this->name(),
            providerReference: $this->providerReference($payload),
            payload: $payload,
        );
    }
}
