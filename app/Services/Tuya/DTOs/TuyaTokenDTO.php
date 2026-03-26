<?php

declare(strict_types=1);

namespace App\Services\Tuya\DTOs;

class TuyaTokenDTO
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly int $expireTime,
        public readonly string $uid,
        public readonly ?string $endpoint = null,
    ) {}
}
