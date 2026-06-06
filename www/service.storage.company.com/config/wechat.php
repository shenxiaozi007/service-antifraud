<?php

return [
    'mini_program' => [
        'app_id' => env('WECHAT_MINI_PROGRAM_APP_ID', env('WECHAT_PAY_APP_ID', '')),
        'app_secret' => env('WECHAT_MINI_PROGRAM_APP_SECRET', ''),
        'session_url' => env('WECHAT_MINI_PROGRAM_SESSION_URL', 'https://api.weixin.qq.com/sns/jscode2session'),
        'mock' => filter_var(env('WECHAT_LOGIN_MOCK', false), FILTER_VALIDATE_BOOL),
    ],
];
