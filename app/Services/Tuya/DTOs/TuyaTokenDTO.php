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
        public readonly ?string $terminalId = null,
        /** Timestamp do servidor Tuya em milissegundos (campo `t` da resposta). */
        public readonly ?int $serverTime = null,
    ) {}
}
