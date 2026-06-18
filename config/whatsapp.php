<?php

return [
    'driver' => env('WHATSAPP_DRIVER', 'fonnte'),
    'sender_name' => env('WHATSAPP_SENDER_NAME', 'Borgfish'),
    'fail_silently' => env('WHATSAPP_FAIL_SILENTLY', false),
    'show_dev_otp' => filter_var(env('WHATSAPP_SHOW_DEV_OTP', false), FILTER_VALIDATE_BOOL),
    'fonnte' => [
        'endpoint' => env('FONNTE_ENDPOINT', 'https://api.fonnte.com/send'),
        'token' => env('FONNTE_TOKEN'),
    ],
    'wablas' => [
        'endpoint' => env('WABLAS_ENDPOINT', 'https://wablas.com/api/send-message'),
        'token' => env('WABLAS_TOKEN'),
        'secret_key' => env('WABLAS_SECRET_KEY'),
    ],
    'generic' => [
        'endpoint' => env('WHATSAPP_ENDPOINT'),
        'token' => env('WHATSAPP_TOKEN'),
        'secret' => env('WHATSAPP_SECRET'),
        'auth_header' => env('WHATSAPP_AUTH_HEADER', 'Authorization'),
        'auth_scheme' => env('WHATSAPP_AUTH_SCHEME', 'Bearer'),
        'recipient_key' => env('WHATSAPP_RECIPIENT_KEY', 'to'),
        'message_key' => env('WHATSAPP_MESSAGE_KEY', 'message'),
    ],
];
