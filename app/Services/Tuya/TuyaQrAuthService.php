<?php

declare(strict_types=1);

namespace App\Services\Tuya;

use App\Services\Tuya\DTOs\TuyaDeviceDTO;
use App\Services\Tuya\DTOs\TuyaQrCodeDTO;
use App\Services\Tuya\DTOs\TuyaTokenDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TuyaQrAuthService
{
    /**
     * Usa Cloud Authorization: client_id e client_secret do projeto no portal iot.tuya.com
     * (TUYA_CLIENT_ID e TUYA_CLIENT_SECRET no .env). O algoritmo de assinatura é o mesmo
     * do Client.php; o client_id fixo do app HA não pode ser usado fora do binário do HA.
     */
    private const SCHEMA = 'tuyaSmart';

    public const ENDPOINTS = [
        'América (padrão)' => 'https://openapi.tuyaus.com',
        'Europa' => 'https://openapi.tuyaeu.com',
        'China' => 'https://openapi.tuyacn.com',
        'Índia' => 'https://openapi.tuyain.com',
    ];

    public function __construct(
        private readonly string $endpoint = 'https://openapi.tuyaus.com',
    ) {}

    /**
     * Etapa 1 — Gera o QR code para o user_code informado.
     */
    public function generateQrCode(string $userCode): ?TuyaQrCodeDTO
    {
        $response = $this->post('/v1.0/iot-03/users/login', [
            'schema' => self::SCHEMA,
            'user_code' => $userCode,
        ]);

        if (! $response) {
            return null;
        }

        $qrCode = data_get($response, 'result.qrcode');
        $expireTime = (int) data_get($response, 'result.expire_time', 300);

        if (! $qrCode) {
            Log::error('TuyaQrAuthService: qrcode ausente na resposta', ['response' => $response]);
            $code = data_get($response, 'code');
            $msg = data_get($response, 'msg', '');
            if ((int) $code === 1004) {
                throw new \RuntimeException(
                    'Assinatura rejeitada (sign invalid). Verifique no .env: TUYA_CLIENT_ID e TUYA_CLIENT_SECRET '
                    .'do seu projeto em iot.tuya.com; região/endpoint compatível; API habilitada no projeto.'
                );
            }
            if ($msg !== '') {
                throw new \RuntimeException('Tuya API: '.$msg);
            }

            return null;
        }

        return new TuyaQrCodeDTO(
            qrCode: $qrCode,
            qrUrl: self::SCHEMA.'--qrLogin?token='.$qrCode,
            expireTime: now()->timestamp + $expireTime,
        );
    }

    /**
     * Etapa 2 — Polling: verifica se o usuário escaneou e confirmou o QR.
     * Retorna null enquanto aguarda. Lança RuntimeException se o QR expirou.
     *
     * @throws \RuntimeException
     */
    public function pollLogin(string $qrCode): ?TuyaTokenDTO
    {
        $response = $this->get('/v1.0/iot-03/users/login/status', [
            'schema' => self::SCHEMA,
            'qrcode' => $qrCode,
        ]);

        if (! $response) {
            return null;
        }

        $code = data_get($response, 'code');

        // 1000 = aguardando scan
        if ($code === 1000 || $code === '1000') {
            return null;
        }

        // QR expirado
        if (in_array($code, [1001, '1001', 1002, '1002'], true)) {
            throw new \RuntimeException('QR code expirado. Por favor, inicie o processo novamente.');
        }

        $accessToken = data_get($response, 'result.access_token');
        $refreshToken = data_get($response, 'result.refresh_token');
        $uid = data_get($response, 'result.uid');
        $expireTime = (int) data_get($response, 'result.expire_time', 7200);

        if (! $accessToken || ! $refreshToken || ! $uid) {
            Log::error('TuyaQrAuthService: resposta de login incompleta', ['response' => $response]);

            return null;
        }

        return new TuyaTokenDTO(
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expireTime: $expireTime,
            uid: $uid,
        );
    }

    /**
     * Etapa 3 — Busca todos os dispositivos da conta autenticada.
     *
     * @return TuyaDeviceDTO[]
     */
    public function getDevices(TuyaTokenDTO $token): array
    {
        $response = $this->get("/v1.0/users/{$token->uid}/devices", [], $token->accessToken);

        if (! $response) {
            return [];
        }

        return collect(data_get($response, 'result', []))
            ->map(fn (array $d) => new TuyaDeviceDTO(
                id: (string) ($d['id'] ?? ''),
                name: (string) ($d['name'] ?? 'Dispositivo sem nome'),
                category: (string) ($d['category'] ?? ''),
                online: (bool) ($d['online'] ?? false),
                productId: $d['product_id'] ?? null,
                productName: $d['product_name'] ?? null,
                icon: $d['icon'] ?? null,
                status: $d['status'] ?? [],
            ))
            ->filter(fn (TuyaDeviceDTO $d) => $d->id !== '')
            ->values()
            ->all();
    }

    // -------------------------------------------------------------------------
    // HTTP helpers com assinatura HMAC-SHA256
    // -------------------------------------------------------------------------

    private function get(string $path, array $query = [], ?string $accessToken = null): ?array
    {
        $urlPath = $path.($query ? ('?'.http_build_query($query)) : '');

        return $this->request('GET', $urlPath, null, $accessToken);
    }

    private function post(string $path, array $body, ?string $accessToken = null): ?array
    {
        return $this->request('POST', $path, $body, $accessToken);
    }

    private function request(string $method, string $urlPath, ?array $body, ?string $accessToken): ?array
    {
        $clientId = config('tuya.client_id');
        $clientSecret = config('tuya.client_secret');
        $timestamp = (string) (now()->timestamp * 1000);
        $nonce = '';

        // POST: usar o mesmo JSON no hash e no body para evitar "sign invalid"
        $jsonBody = ($method === 'POST' && $body !== null) ? json_encode($body) : null;
        $stringToSign = $this->buildStringToSign($method, $urlPath, $body, $jsonBody);
        $sign = $this->sign($clientId, $clientSecret, $accessToken ?? '', $timestamp, $nonce, $stringToSign);

        $headers = [
            'client_id' => $clientId,
            'sign' => $sign,
            't' => $timestamp,
            'sign_method' => 'HMAC-SHA256',
            'Content-Type' => 'application/json',
        ];

        if ($accessToken) {
            $headers['access_token'] = $accessToken;
        }

        $http = Http::withHeaders($headers)->baseUrl($this->endpoint);

        $response = match ($method) {
            'GET' => $http->get($urlPath),
            'POST' => $jsonBody !== null
                ? $http->withBody($jsonBody, 'application/json')->post($urlPath)
                : $http->post($urlPath, []),
            default => $http->send($method, $urlPath),
        };

        if (! $response->successful()) {
            Log::error('TuyaQrAuthService HTTP error', [
                'method' => $method,
                'path' => $urlPath,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();

        if (! ($data['success'] ?? false)) {
            Log::warning('TuyaQrAuthService API error', [
                'method' => $method,
                'path' => $urlPath,
                'code' => $data['code'] ?? null,
                'msg' => $data['msg'] ?? null,
            ]);

            // Retorna para o caller inspecionar code/msg (ex.: 1004 = sign invalid)
            return $data;
        }

        return $data;
    }

    private function buildStringToSign(string $method, string $urlPath, ?array $body, ?string $jsonBody = null): string
    {
        $raw = $jsonBody ?? ($body !== null ? json_encode($body) : '');
        $hashedBody = hash('sha256', $raw);

        // Tuya doc: "line-feed characters (\n)" entre as partes
        return $method."\n".$hashedBody."\n\n".$urlPath;
    }

    private function sign(
        string $clientId,
        string $clientSecret,
        string $accessToken,
        string $timestamp,
        string $nonce,
        string $stringToSign,
    ): string {
        // Token management API: str = client_id + t + nonce + stringToSign (sem access_token)
        $str = $accessToken !== ''
            ? $clientId.$accessToken.$timestamp.$nonce.$stringToSign
            : $clientId.$timestamp.$nonce.$stringToSign;

        return strtoupper(hash_hmac('sha256', $str, $clientSecret, false));
    }
}
