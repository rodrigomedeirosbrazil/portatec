<?php

declare(strict_types=1);

namespace App\Services\Tuya;

use App\Models\Place;
use App\Models\TuyaCredential;
use Illuminate\Support\Facades\Log;

class TuyaClientFactory
{
    private const REFRESH_BUFFER_SECONDS = 300;

    public function clientForPlace(Place $place): ?Client
    {
        $credential = $place->tuyaCredential;
        if ($credential === null) {
            return null;
        }

        if ($credential->isExpiredOrExpiringSoon(self::REFRESH_BUFFER_SECONDS)) {
            $refreshed = $this->refreshCredential($credential);
            if ($refreshed === null) {
                return null;
            }
            $credential = $refreshed;
        }

        $client = Client::fromConfig($credential->access_token);

        return $client;
    }

    public function refreshCredential(TuyaCredential $credential): ?TuyaCredential
    {
        $client = Client::fromConfig($credential->access_token);
        $dto = $client->refreshToken($credential->refresh_token, $credential->access_token);

        if ($dto === null) {
            Log::warning('Tuya token refresh failed', ['place_id' => $credential->place_id]);

            return null;
        }

        $credential->access_token = $dto->accessToken;
        $credential->refresh_token = $dto->refreshToken;
        $credential->expires_at = now()->addSeconds((int) $dto->expireTime);
        $credential->save();

        return $credential;
    }
}
