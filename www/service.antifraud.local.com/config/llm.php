<?php

return [
    'base_url' => env('LLM_BASE_URL', ''),
    'api_key' => env('LLM_API_KEY', ''),
    'model' => env('LLM_MODEL', ''),
    'vision_model' => env('LLM_VISION_MODEL', env('LLM_MODEL', '')),
    'audio_model' => env('LLM_AUDIO_MODEL', ''),
    'timeout' => (int) env('LLM_TIMEOUT', 60),
];
