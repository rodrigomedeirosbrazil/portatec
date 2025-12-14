<?php

declare(strict_types=1);

namespace App\DTOs;

use Carbon\CarbonInterface;

class BookingDTO
{
    public function __construct(
        public string $externalId,
        public string $guestName,
        public CarbonInterface $checkIn,
        public CarbonInterface $checkOut,
    ) {}
}
