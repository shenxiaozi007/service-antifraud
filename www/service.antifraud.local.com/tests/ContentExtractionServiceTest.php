<?php

namespace Tests;

use App\Libraries\Agent\ContentExtractionService;
use App\Libraries\Agent\LlmClient;
use App\Modules\Basics\Constant\AnalysisConstant;
use App\Modules\Basics\Model\FileAsset;
use Illuminate\Support\Facades\Http;

class ContentExtractionServiceTest extends TestCase
{
    public function test_image_extraction_uses_llm_text_when_available(): void
    {
        $file = new InMemoryFileAsset([
            'id' => 1,
            'file_type' => AnalysisConstant::TYPE_IMAGE,
            'storage_file_id' => 'common_file_1',
            'file_url' => 'https://file.hxcbox.cn/a.png',
        ]);
        $service = new ContentExtractionService(new FakeExtractionLlmClient([
            'describeImage' => ['enabled' => true, 'success' => true, 'text' => '图片里出现验证码和个人账户'],
        ]));

        $result = $service->extract($file);

        $this->assertSame('图片里出现验证码和个人账户', $result['text']);
        $this->assertSame('success', $file->ocr_status);
        $this->assertTrue($file->saved);
    }

    public function test_audio_extraction_falls_back_to_placeholder_when_llm_unavailable(): void
    {
        $file = new InMemoryFileAsset([
            'id' => 2,
            'file_type' => AnalysisConstant::TYPE_AUDIO,
            'storage_file_id' => 'common_audio_1',
            'file_url' => '',
            'storage_key' => 'audio/demo.m4a',
        ]);
        $service = new ContentExtractionService(new FakeExtractionLlmClient([
            'transcribeAudio' => ['enabled' => false],
        ]));

        $result = $service->extract($file);

        $this->assertStringContainsString('common_audio_1', $result['text']);
        $this->assertStringContainsString('audio/demo.m4a', $result['text']);
        $this->assertSame('success', $file->transcript_status);
        $this->assertTrue($file->saved);
    }

    public function test_image_extraction_fails_when_enabled_provider_returns_error(): void
    {
        $file = new InMemoryFileAsset([
            'id' => 3,
            'file_type' => AnalysisConstant::TYPE_IMAGE,
            'storage_file_id' => 'common_file_failed',
            'file_url' => 'https://file.hxcbox.cn/bad.png',
        ]);
        $service = new ContentExtractionService(new FakeExtractionLlmClient([
            'describeImage' => ['enabled' => true, 'success' => false, 'raw' => 'provider timeout'],
        ]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('图片识别失败');

        try {
            $service->extract($file);
        } finally {
            $this->assertSame('failed', $file->ocr_status);
            $this->assertStringContainsString('provider timeout', $file->ocr_error);
            $this->assertTrue($file->saved);
        }
    }

    public function test_llm_client_inlines_image_url_as_data_url_for_vision_models(): void
    {
        config([
            'llm.base_url' => 'https://llm.example.com/v1',
            'llm.api_key' => 'test-key',
            'llm.vision_model' => 'vision-model',
            'llm.image_inline_max_bytes' => 1024,
        ]);
        Http::fake([
            'https://r2.example.com/demo.jpeg' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
            'https://llm.example.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => '图片内容摘要']],
                ],
            ]),
        ]);

        $result = app(LlmClient::class)->describeImage('https://r2.example.com/demo.jpeg');

        $this->assertTrue($result['success']);
        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://llm.example.com/v1/chat/completions') {
                return false;
            }

            $payload = $request->data();
            $imageUrl = $payload['messages'][0]['content'][1]['image_url']['url'] ?? '';

            return str_starts_with($imageUrl, 'data:image/jpeg;base64,');
        });
    }

    public function test_llm_client_disables_image_provider_when_image_download_fails(): void
    {
        config([
            'llm.base_url' => 'https://llm.example.com/v1',
            'llm.api_key' => 'test-key',
            'llm.vision_model' => 'vision-model',
        ]);
        Http::fake([
            'https://r2.example.com/private.jpeg' => Http::response('bad request', 400),
            'https://llm.example.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'should not be called']],
                ],
            ]),
        ]);

        $result = app(LlmClient::class)->describeImage('https://r2.example.com/private.jpeg');

        $this->assertFalse($result['enabled']);
        Http::assertNotSent(fn ($request) => $request->url() === 'https://llm.example.com/v1/chat/completions');
    }
}

class FakeExtractionLlmClient extends LlmClient
{
    public function __construct(private array $responses)
    {
    }

    public function describeImage(string $imageUrl): array
    {
        return $this->responses['describeImage'] ?? ['enabled' => false];
    }

    public function transcribeAudio(string $audioUrl): array
    {
        return $this->responses['transcribeAudio'] ?? ['enabled' => false];
    }
}

class InMemoryFileAsset extends FileAsset
{
    public bool $saved = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct();
        $this->exists = true;
        $this->forceFill($attributes);
    }

    public function save(array $options = []): bool
    {
        $this->saved = true;

        return true;
    }
}
