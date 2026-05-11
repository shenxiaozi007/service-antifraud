<?php

namespace App\Exceptions;

use App\Kernel\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\Request;

class BaseException extends Exception
{
    use ApiResponseTrait;

    protected array $data;

    protected array $map = [];

    protected static array $codeMaps = [];

    public function __construct($code, array $data = [], $message = '')
    {
        $code = (int) $code;
        $map = $this->getCodeMap($code);

        $this->data = $data;
        $this->map = $map;

        parent::__construct($message ?: array_get($map, 'message', ''), $code);
    }

    public function all(): array
    {
        return [
            'code' => $this->getCode(),
            'message' => $this->getMessage(),
            'data' => $this->data ?: null,
            'time' => get_now(),
            'module' => config('service.name'),
        ];
    }

    public function getCodeMap(int $code): array
    {
        return array_get(static::getCodeMaps(), $code, []);
    }

    public static function getCodeMaps(): array
    {
        return static::$codeMaps;
    }

    public function render(Request $request)
    {
        return $this->ok($this->all());
    }
}
