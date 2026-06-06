<?php

return [
    'wechat' => [
        'app_id' => env('WECHAT_PAY_APP_ID', ''),
        'mch_id' => env('WECHAT_PAY_MCH_ID', ''),
        'api_v3_key' => env('WECHAT_PAY_API_V3_KEY', ''),
        'merchant_serial_no' => env('WECHAT_PAY_MERCHANT_SERIAL_NO', ''),
        'merchant_private_key' => env('WECHAT_PAY_MERCHANT_PRIVATE_KEY', ''),
        'merchant_private_key_path' => env('WECHAT_PAY_MERCHANT_PRIVATE_KEY_PATH', ''),
        'platform_certificate' => env('WECHAT_PAY_PLATFORM_CERTIFICATE', ''),
        'platform_certificate_path' => env('WECHAT_PAY_PLATFORM_CERTIFICATE_PATH', ''),
        'notify_url' => env('WECHAT_PAY_NOTIFY_URL', 'https://file.hxcbox.cn/service/api/v1/payment/wechat/notify'),
        'api_base_url' => env('WECHAT_PAY_API_BASE_URL', 'https://api.mch.weixin.qq.com'),
        'mock' => filter_var(env('WECHAT_PAY_MOCK', false), FILTER_VALIDATE_BOOL),
    ],
];
