<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

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
            // `default_pin` abre a porta: vai no payload enviado ao
            // equipamento e serve de fallback no evento de acesso. Num
            // dispositivo compartilhado por 16 unidades, mandá-lo para todo
            // mundo que pode VER o dispositivo entrega a credencial do portão
            // do condomínio a cada morador. Só o admin do dispositivo recebe.
            'default_pin' => $this->when($this->viewerIsDeviceAdmin(), fn () => $this->default_pin),
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
            'places' => PlaceResource::collection($this->whenLoaded('places', fn () => $this->visiblePlacesFor(Auth::user()))),
            'place' => new PlaceResource($this->whenLoaded('place')),
            'integration' => new IntegrationResource($this->whenLoaded('integration')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function viewerIsDeviceAdmin(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $this->isAdministeredBy($user);
    }
}
