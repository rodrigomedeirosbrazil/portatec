<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ICalParserInterface;
use App\DTOs\BookingDTO;
use App\Enums\BookingDeletionReasonEnum;
use App\Models\Booking;
use App\Models\Integration;
use App\Models\Place;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ICalSyncService
{
    public function __construct(
        private ICalParserInterface $parser
    ) {}

    public function syncPlaceIntegration(int $placeId, int $integrationId): void
    {
        $place = Place::findOrFail($placeId);
        $integration = Integration::findOrFail($integrationId);

        $placeIntegration = $place->integrations()
            ->where('integration_id', $integrationId)
            ->first();

        if (! $placeIntegration) {
            throw new \RuntimeException("Place-Integration relationship not found for place={$placeId}, integration={$integrationId}");
        }

        if (! $placeIntegration->pivot->external_id) {
            throw new \RuntimeException("External ID not configured for place={$placeId}, integration={$integrationId}");
        }

        $externalId = $placeIntegration->pivot->external_id;
        Log::info('Starting iCal sync', [
            'place_id' => $placeId,
            'integration_id' => $integrationId,
            'external_id' => $externalId,
        ]);

        try {
            $icalContent = $this->downloadICal($externalId);
            $bookingDTOs = $this->parser->parse($icalContent, $externalId);

            $existingBookings = Booking::where('place_id', $placeId)
                ->where('integration_id', $integrationId)
                ->whereNull('deleted_at')
                ->get()
                ->keyBy('external_id');

            $existingBookings = $this->dedupeExistingBookings($existingBookings);

            $currentExternalIds = [];
            foreach ($bookingDTOs as $bookingDTO) {
                $currentExternalIds[] = $bookingDTO->externalId;
                $this->createOrUpdateBooking($bookingDTO, $integration, $place, $existingBookings);
            }

            $removedBookings = $existingBookings->whereNotIn('external_id', $currentExternalIds);
            foreach ($removedBookings as $booking) {
                $booking->deletion_reason = BookingDeletionReasonEnum::Canceled;
                $booking->delete();
            }

            Log::info('Finished iCal sync', [
                'place_id' => $placeId,
                'integration_id' => $integrationId,
                'received' => count($currentExternalIds),
                'removed' => $removedBookings->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('iCal sync failed', [
                'place_id' => $placeId,
                'integration_id' => $integrationId,
                'external_id' => $externalId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function downloadICal(string $url): string
    {
        $response = Http::timeout(30)->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to download iCal from: {$url}; status={$response->status()}");
        }

        return $response->body();
    }

    private function createOrUpdateBooking(
        BookingDTO $bookingDTO,
        Integration $integration,
        Place $place,
        Collection $existingBookings
    ): Booking {
        $booking = $existingBookings->get($bookingDTO->externalId);

        if ($booking) {
            // Verificar se houve mudanças
            $hasChanges = false;
            $deletionReason = null;

            if ($booking->check_in->format('Y-m-d H:i:s') !== $bookingDTO->checkIn->format('Y-m-d H:i:s') ||
                $booking->check_out->format('Y-m-d H:i:s') !== $bookingDTO->checkOut->format('Y-m-d H:i:s')) {
                $hasChanges = true;
                $deletionReason = BookingDeletionReasonEnum::ChangeDate;
            }

            if ($booking->guest_name !== $bookingDTO->guestName) {
                $hasChanges = true;
                $deletionReason = BookingDeletionReasonEnum::ChangeGuest;
            }

            if ($hasChanges) {
                $booking->deletion_reason = $deletionReason;
                $booking->delete();

                return Booking::create([
                    'place_id' => $place->id,
                    'integration_id' => $integration->id,
                    'external_id' => $bookingDTO->externalId,
                    'guest_name' => $bookingDTO->guestName,
                    'check_in' => $bookingDTO->checkIn,
                    'check_out' => $bookingDTO->checkOut,
                    'source' => 'ical',
                ]);
            }

            $booking->update([
                'guest_name' => $bookingDTO->guestName,
                'check_in' => $bookingDTO->checkIn,
                'check_out' => $bookingDTO->checkOut,
            ]);

            return $booking;
        }

        return Booking::create([
            'place_id' => $place->id,
            'integration_id' => $integration->id,
            'external_id' => $bookingDTO->externalId,
            'guest_name' => $bookingDTO->guestName,
            'check_in' => $bookingDTO->checkIn,
            'check_out' => $bookingDTO->checkOut,
            'source' => 'ical',
        ]);
    }

    private function dedupeExistingBookings(Collection $existingBookings): Collection
    {
        if ($existingBookings->isEmpty()) {
            return $existingBookings;
        }

        $grouped = $existingBookings->groupBy('external_id');
        $keptIds = [];

        foreach ($grouped as $externalId => $bookings) {
            $bookings = $bookings->sortByDesc('updated_at')->values();
            $kept = $bookings->first();
            if ($kept) {
                $keptIds[] = $kept->id;
            }

            if ($bookings->count() > 1) {
                $bookings->slice(1)->each(function (Booking $booking): void {
                    $booking->deletion_reason = BookingDeletionReasonEnum::Other;
                    $booking->delete();
                });
            }
        }

        if (empty($keptIds)) {
            return collect([]);
        }

        return Booking::whereIn('id', $keptIds)->get()->keyBy('external_id');
    }
}
