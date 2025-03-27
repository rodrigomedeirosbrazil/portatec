<?php

namespace App\Services\Tuya\DTOs;

class TuyaTicketDTO
{
    public function __construct(
        public string $ticketId,
        public string $ticketKey,
        public string $expireTime,
    ) {
    }
}
