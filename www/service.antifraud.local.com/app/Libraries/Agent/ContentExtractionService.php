<?php

namespace App\Libraries\Agent;

use App\Modules\Basics\Constant\AnalysisConstant;

class ContentExtractionService
{
    public function __construct(protected LlmClient $llmClient)
    {
    }

    public function extract($file): array
    {
        if ($file->file_type === AnalysisConstant::TYPE_IMAGE) {
            $result = $file->ocr_text ? [] : $this->llmClient->describeImage($file->file_url ?: '');
            if (($result['enabled'] ?? false) && !($result['success'] ?? false)) {
                $message = $this->errorMessage($result, '图片识别失败');
                $file->fill(['ocr_status' => 'failed', 'ocr_error' => $message])->save();

                throw new \RuntimeException($message);
            }

            $text = $file->ocr_text ?: (($result['success'] ?? false) ? $result['text'] : $this->fallbackText($file));
            $file->fill(['ocr_text' => $text, 'ocr_status' => 'success', 'ocr_error' => null])->save();

            return ['text' => $text, 'status' => 'success'];
        }

        $result = $file->transcript_text ? [] : $this->llmClient->transcribeAudio($file->file_url ?: '');
        if (($result['enabled'] ?? false) && !($result['success'] ?? false)) {
            $message = $this->errorMessage($result, '音频转写失败');
            $file->fill(['transcript_status' => 'failed', 'transcript_error' => $message])->save();

            throw new \RuntimeException($message);
        }

        $text = $file->transcript_text ?: (($result['success'] ?? false) ? $result['text'] : $this->fallbackText($file));
        $file->fill(['transcript_text' => $text, 'transcript_status' => 'success', 'transcript_error' => null])->save();

        return ['text' => $text, 'status' => 'success'];
    }

    protected function fallbackText($file): string
    {
        return sprintf('文件 %s 已上传，当前未配置识别供应商，暂使用文件URL和用户补充文本作为识别输入：%s', $file->storage_file_id ?: $file->id, $file->file_url ?: $file->storage_key);
    }

    protected function errorMessage(array $result, string $default): string
    {
        $raw = $result['raw'] ?? '';
        if (is_array($raw)) {
            $raw = json_encode($raw, JSON_UNESCAPED_UNICODE);
        }

        return trim($default.($raw ? '：'.mb_substr((string) $raw, 0, 500) : ''));
    }
}
