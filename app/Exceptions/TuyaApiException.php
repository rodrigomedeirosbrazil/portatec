<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class TuyaApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $tuyaCode = null,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message);
    }

    public static function http(string $path, int $status, string $body): self
    {
        return new self(
            "Tuya respondeu HTTP {$status} em {$path}: ".substr($body, 0, 200),
            httpStatus: $status,
        );
    }

    public static function api(string $path, ?string $code, ?string $message): self
    {
        return new self(
            "Tuya recusou {$path}: [{$code}] ".($message ?? 'sem mensagem'),
            tuyaCode: $code,
        );
    }
}
