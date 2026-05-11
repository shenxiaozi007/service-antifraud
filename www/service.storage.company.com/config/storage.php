<?php

return [
    'default_disk' => env('OBJECT_STORAGE_DEFAULT_DISK', 'tencent_cos'),
    'disks' => [
        'tencent_cos' => [
            'driver' => 's3',
            'key' => env('TENCENT_COS_SECRET_ID', ''),
            'secret' => env('TENCENT_COS_SECRET_KEY', ''),
            'region' => env('TENCENT_COS_REGION', 'ap-guangzhou'),
            'bucket' => env('TENCENT_COS_BUCKET', ''),
            'endpoint' => env('TENCENT_COS_ENDPOINT', ''),
            'cdn_host' => env('TENCENT_COS_CDN_HOST', ''),
            'use_path_style_endpoint' => env('TENCENT_COS_PATH_STYLE', false),
        ],
        'cloudflare_r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID', ''),
            'secret' => env('R2_SECRET_ACCESS_KEY', ''),
            'region' => env('R2_REGION', 'auto'),
            'bucket' => env('R2_BUCKET', ''),
            'endpoint' => env('R2_ENDPOINT', ''),
            'cdn_host' => env('R2_PUBLIC_HOST', ''),
            'use_path_style_endpoint' => env('R2_PATH_STYLE', true),
        ],
    ],
];
