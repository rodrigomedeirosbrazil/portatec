<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Device
 */
class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'external_device_id' => $this->external_device_id,
            'place_id' => $this->place_id,
            'integration_id' => $this->integration_id,
            'brand' => $this->brand?->value,
            'default_pin' => $this->default_pin,
            'last_sync' => $this->last_sync?->toIso8601String(),
            'wifi_strength' => $this->wifi_strength,
            'firmware_version' => $this->firmware_version,
            'tuya_category' => $this->tuya_category,
            'tuya_product_id' => $this->tuya_product_id,
            'tuya_product_name' => $this->tuya_product_name,
            'tuya_icon' => $this->tuya_icon,
            'tuya_online' => $this->tuya_online,
            'is_available' => $this->isAvailable(),
            'is_tuya_lock' => $this->isTuyaLock(),
            'supports_tuya_temporary_password' => $this->supportsTuyaTemporaryPassword(),
            'device_functions_count' => $this->whenCounted('deviceFunctions'),
            'device_functions' => DeviceFunctionResource::collection($this->whenLoaded('deviceFunctions')),
            'places' => PlaceResource::collection($this->whenLoaded('places')),
            'place' => new PlaceResource($this->whenLoaded('place')),
            'integration' => new IntegrationResource($this->whenLoaded('integration')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
