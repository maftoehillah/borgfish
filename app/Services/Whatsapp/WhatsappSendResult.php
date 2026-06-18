<?php

namespace App\Services\Whatsapp;

class WhatsappSendResult
{
    public function __construct(
        public readonly string $provider,
        public readonly ?string $providerReference = null,
        public readonly array $payload = [],
    ) {
    }
}
