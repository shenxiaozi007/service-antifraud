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

        return $this->vision([
            ['type' => 'text', 'text' => '请提取图片中的文字、金额、联系方式、转账信息和可疑诈骗话术，输出纯文本摘要。'],
            ['type' => 'image_url', 'image_url' => ['url' => $imageInput]],
        ]);
    }

    public function transcribeAudio(string $audioUrl): array
    {
        if ($audioUrl === '') {
            return ['enabled' => false];
        }

        $prompt = "请根据以下音频文件URL进行转写，输出对话纯文本。如果无法直接访问，请说明无法转写并提取文件名线索：\n".$audioUrl;

        return $this->vision([
            ['type' => 'text', 'text' => $prompt],
        ], (string) config('llm.audio_model', '') ?: (string) config('llm.model', ''));
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
                return $imageUrl;
            }

            $body = $response->body();
            if ($body === '' || strlen($body) > $maxBytes) {
                return $imageUrl;
            }

            $contentType = (string) ($response->header('Content-Type') ?: $this->guessImageContentType($imageUrl));
            if (!str_starts_with($contentType, 'image/')) {
                return $imageUrl;
            }

            return 'data:'.$contentType.';base64,'.base64_encode($body);
        } catch (\Throwable) {
            return $imageUrl;
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
}
