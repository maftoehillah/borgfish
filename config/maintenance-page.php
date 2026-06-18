<?php

return [
    'enabled' => filter_var(env('MAINTENANCE_MODE', 0), FILTER_VALIDATE_BOOL),
    'html_path' => env('MAINTENANCE_HTML_PATH', public_path('maintenance.html')),
    'status' => max(200, (int) env('MAINTENANCE_STATUS', 503)),
    'except' => array_values(array_filter(array_map(
        static fn (string $value): string => trim($value),
        explode(',', (string) env('MAINTENANCE_EXCEPT', 'up,api/tripay/callback',))
    ))),
];
