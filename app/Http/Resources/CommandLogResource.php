<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CommandLog
 */
class CommandLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'command_id' => $this->command_id,
            'user_id' => $this->user_id,
            'place_id' => $this->place_id,
            'device_function_id' => $this->device_function_id,
            'command_type' => $this->command_type,
            'command_payload' => $this->command_payload,
            'device_function_type' => $this->device_function_type,
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
