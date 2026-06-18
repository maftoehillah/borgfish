<?php

return [
    'admin_whitelist' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADMIN_GOOGLE_WHITELIST', 'sabiqmaftu@gmail.com'))))),
    'otp' => [
        'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 5),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
        'max_resend' => (int) env('OTP_MAX_RESEND', 3),
        'rate_limit_per_hour' => (int) env('OTP_RATE_LIMIT_PER_HOUR', 6),
        'rate_limit_per_number_per_hour' => (int) env('OTP_RATE_LIMIT_PER_NUMBER_PER_HOUR', env('OTP_RATE_LIMIT_PER_HOUR', 6)),
        'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 60),
    ],
    'payment_deadline_minutes' => (int) env('AUCTION_PAYMENT_DEADLINE_MINUTES', 30),
    'payment_deadline_reminder_minutes' => (int) env('PAYMENT_DEADLINE_REMINDER_MINUTES', 10),
    'bid_spam_cooldown_seconds' => (int) env('BID_SPAM_COOLDOWN_SECONDS', 2),
    'buyer_violation_suspend_hours' => (int) env('BUYER_VIOLATION_SUSPEND_HOURS', 24),
    'buyer_violation_ban_threshold' => (int) env('BUYER_VIOLATION_BAN_THRESHOLD', 3),
    'notifications' => [
        'enable_whatsapp' => filter_var(env('NOTIFICATION_WHATSAPP_ENABLED', false), FILTER_VALIDATE_BOOL),
        'enable_email' => filter_var(env('NOTIFICATION_EMAIL_ENABLED', false), FILTER_VALIDATE_BOOL),
    ],
];
