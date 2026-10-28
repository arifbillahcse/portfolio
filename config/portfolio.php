<?php

return [
    'public_path' => env('APP_PUBLIC_PATH'),
    'indexable' => (bool) env('SITE_INDEXABLE', env('APP_ENV') === 'production'),
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),
];
