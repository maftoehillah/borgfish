<?php

return [
    // Mode: SIMULATION => internal DB-only representation
    // REAL => interact with external payment gateways for payouts/charges
    'mode' => env('WALLET_MODE', 'SIMULATION'),
    // Default gateway adapter key (midtrans | xendit)
    'gateway' => env('PAYMENT_GATEWAY', 'midtrans'),
    // Midtrans settings (used when gateway=midtrans)
    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'base_url' => env('MIDTRANS_BASE_URL', 'https://api.midtrans.com'),
        // Payout/disbursement endpoint (adjust to actual vendor path)
        'payout_url' => env('MIDTRANS_PAYOUT_URL', 'https://api.midtrans.com/v1/payouts'),
    ],

    // Xendit settings (used when gateway=xendit)
    'xendit' => [
        'secret' => env('XENDIT_SECRET'),
        'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
        'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),
        'payout_url' => env('XENDIT_PAYOUT_URL', 'https://api.xendit.co/v2/payouts'),
    ],
];
