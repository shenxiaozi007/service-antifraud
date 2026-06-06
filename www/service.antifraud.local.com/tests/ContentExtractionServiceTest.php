<?php

namespace Tests;

use App\Libraries\Agent\ContentExtractionService;
use App\Libraries\Agent\LlmClient;
use App\Modules\Basics\Constant\AnalysisConstant;
use App\Modules\Basics\Model\FileAsset;

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
