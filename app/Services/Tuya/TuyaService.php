<?php

namespace App\Services\Tuya;

use App\Services\Tuya\DTOs\TuyaTicketDTO;
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

    public function sendPulse(string $deviceId): bool
    {
        if (
            ! $this->client->isAuthenticated()
            && ! $this->client->authenticate()
        ) {
            throw new \Exception('Failed to authenticate');
        }

        $urlPath = "/v1.0/iot-03/devices/{$deviceId}/commands";

        $body = [
            'commands' => [
                [
                    'code' => 'switch_1',
                    'value' => true,
                ],
                [
                    'code' => 'countdown_1',
                    'value' => 1,
                ],
            ],
        ];

        $response = $this->client->sendRequest(
            method: Request::METHOD_POST,
            urlPath: $urlPath,
            body: $body,
        );

        if ($response->successful() && boolval($response->json('success', false))) {
            return true;
        }

        Log::error('Failed to send pulse', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return false;
    }

    public function getPasswordTicket(string $deviceId): ?TuyaTicketDTO
    {
        if (
            ! $this->client->isAuthenticated()
            && ! $this->client->authenticate()
        ) {
            throw new \Exception('Failed to authenticate');
        }

        $urlPath = "/v1.0/devices/{$deviceId}/door-lock/password-ticket";

        $response = $this->client->sendRequest(
            method: Request::METHOD_POST,
            urlPath: $urlPath,
        );

        if ($response->successful() && boolval($response->json('success', false))) {
            $data = json_decode($response->body(), true);
            return new TuyaTicketDTO(
                ticketId: data_get($data, 'result.ticket_id'),
                ticketKey: data_get($data, 'result.ticket_key'),
                expireTime: data_get($data, 'result.expire_time'),
            );
        }

        Log::error('Failed to get tuya ticket', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return null;
    }

    public function decryptTicketKey(string $clientSecret, TuyaTicketDTO $ticket)
    {
        $data = hex2bin($ticket->ticketKey);
		$cipherMethod = 'aes-256-ecb';
		$options = OPENSSL_RAW_DATA;
		$keyUtf8 = mb_convert_encoding($clientSecret, 'UTF-8', 'ISO-8859-1');
		return openssl_decrypt($data, $cipherMethod, $keyUtf8, $options);
    }

    public function encryptPasswordWithTicket(string $clientSecret, string $password, TuyaTicketDTO $ticket): ?string
    {
        $decriptedKey = $this->decryptTicketKey($clientSecret, $ticket);
        $decryptKeyHex = bin2hex($decriptedKey);

		$cipherMethod = 'aes-128-ecb';
		$options = OPENSSL_RAW_DATA;

		$binaryPassword = openssl_encrypt($password, $cipherMethod, hex2bin($decryptKeyHex), $options);

		if ($binaryPassword === false) {
			return null;
		}

		$encryptedPassword = bin2hex($binaryPassword);

		return $encryptedPassword;
    }

    public function createTemporaryPassword(
        string $deviceId,
        string $name,
        string $password,
        ?int $effectiveTime = null,
        ?int $invalidTime = null,
        ?int $type = null,
    ) : ?int {
        if (
            ! $this->client->isAuthenticated()
            && ! $this->client->authenticate()
        ) {
            throw new \Exception('Failed to authenticate');
        }

        $ticket = $this->getPasswordTicket($deviceId);

        $encryptedPassword = $this->encryptPasswordWithTicket($this->client->getClientSecret(), $password, $ticket);

        $urlPath = "/v1.0/devices/{$deviceId}/door-lock/temp-password";

        if (! $effectiveTime) {
            $effectiveTime = now()->timestamp;
        }

        if (! $invalidTime) {
            $invalidTime = now()->addDay()->timestamp;
        }

        if (! $type) {
            $type = 0;
        }

        $body = [
            'device_id' => $deviceId,
            'name' => $name,
            'password' => $encryptedPassword,
            'effective_time' => $effectiveTime,
            'invalid_time' => $invalidTime,
            'password_type' => 'ticket',
            'ticket_id' => $ticket->ticketId,
            'type' => $type,
        ];

        $response = $this->client->sendRequest(
            method: Request::METHOD_POST,
            urlPath: $urlPath,
            body: $body
        );

        if ($response->successful() && boolval($response->json('success', false))) {
            $data = json_decode($response->body(), true);
            return data_get($data, 'result.id');
        }

        Log::error('Failed to create temporary password', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return null;
    }
}
