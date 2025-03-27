<?php

namespace App\Services\Tuya;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
class TuyaService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function getDevices(string $uid)
    {
        if (
            ! $this->client->isAuthenticated()
            && ! $this->client->authenticate()
        ) {
            throw new \Exception('Failed to authenticate');
        }

        $urlPath = "/v1.0/users/{$uid}/devices";

        $response = $this->client->sendRequest(
            method: Request::METHOD_GET,
            urlPath: $urlPath,
        );

        if ($response->successful() && boolval($response->json('success', false))) {
            $data = json_decode($response->body(), true);
            return $data;
        }

        Log::error('Failed to get tuya devices', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return null;
    }
}
