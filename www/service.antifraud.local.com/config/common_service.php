<?php

return [
    'base_url' => env('COMMON_SERVICE_BASE_URL', 'https://file.hxcbox.cn/service/api/v1'),
    'host' => env('COMMON_SERVICE_HOST', ''),
    'project_code' => env('COMMON_SERVICE_PROJECT_CODE', 'antifraud'),
    'service_app_id' => env('COMMON_SERVICE_APP_ID', 'antifraud'),
    'service_secret' => env('COMMON_SERVICE_SECRET', ''),
    'timeout' => (int) env('COMMON_SERVICE_TIMEOUT', 15),
];
