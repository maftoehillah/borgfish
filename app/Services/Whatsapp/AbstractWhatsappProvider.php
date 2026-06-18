<?php

namespace App\Services\Whatsapp;

abstract class AbstractWhatsappProvider implements WhatsappMessageProvider
{
    protected function ensureProviderAccepted(?array $json, bool $httpOk, string $provider): void
    {
        if (! $httpOk) {
            throw new \RuntimeException("{$provider} menolak request OTP.");
        }

        if ($json === null) {
            return;
        }

        $status = $json['status'] ?? $json['success'] ?? null;
        if ($status === false || $status === 'false' || $status === 'error') {
            $message = $this->stringValue($json['message'] ?? $json['reason'] ?? "{$provider} gagal mengirim OTP.");
            throw new \RuntimeException($message);
        }
    }

    protected function providerReference(?array $response): ?string
    {
        if ($response === null) {
            return null;
        }

        $reference = $response['id']
            ?? $response['data']['id']
            ?? $response['detail'][0]['id']
            ?? $response['process']
            ?? null;

        return $this->stringValue($reference);
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? null : mb_substr($encoded, 0, 191);
    }
}
