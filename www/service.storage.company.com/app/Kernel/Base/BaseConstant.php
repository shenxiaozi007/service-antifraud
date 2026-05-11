<?php

namespace App\Kernel\Base;

abstract class BaseConstant
{
    abstract public static function getNames(): array;

    public static function all(): array
    {
        return array_keys(static::getNames());
    }

    public static function getName($code): ?string
    {
        return is_null($code) ? null : array_get(static::getNames(), $code, '');
    }

    public static function has($code): bool
    {
        return array_key_exists($code, static::getNames());
    }
}
