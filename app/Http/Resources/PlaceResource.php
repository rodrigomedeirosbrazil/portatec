<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Place
 */
class PlaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'devices_count' => $this->whenCounted('devices'),
            'bookings_count' => $this->whenCounted('bookings'),
            'access_codes_count' => $this->whenCounted('accessCodes'),
            'devices' => DeviceResource::collection($this->whenLoaded('devices')),
            'bookings' => BookingResource::collection($this->whenLoaded('bookings')),
            'access_codes' => AccessCodeResource::collection($this->whenLoaded('accessCodes')),
            'place_users' => PlaceUserResource::collection($this->whenLoaded('placeUsers')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
