<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Booking
 */
class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'place_id' => $this->place_id,
            'integration_id' => $this->integration_id,
            'guest_name' => $this->guest_name,
            'check_in' => $this->check_in?->toIso8601String(),
            'check_out' => $this->check_out?->toIso8601String(),
            'source' => $this->source,
            'external_id' => $this->external_id,
            'deletion_reason' => $this->deletion_reason?->value,
            'status' => $this->resolveStatus(),
            'place' => new PlaceResource($this->whenLoaded('place')),
            'access_code' => new AccessCodeResource($this->whenLoaded('accessCode')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return 'current'|'future'|'past'|null
     */
    private function resolveStatus(): ?string
    {
        if ($this->check_in === null || $this->check_out === null) {
            return null;
        }

        $now = now();

        if ($this->check_in <= $now && $this->check_out >= $now) {
            return 'current';
        }

        if ($this->check_in > $now) {
            return 'future';
        }

        return 'past';
    }
}
