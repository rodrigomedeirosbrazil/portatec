<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AccessCodeDeviceSync
 */
class AccessCodeDeviceSyncResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'access_code_id' => $this->access_code_id,
            'device_id' => $this->device_id,
            'provider' => $this->provider,
            'external_reference' => $this->external_reference,
            'synced_start' => $this->synced_start?->toIso8601String(),
            'synced_end' => $this->synced_end?->toIso8601String(),
            'synced_pin' => $this->synced_pin,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'status' => $this->status,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
