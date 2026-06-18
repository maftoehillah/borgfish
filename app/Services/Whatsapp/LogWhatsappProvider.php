<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\Log;

class LogWhatsappProvider implements WhatsappMessageProvider
{
    public function name(): string
    {
        return 'log';
    }

    public function sendMessage(string $phone, string $message): WhatsappSendResult
    {
        if (! (bool) config('whatsapp.show_dev_otp', false)) {
            throw new \RuntimeException('WHATSAPP_DRIVER=log hanya boleh dipakai jika WHATSAPP_SHOW_DEV_OTP=true. Isi provider WhatsApp asli untuk pengiriman OTP.');
        }

        Log::info('whatsapp.otp_log_driver', [
            'to' => $phone,
            'message' => $message,
        ]);

        return new WhatsappSendResult(provider: $this->name());
    }
}
