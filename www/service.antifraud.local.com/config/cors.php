<?php

$allowedOrigins = array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '*'))));

return [
    'allowed_origins' => $allowedOrigins ?: ['*'],
    'allowed_headers' => env('CORS_ALLOWED_HEADERS', 'Content-Type, Authorization, X-Requested-With'),
    'allowed_methods' => env('CORS_ALLOWED_METHODS', 'GET, POST, PUT, DELETE, OPTIONS'),
    'max_age' => (int) env('CORS_MAX_AGE', 86400),
];
