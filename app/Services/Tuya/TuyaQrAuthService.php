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
    private const CLIENT_ID = 'HA_3y9q4ak7g4ephrvke';

    private const SCHEMA = 'tuyaSmart';

    private const BASE_URL = 'https://apigw.iotbing.com';

    /** Base URL para getDevices (API openapi com assinatura HMAC). */
    private const OPENAPI_BASE_URL = 'https://openapi.tuyaus.com';

    /** Guardado após generateQrCode para uso no pollLogin. */
    private string $userCode = '';

    /**
     * Etapa 1 — Gera o QR code para o user_code informado.
     * POST simples para apigw.iotbing.com, sem assinatura.
     */
    public function generateQrCode(string $userCode): ?TuyaQrCodeDTO
    {
        $this->userCode = $userCode;

        $url = self::BASE_URL.'/v1.0/m/life/home-assistant/qrcode/tokens'
            .'?clientid='.self::CLIENT_ID
            .'&usercode='.urlencode($userCode)
            .'&schema='.self::SCHEMA;

        $response = Http::post($url);

        if (! $response->successful()) {
            Log::error('TuyaQrAuthService generateQrCode HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();
        if (! ($data['success'] ?? false)) {
            Log::warning('TuyaQrAuthService generateQrCode API error', [
                'response' => $data,
            ]);

            return null;
        }

        $qrCode = data_get($data, 'result.qrcode');
        $expireTime = (int) data_get($data, 'result.expire_time', 300);

        if (! $qrCode || $qrCode === '') {
            Log::error('TuyaQrAuthService: qrcode ausente na resposta', ['response' => $data]);

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
     * GET simples para apigw.iotbing.com, sem assinatura.
     * Retorna null enquanto aguarda. Lança RuntimeException se o QR expirou.
     *
     * @param  string  $userCode  obrigatório quando o fluxo é stateless (ex.: nova instância do serviço a cada request)
     *
     * @throws \RuntimeException
     */
    public function pollLogin(string $qrCode, ?string $userCode = null): ?TuyaTokenDTO
    {
        $usercode = $userCode ?? $this->userCode;
        $url = self::BASE_URL.'/v1.0/m/life/home-assistant/qrcode/tokens/'.$qrCode
            .'?clientid='.self::CLIENT_ID
            .'&usercode='.urlencode($usercode);

        $response = Http::get($url);

        if (! $response->successful()) {
            Log::error('TuyaQrAuthService pollLogin HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Erro ao verificar o QR code. Tente novamente.');
        }

        $data = $response->json();

        if (! ($data['success'] ?? false)) {
            return null;
        }

        $accessToken = data_get($data, 'result.access_token');
        $refreshToken = data_get($data, 'result.refresh_token');
        $uid = data_get($data, 'result.uid');
        $expireTime = (int) data_get($data, 'result.expire_time', 7200);

        if (! $accessToken || ! $refreshToken || ! $uid) {
            Log::error('TuyaQrAuthService: resposta de login incompleta', ['response' => $data]);

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
     * Usa openapi.tuyaus.com com assinatura HMAC (credenciais do projeto cloud).
     *
     * @return TuyaDeviceDTO[]
     */
    public function getDevices(TuyaTokenDTO $token): array
    {
        $response = $this->signedGet("/v1.0/users/{$token->uid}/devices", [], $token->accessToken);

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
    // HTTP com assinatura HMAC-SHA256 (apenas para getDevices / openapi)
    // -------------------------------------------------------------------------

    private function signedGet(string $path, array $query = [], ?string $accessToken = null): ?array
    {
        $urlPath = $path.($query ? ('?'.http_build_query($query)) : '');

        return $this->signedRequest('GET', $urlPath, null, $accessToken);
    }

    private function signedRequest(string $method, string $urlPath, ?array $body, ?string $accessToken): ?array
    {
        $clientId = config('tuya.client_id');
        $clientSecret = config('tuya.client_secret');
        $timestamp = (string) (now()->timestamp * 1000);
        $nonce = '';

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

        $http = Http::withHeaders($headers)->baseUrl(self::OPENAPI_BASE_URL);

        $response = match ($method) {
            'GET' => $http->get($urlPath),
            'POST' => $jsonBody !== null
                ? $http->withBody($jsonBody, 'application/json')->post($urlPath)
                : $http->post($urlPath, []),
            default => $http->send($method, $urlPath),
        };

        if (! $response->successful()) {
            Log::error('TuyaQrAuthService signedRequest HTTP error', [
                'method' => $method,
                'path' => $urlPath,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();

        if (! ($data['success'] ?? false)) {
            Log::warning('TuyaQrAuthService signedRequest API error', [
                'method' => $method,
                'path' => $urlPath,
                'code' => $data['code'] ?? null,
                'msg' => $data['msg'] ?? null,
            ]);

            return $data;
        }

        return $data;
    }

    private function buildStringToSign(string $method, string $urlPath, ?array $body, ?string $jsonBody = null): string
    {
        $raw = $jsonBody ?? ($body !== null ? json_encode($body) : '');
        $hashedBody = hash('sha256', $raw);

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
        $str = $accessToken !== ''
            ? $clientId.$accessToken.$timestamp.$nonce.$stringToSign
            : $clientId.$timestamp.$nonce.$stringToSign;

        return strtoupper(hash_hmac('sha256', $str, $clientSecret, false));
    }
}
