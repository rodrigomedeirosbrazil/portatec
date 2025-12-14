<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\BookingDTO;
use Illuminate\Support\Collection;

interface ICalParserInterface
{
    /**
     * Parse iCal content and return Collection of BookingDTO
     */
    public function parse(string $icalContent): Collection;
}
