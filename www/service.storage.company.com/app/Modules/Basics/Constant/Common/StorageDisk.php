<?php

namespace App\Modules\Basics\Constant\Common;

use App\Kernel\Base\BaseConstant;

class StorageDisk extends BaseConstant
{
    public const TENCENT_COS = 'tencent_cos';

    public const CLOUDFLARE_R2 = 'cloudflare_r2';

    public static function getNames(): array
    {
        return [
            self::TENCENT_COS => '腾讯云 COS',
            self::CLOUDFLARE_R2 => 'Cloudflare R2',
        ];
    }

    public static function getWithDefault(?string $disk = ''): string
    {
        if ($disk && static::has($disk)) {
            return $disk;
        }

        return config('storage.default_disk', self::TENCENT_COS);
    }
}
