<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\BookingDTO;
use Illuminate\Support\Collection;

interface ICalParserInterface
{
    /**
     * Parse iCal content and return Collection of BookingDTO
     *
     * @param  string  $icalContent  The iCal content to parse
     * @param  string|null  $icalUrl  Optional URL of the iCal file for platform-specific logic
     * @return Collection<BookingDTO>
     */
    public function parse(string $icalContent, ?string $icalUrl = null): Collection;
}
