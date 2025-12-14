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

class ICalSyncService
{
    public function __construct(
        private ICalParserInterface $parser
    ) {}

    public function syncPlaceIntegration(int $placeId, int $integrationId): void
    {
        // Sincronizar um relacionamento específico Place-Integration
        $place = Place::findOrFail($placeId);
        $integration = Integration::findOrFail($integrationId);

        $placeIntegration = $place->integrations()
            ->where('integration_id', $integrationId)
            ->first();

        if (!$placeIntegration) {
            throw new \Exception("Place-Integration relationship not found");
        }

        if (!$placeIntegration->pivot->external_id) {
            throw new \Exception("External ID not configured for this Place-Integration relationship");
        }

        $externalId = $placeIntegration->pivot->external_id;

        // Baixar iCal via HTTP
        $icalContent = $this->downloadICal($externalId);

        // Parsear usando a classe fornecida
        $bookingDTOs = $this->parser->parse($icalContent);

        // Obter bookings existentes para este relacionamento
        $existingBookings = Booking::where('place_id', $placeId)
            ->where('integration_id', $integrationId)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('external_id');

        // Criar/atualizar bookings
        $currentExternalIds = [];
        foreach ($bookingDTOs as $bookingDTO) {
            $currentExternalIds[] = $bookingDTO->externalId;
            $this->createOrUpdateBooking($bookingDTO, $integration, $place, $existingBookings);
        }

        // Soft delete bookings que não estão mais no iCal
        $removedBookings = $existingBookings->whereNotIn('external_id', $currentExternalIds);
        foreach ($removedBookings as $booking) {
            $booking->deletion_reason = BookingDeletionReasonEnum::Canceled;
            $booking->delete();
        }
    }

    private function downloadICal(string $url): string
    {
        // Fazer requisição HTTP para baixar o iCal
        $response = Http::get($url);

        if (!$response->successful()) {
            throw new \Exception("Failed to download iCal from: {$url}");
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
                // Soft delete o booking antigo com reason (deletion_reason antes de delete())
                $booking->deletion_reason = $deletionReason;
                $booking->delete();

                // Criar novo booking
                return Booking::create([
                    'place_id' => $place->id,
                    'integration_id' => $integration->id,
                    'external_id' => $bookingDTO->externalId,
                    'guest_name' => $bookingDTO->guestName,
                    'check_in' => $bookingDTO->checkIn,
                    'check_out' => $bookingDTO->checkOut,
                ]);
            }

            // Sem mudanças, apenas atualizar se necessário
            $booking->update([
                'guest_name' => $bookingDTO->guestName,
                'check_in' => $bookingDTO->checkIn,
                'check_out' => $bookingDTO->checkOut,
            ]);

            return $booking;
        }

        // Criar novo booking
        return Booking::create([
            'place_id' => $place->id,
            'integration_id' => $integration->id,
            'external_id' => $bookingDTO->externalId,
            'guest_name' => $bookingDTO->guestName,
            'check_in' => $bookingDTO->checkIn,
            'check_out' => $bookingDTO->checkOut,
        ]);
    }
}
