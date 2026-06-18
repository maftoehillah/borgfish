<?php

return [
    'driver' => env('PAYMENT_GATEWAY', 'tripay'),
    'environment' => env('TRIPAY_ENVIRONMENT', 'sandbox'),
    'sync_methods' => filter_var(env('TRIPAY_SYNC_METHODS', true), FILTER_VALIDATE_BOOL),
    'method_cache_minutes' => max(1, (int) env('TRIPAY_METHOD_CACHE_MINUTES', 30)),
    'reconcile_pending_after_seconds' => max(0, (int) env('TRIPAY_RECONCILE_PENDING_AFTER_SECONDS', 90)),
    'api_key' => env('TRIPAY_API_KEY')
        ?: (in_array(strtolower(env('TRIPAY_ENVIRONMENT', 'sandbox')), ['live', 'production'])
            ? env('TRIPAY_PRODUCTION_API_KEY')
            : env('TRIPAY_SANDBOX_API_KEY')),
    'private_key' => env('TRIPAY_PRIVATE_KEY')
        ?: (in_array(strtolower(env('TRIPAY_ENVIRONMENT', 'sandbox')), ['live', 'production'])
            ? env('TRIPAY_PRODUCTION_PRIVATE_KEY')
            : env('TRIPAY_SANDBOX_PRIVATE_KEY')),
    'merchant_code' => env('TRIPAY_MERCHANT_CODE')
        ?: (in_array(strtolower(env('TRIPAY_ENVIRONMENT', 'sandbox')), ['live', 'production'])
            ? env('TRIPAY_PRODUCTION_MERCHANT_CODE')
            : env('TRIPAY_SANDBOX_MERCHANT_CODE')),
    'sandbox_base_url' => env('TRIPAY_SANDBOX_BASE_URL', 'https://tripay.co.id/api-sandbox'),
    'live_base_url' => env('TRIPAY_LIVE_BASE_URL', 'https://tripay.co.id/api'),
    'callback_url' => env('TRIPAY_CALLBACK_URL'),
    'return_url' => env('TRIPAY_RETURN_URL'),
    'default_method' => env('TRIPAY_DEFAULT_METHOD', 'QRIS'),
    'methods' => [
        'QRIS' => 'QRIS',
        'BRIVA' => 'BRI Virtual Account',
        'BCAVA' => 'BCA Virtual Account',
        'BNIVA' => 'BNI Virtual Account',
        'MANDIRIVA' => 'Mandiri Virtual Account',
        'ALFAMART' => 'Alfamart',
        'INDOMARET' => 'Indomaret',
        'DANA' => 'DANA',
        'SHOPEEPAY' => 'ShopeePay',
        'OVO' => 'OVO',
    ],
];
