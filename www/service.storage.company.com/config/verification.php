<?php

return [
    'webhook_url' => env('VERIFICATION_CODE_WEBHOOK_URL', ''),
    'webhook_token' => env('VERIFICATION_CODE_WEBHOOK_TOKEN', ''),
    'timeout' => (int) env('VERIFICATION_CODE_TIMEOUT', 10),
    'mail' => [
        'enabled' => (bool) env('VERIFICATION_CODE_MAIL_ENABLED', false),
        'host' => env('VERIFICATION_CODE_MAIL_HOST', 'smtp.exmail.qq.com'),
        'port' => (int) env('VERIFICATION_CODE_MAIL_PORT', 465),
        'encryption' => env('VERIFICATION_CODE_MAIL_ENCRYPTION', 'ssl'),
        'username' => env('VERIFICATION_CODE_MAIL_USERNAME', ''),
        'password' => env('VERIFICATION_CODE_MAIL_PASSWORD', ''),
        'from_address' => env('VERIFICATION_CODE_MAIL_FROM_ADDRESS', env('VERIFICATION_CODE_MAIL_USERNAME', '')),
        'from_name' => env('VERIFICATION_CODE_MAIL_FROM_NAME', '守护者max'),
        'timeout' => (int) env('VERIFICATION_CODE_MAIL_TIMEOUT', 15),
    ],
];
