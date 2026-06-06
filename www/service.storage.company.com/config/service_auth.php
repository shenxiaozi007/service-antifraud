<?php

return [
    'apps' => [
        env('SERVICE_APP_ID', 'antifraud') => env('SERVICE_SECRET', ''),
    ],
    'timestamp_tolerance' => (int) env('SERVICE_AUTH_TIMESTAMP_TOLERANCE', 300),
];
