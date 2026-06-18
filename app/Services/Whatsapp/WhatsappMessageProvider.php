<?php

namespace App\Services\Whatsapp;

interface WhatsappMessageProvider
{
    public function name(): string;

    public function sendMessage(string $phone, string $message): WhatsappSendResult;
}
