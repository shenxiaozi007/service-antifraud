<?php

namespace App\Libraries\Agent;

use Illuminate\Support\Facades\Http;

class LlmClient
{
    public function analyze(string $prompt): array
    {
        $baseUrl = rtrim((string) config('llm.base_url'), '/');
        $apiKey = (string) config('llm.api_key');
        $model = (string) config('llm.model');

        if ($baseUrl === '' || $apiKey === '' || $model === '') {
            return ['enabled' => false];
        }

        $started = microtime(true);
        $response = Http::timeout((int) config('llm.timeout', 60))
            ->withToken($apiKey)
            ->acceptJson()
            ->post($baseUrl.'/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => '你是反诈风险分析 agent，只输出 JSON。'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2,
            ]);

        $durationMs = (int) round((microtime(true) - $started) * 1000);
        if (!$response->successful()) {
            return ['enabled' => true, 'success' => false, 'duration_ms' => $durationMs, 'raw' => $response->body()];
        }

        $body = $response->json();
        $content = $body['choices'][0]['message']['content'] ?? '';
        $json = json_decode($this->extractJson($content), true);

        return [
            'enabled' => true,
            'success' => is_array($json),
            'model' => $model,
            'duration_ms' => $durationMs,
            'result' => is_array($json) ? $json : [],
            'raw' => $body,
        ];
    }

    public function describeImage(string $imageUrl): array
    {
        $imageInput = $this->imageInput($imageUrl);
        if ($imageInput === '') {
            return ['enabled' => false];
        }

        return $this->vision([
            ['type' => 'text', 'text' => '请提取图片中的文字、金额、联系方式、转账信息和可疑诈骗话术，输出纯文本摘要。'],
            ['type' => 'image_url', 'image_url' => ['url' => $imageInput]],
        ]);
    }

    /**
     * 转写音频内容。
     *
     * 优先调用本机轻量 ASR 服务；本机 ASR 未配置时，才回退到兼容 OpenAI
     * audio/transcriptions 协议的外部 LLM 服务。
     *
     * @param string $audioUrl 音频下载地址
     * @return array
     */
    public function transcribeAudio(string $audioUrl): array
    {
        if ($audioUrl === '') {
            return ['enabled' => false];
        }

        $audio = $this->downloadAudio($audioUrl);
        if (!$audio) {
            return ['enabled' => false];
        }

        $localAsrResult = $this->transcribeAudioByLocalAsr($audio);
        if (($localAsrResult['enabled'] ?? false) === true) {
            return $localAsrResult;
        }

        $baseUrl = rtrim((string) config('llm.base_url'), '/');
        $apiKey = (string) config('llm.api_key');
        $model = (string) config('llm.audio_model', '') ?: (string) config('llm.model', '');
        if ($baseUrl === '' || $apiKey === '' || $model === '') {
            return ['enabled' => false];
        }

        $started = microtime(true);
        $response = Http::timeout((int) config('llm.timeout', 60))
            ->withToken($apiKey)
            ->acceptJson()
            ->attach('file', $audio['body'], $audio['filename'])
            ->post($baseUrl.'/audio/transcriptions', [
                'model' => $model,
                'response_format' => 'json',
            ]);

        $durationMs = (int) round((microtime(true) - $started) * 1000);
        if (!$response->successful()) {
            return ['enabled' => true, 'success' => false, 'model' => $model, 'duration_ms' => $durationMs, 'raw' => $response->body()];
        }

        $body = $response->json();

        return [
            'enabled' => true,
            'success' => is_array($body) && (string) ($body['text'] ?? '') !== '',
            'model' => $model,
            'duration_ms' => $durationMs,
            'text' => (string) ($body['text'] ?? ''),
            'raw' => $body,
        ];
    }

    /**
     * 调用本机轻量 ASR 服务转写音频。
     *
     * @param array $audio 已下载音频内容，包含 body 和 filename
     * @return array
     */
    protected function transcribeAudioByLocalAsr(array $audio): array
    {
        $serviceUrl = rtrim((string) config('llm.asr_service_url', ''), '/');
        if ($serviceUrl === '') {
            return ['enabled' => false];
        }

        $started = microtime(true);
        try {
            $response = Http::timeout((int) config('llm.asr_timeout', 120))
                ->acceptJson()
                ->attach('file', $audio['body'], $audio['filename'])
                ->post($serviceUrl.'/transcribe', [
                    'language' => (string) config('llm.asr_language', 'zh'),
                ]);
        } catch (\Throwable $exception) {
            return [
                'enabled' => true,
                'success' => false,
                'model' => (string) config('llm.asr_model', 'tiny'),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'raw' => $exception->getMessage(),
            ];
        }

        $durationMs = (int) round((microtime(true) - $started) * 1000);
        if (!$response->successful()) {
            return [
                'enabled' => true,
                'success' => false,
                'model' => (string) config('llm.asr_model', 'tiny'),
                'duration_ms' => $durationMs,
                'raw' => $response->body(),
            ];
        }

        $body = $response->json();
        $text = trim((string) ($body['text'] ?? ''));

        return [
            'enabled' => true,
            'success' => $text !== '',
            'model' => (string) ($body['model'] ?? config('llm.asr_model', 'tiny')),
            'duration_ms' => $durationMs,
            'text' => $text,
            'raw' => $body,
        ];
    }

    protected function vision(array $content, string $model = ''): array
    {
        $baseUrl = rtrim((string) config('llm.base_url'), '/');
        $apiKey = (string) config('llm.api_key');
        $model = $model ?: (string) config('llm.vision_model');
        if ($baseUrl === '' || $apiKey === '' || $model === '') {
            return ['enabled' => false];
        }

        $started = microtime(true);
        $response = Http::timeout((int) config('llm.timeout', 60))
            ->withToken($apiKey)
            ->acceptJson()
            ->post($baseUrl.'/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $content],
                ],
                'temperature' => 0.1,
            ]);

        $durationMs = (int) round((microtime(true) - $started) * 1000);
        if (!$response->successful()) {
            return ['enabled' => true, 'success' => false, 'duration_ms' => $durationMs, 'raw' => $response->body()];
        }

        $body = $response->json();

        return [
            'enabled' => true,
            'success' => true,
            'model' => $model,
            'duration_ms' => $durationMs,
            'text' => (string) ($body['choices'][0]['message']['content'] ?? ''),
            'raw' => $body,
        ];
    }

    protected function extractJson(string $content): string
    {
        if (preg_match('/```json\s*(.*?)```/s', $content, $matches)) {
            return trim($matches[1]);
        }
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            return $matches[0];
        }

        return $content;
    }

    protected function imageInput(string $imageUrl): string
    {
        if ($imageUrl === '' || str_starts_with($imageUrl, 'data:')) {
            return $imageUrl;
        }

        $maxBytes = (int) config('llm.image_inline_max_bytes', 5 * 1024 * 1024);
        try {
            $response = Http::timeout((int) config('llm.image_download_timeout', 15))->get($imageUrl);
            if (!$response->successful()) {
                return '';
            }

            $body = $response->body();
            if ($body === '' || strlen($body) > $maxBytes) {
                return '';
            }

            $contentType = (string) ($response->header('Content-Type') ?: $this->guessImageContentType($imageUrl));
            if (!str_starts_with($contentType, 'image/')) {
                return '';
            }

            return 'data:'.$contentType.';base64,'.base64_encode($body);
        } catch (\Throwable) {
            return '';
        }
    }

    protected function guessImageContentType(string $imageUrl): string
    {
        $path = parse_url($imageUrl, PHP_URL_PATH) ?: '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }

    protected function downloadAudio(string $audioUrl): ?array
    {
        $maxBytes = (int) config('llm.audio_inline_max_bytes', 25 * 1024 * 1024);
        try {
            $response = Http::timeout((int) config('llm.audio_download_timeout', 30))->get($audioUrl);
            if (!$response->successful()) {
                return null;
            }

            $body = $response->body();
            if ($body === '' || strlen($body) > $maxBytes) {
                return null;
            }

            return [
                'body' => $body,
                'filename' => $this->audioFilename($audioUrl, (string) $response->header('Content-Type')),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    protected function audioFilename(string $audioUrl, string $contentType = ''): string
    {
        $path = parse_url($audioUrl, PHP_URL_PATH) ?: '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension !== '') {
            return 'audio.'.$extension;
        }

        $extension = match (strtolower(strtok($contentType, ';') ?: '')) {
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/mp4', 'audio/m4a', 'audio/x-m4a' => 'm4a',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/webm' => 'webm',
            default => 'mp3',
        };

        return 'audio.'.$extension;
    }
}
