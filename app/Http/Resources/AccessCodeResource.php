<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AccessCode
 */
class AccessCodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'place_id' => $this->place_id,
            'user_id' => $this->user_id,
            'booking_id' => $this->booking_id,
            'pin' => $this->pin,
            'start' => $this->start?->toIso8601String(),
            'end' => $this->end?->toIso8601String(),
            'display_name' => $this->display_name,
            'is_valid' => $this->isValid(),
            'place' => new PlaceResource($this->whenLoaded('place')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
