<?php

return [
    'base_url' => env('LLM_BASE_URL', ''),
    'api_key' => env('LLM_API_KEY', ''),
    'model' => env('LLM_MODEL', ''),
    'vision_model' => env('LLM_VISION_MODEL', env('LLM_MODEL', '')),
    'audio_model' => env('LLM_AUDIO_MODEL', ''),
    'timeout' => (int) env('LLM_TIMEOUT', 60),
    'image_download_timeout' => (int) env('LLM_IMAGE_DOWNLOAD_TIMEOUT', 15),
    'image_inline_max_bytes' => (int) env('LLM_IMAGE_INLINE_MAX_BYTES', 5242880),
    'audio_download_timeout' => (int) env('LLM_AUDIO_DOWNLOAD_TIMEOUT', 30),
    'audio_inline_max_bytes' => (int) env('LLM_AUDIO_INLINE_MAX_BYTES', 26214400),
    'asr_service_url' => env('ASR_SERVICE_URL', ''),
    'asr_model' => env('ASR_MODEL_SIZE', 'tiny'),
    'asr_timeout' => (int) env('ASR_TIMEOUT', 120),
    'asr_language' => env('ASR_LANGUAGE', 'zh'),
];
