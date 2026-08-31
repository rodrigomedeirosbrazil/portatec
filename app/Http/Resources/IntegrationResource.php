<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Integration
 *
 * Security: never expose `tuya_access_token`, `tuya_refresh_token`,
 * `tuya_terminal_id` or `tuya_user_code` — see the migration spec's security
 * note for §5 (App Resources). Only what the screen displays goes out.
 */
class IntegrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->whenLoaded('platform', fn () => [
                'id' => $this->platform->id,
                'name' => $this->platform->name,
                'slug' => $this->platform->slug,
            ]),
            'tuya_uid' => $this->maskUid($this->tuya_uid),
            'tuya_token_expires_at' => $this->tuya_token_expires_at?->toIso8601String(),
            'places' => $this->whenLoaded('places', fn () => $this->places->map(fn ($place) => [
                'id' => $place->id,
                'name' => $place->name,
                'external_id' => $place->pivot->external_id,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function maskUid(?string $uid): ?string
    {
        if ($uid === null || $uid === '') {
            return $uid;
        }

        if (strlen($uid) <= 4) {
            return str_repeat('*', strlen($uid));
        }

        return str_repeat('*', strlen($uid) - 4).substr($uid, -4);
    }
}
