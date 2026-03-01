<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ICalParserInterface;
use App\DTOs\BookingDTO;
use App\Libraries\Integrations\Support\Helpers\ICalHelper;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Sabre\VObject\ParseException;
use Sabre\VObject\Property\ICalendar\DateTime as VObjectDateTime;
use Sabre\VObject\Reader;

class ICalParser implements ICalParserInterface
{
    private const DEFAULT_TIMEZONE = 'UTC';

    /**
     * Parse iCal content and return Collection of BookingDTO
     *
     * @param  string  $icalContent  The iCal content to parse
     * @param  string|null  $icalUrl  Optional URL of the iCal file for platform-specific logic
     * @return Collection<BookingDTO>
     */
    public function parse(string $icalContent, ?string $icalUrl = null): Collection
    {
        // Validate iCal content
        if (! $this->hasIcalHeaders($icalContent)) {
            Log::warning('Invalid iCal content: missing BEGIN:VCALENDAR or END:VCALENDAR');

            return collect([]);
        }

        try {
            $vCalendar = Reader::read($icalContent, $this->getReaderOption($icalUrl));

            if (empty($vCalendar) || empty($vCalendar->VEVENT)) {
                return collect([]);
            }

            $platform = $icalUrl ? ICalHelper::getPlatformByIcalUrl($icalUrl) : null;
            $forceDateUTC = $icalUrl ? ICalHelper::shouldForceDateUTC($icalUrl) : false;
            $shouldRemoveHours = $icalUrl ? ICalHelper::shouldRemoveHours($icalUrl) : false;

            $bookings = collect([]);

            foreach ($vCalendar->VEVENT as $vevent) {
                try {
                    $bookingDTO = $this->parseEvent($vevent, $platform, $forceDateUTC, $shouldRemoveHours);
                    if ($bookingDTO !== null) {
                        $bookings->push($bookingDTO);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Error parsing iCal event', [
                        'error' => $e->getMessage(),
                        'uid' => $this->getEventUid($vevent),
                    ]);

                    continue;
                }
            }

            return $bookings;
        } catch (ParseException $e) {
            Log::error('Failed to parse iCal content', [
                'error' => $e->getMessage(),
                'url' => $icalUrl,
            ]);

            return collect([]);
        } catch (\Throwable $e) {
            Log::error('Unexpected error parsing iCal', [
                'error' => $e->getMessage(),
                'url' => $icalUrl,
            ]);

            return collect([]);
        }
    }

    /**
     * Parse a single VEVENT into BookingDTO
     */
    private function parseEvent(
        $vevent,
        ?string $platform,
        bool $forceDateUTC,
        bool $shouldRemoveHours
    ): ?BookingDTO {
        // Extract required fields
        $dtstart = $vevent->DTSTART ?? null;
        $dtend = $vevent->DTEND ?? null;

        if (! $dtstart || ! $dtend) {
            return null;
        }

        // Parse dates
        $checkIn = $this->parseDateTime($dtstart, $forceDateUTC, $shouldRemoveHours);
        $checkOut = $this->parseDateTime($dtend, $forceDateUTC, $shouldRemoveHours);

        // Adjust end date if needed (for platforms that support one-day end date)
        if ($platform) {
            $checkOut = $this->adjustEndDate($checkIn, $checkOut, $platform);
        }

        // Extract external ID (UID)
        $externalId = $this->getExternalId($vevent);

        // Extract guest name
        $guestName = $this->extractGuestName($vevent);

        return new BookingDTO(
            externalId: $externalId,
            guestName: $guestName,
            checkIn: $checkIn,
            checkOut: $checkOut
        );
    }

    /**
     * Parse VObjectDateTime to Carbon
     */
    private function parseDateTime(
        VObjectDateTime $date,
        bool $forceDateUTC = false,
        bool $shouldRemoveHours = false
    ): Carbon {
        $timeZoneName = self::DEFAULT_TIMEZONE;

        if ($this->dateWithUTCTime($date) || $forceDateUTC || $shouldRemoveHours) {
            $dateTime = new DateTime('@'.$date->getDateTime()->getTimestamp());
            $dateTime->setTimezone(new DateTimeZone($timeZoneName));
            $dateTime->setTime(0, 0);

            if ($shouldRemoveHours) {
                return Carbon::parse($dateTime->format('Y-m-d'))->startOfDay();
            }

            return Carbon::instance($dateTime);
        }

        if ($this->dateWithLocalTime($date)) {
            $dateTime = new DateTime('@'.$date->getDateTime()->getTimestamp());
            $dateTime->setTime(0, 0);

            return Carbon::instance($dateTime);
        }

        return Carbon::instance(new DateTime('@'.$date->getDateTime()->getTimestamp()));
    }

    /**
     * Check if date has UTC time (ends with Z)
     */
    private function dateWithUTCTime(VObjectDateTime $date): bool
    {
        $dateValue = $date->getValue();

        return (bool) (strlen($dateValue) == 16 && mb_substr($dateValue, -1) === 'Z');
    }

    /**
     * Check if date has local time (format: YYYYMMDDTHHMMSS)
     */
    private function dateWithLocalTime(VObjectDateTime $date): bool
    {
        $dateValue = $date->getValue();

        return (bool) (strlen($dateValue) == 15 && mb_substr($dateValue, -7, 1) === 'T');
    }

    /**
     * Extract external ID from event (UID or hash fallback)
     */
    private function getExternalId($vevent): string
    {
        if (isset($vevent->UID)) {
            return (string) $vevent->UID;
        }

        // Fallback: create hash from dates
        $dtstart = $vevent->DTSTART ?? null;
        $dtend = $vevent->DTEND ?? null;

        if ($dtstart && $dtend) {
            $startTimestamp = $dtstart->getDateTime()->getTimestamp();
            $endTimestamp = $dtend->getDateTime()->getTimestamp();

            return md5("{$endTimestamp}|{$startTimestamp}");
        }

        return md5(uniqid('', true));
    }

    /**
     * Extract guest name from SUMMARY or DESCRIPTION
     */
    private function extractGuestName($vevent): string
    {
        $summary = isset($vevent->SUMMARY) ? (string) $vevent->SUMMARY : '';
        $description = isset($vevent->DESCRIPTION) ? (string) $vevent->DESCRIPTION : '';

        // Try to extract name from common patterns
        $text = $summary ?: $description;

        if (empty($text)) {
            return '';
        }

        // Common patterns:
        // - "Reservation for John Doe"
        // - "John Doe - Reservation"
        // - "Guest: John Doe"
        // - Just the name itself

        $patterns = [
            '/reservation\s+for\s+(.+)/i',
            '/guest[:\s]+(.+)/i',
            '/^(.+?)\s*[-–]\s*reservation/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $name = trim($matches[1]);
                if (! empty($name)) {
                    return $name;
                }
            }
        }

        // If no pattern matches, return the summary/description as-is
        // (might be just the name)
        return trim($text);
    }

    /**
     * Adjust end date for platforms that support one-day end date
     */
    private function adjustEndDate(Carbon $checkIn, Carbon $checkOut, ?string $platform): Carbon
    {
        if (! $platform) {
            return $checkOut;
        }

        if (ICalHelper::shouldAddOneDayOnEndDateWhenIsSameDate($platform)) {
            // If check-in and check-out are on the same day, add one day
            if ($checkIn->format('Y-m-d') === $checkOut->format('Y-m-d')) {
                return $checkOut->copy()->addDay();
            }
        }

        return $checkOut;
    }

    /**
     * Get reader option for parsing (ignore invalid lines for certain calendars)
     */
    private function getReaderOption(?string $icalUrl): int
    {
        // This logic can be extended based on calendar URL patterns
        // For now, return default option (0)
        return 0;
    }

    /**
     * Check if content has valid iCal headers
     */
    private function hasIcalHeaders(string $rawCalendar): bool
    {
        return is_string($rawCalendar)
            && strpos($rawCalendar, 'BEGIN:VCALENDAR') !== false
            && strpos($rawCalendar, 'END:VCALENDAR') !== false;
    }

    /**
     * Get event UID for logging purposes
     */
    private function getEventUid($vevent): string
    {
        return isset($vevent->UID) ? (string) $vevent->UID : 'unknown';
    }
}
