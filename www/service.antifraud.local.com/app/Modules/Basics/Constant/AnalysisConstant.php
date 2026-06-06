<?php

namespace App\Modules\Basics\Constant;

class AnalysisConstant
{
    public const TYPE_IMAGE = 'image';
    public const TYPE_AUDIO = 'audio';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELED = 'canceled';

    public const RISK_LOW = 'low';
    public const RISK_MEDIUM = 'medium';
    public const RISK_HIGH = 'high';
    public const RISK_CRITICAL = 'critical';

    public static function types(): array
    {
        return [self::TYPE_IMAGE, self::TYPE_AUDIO];
    }

    public static function riskLevels(): array
    {
        return [self::RISK_LOW, self::RISK_MEDIUM, self::RISK_HIGH, self::RISK_CRITICAL];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_SUCCESS,
            self::STATUS_FAILED,
            self::STATUS_CANCELED,
        ];
    }
}
