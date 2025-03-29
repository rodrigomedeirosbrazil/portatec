<?php

namespace App\Services\Tuya\DTOs;

class TuyaAuthenticationDTO
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public string $expireTime,
        public string $uid,
    ) {
    }
}