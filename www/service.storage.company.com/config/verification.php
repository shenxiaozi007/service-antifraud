<?php

return [
    'webhook_url' => env('VERIFICATION_CODE_WEBHOOK_URL', ''),
    'webhook_token' => env('VERIFICATION_CODE_WEBHOOK_TOKEN', ''),
    'timeout' => (int) env('VERIFICATION_CODE_TIMEOUT', 10),
];
