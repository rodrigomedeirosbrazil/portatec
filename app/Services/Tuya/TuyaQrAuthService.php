<?php

declare(strict_types=1);

namespace App\Services\Tuya;

use App\Services\Tuya\DTOs\TuyaDeviceDTO;
use App\Services\Tuya\DTOs\TuyaQrCodeDTO;
use App\Services\Tuya\DTOs\TuyaTokenDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TuyaQrAuthService
{
    private const CLIENT_ID = 'HA_3y9q4ak7g4ephrvke';

    private const SCHEMA = 'tuyaSmart';

    private const BASE_URL = 'https://apigw.iotbing.com';

    private const NONCE_ALPHABET = 'ABCDEFGHJKMNPQRSTWXYZabcdefhijkmnprstwxyz2345678';

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
        $endpoint = data_get($data, 'result.endpoint')
            ?? data_get($data, 'result.end_point')
            ?? data_get($data, 'result.endPoint')
            ?? data_get($data, 'result.endpointUrl')
            ?? data_get($data, 'result.endpoint_url');
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
            endpoint: is_string($endpoint) ? $endpoint : null,
        );
    }

    /**
     * Etapa 3 — Busca todos os dispositivos da conta autenticada.
     * Usa CustomerApi no endpoint retornado pelo login QR (fallback apigw.iotbing.com),
     * com homes + devices por home, AES-GCM + X-sign.
     *
     * @return TuyaDeviceDTO[]
     */
    public function getDevices(TuyaTokenDTO $token): array
    {
        $homesResponse = $this->customerRequest(
            'GET',
            '/v1.0/m/life/users/homes',
            $token->accessToken,
            $token->refreshToken,
            endpoint: $token->endpoint
        );

        if ($homesResponse === null || ! is_array($homesResponse)) {
            return [];
        }

        $homes = $homesResponse['list']
            ?? $homesResponse['homes']
            ?? $homesResponse;
        if (! is_array($homes)) {
            return [];
        }

        $allDevices = [];
        foreach ($homes as $home) {
            $homeId = $home['ownerId']
                ?? $home['homeId']
                ?? $home['home_id']
                ?? $home['id']
                ?? null;
            if ($homeId === null || $homeId === '') {
                continue;
            }
            $devicesResponse = $this->customerRequest(
                'GET',
                '/v1.0/m/life/ha/home/devices',
                $token->accessToken,
                $token->refreshToken,
                ['homeId' => (string) $homeId],
                endpoint: $token->endpoint
            );
            if ($devicesResponse === null || ! is_array($devicesResponse)) {
                continue;
            }
            $devices = $devicesResponse['list']
                ?? $devicesResponse['devices']
                ?? $devicesResponse;
            if (! is_array($devices)) {
                continue;
            }
            foreach ($devices as $d) {
                $allDevices[] = $d;
            }
        }

        return collect($allDevices)
            ->map(fn (array $d) => new TuyaDeviceDTO(
                id: (string) ($d['id'] ?? $d['deviceId'] ?? ''),
                name: (string) ($d['name'] ?? 'Dispositivo sem nome'),
                category: (string) ($d['category'] ?? ''),
                online: (bool) ($d['online'] ?? false),
                productId: $d['product_id'] ?? $d['productId'] ?? null,
                productName: $d['product_name'] ?? $d['productName'] ?? null,
                icon: $d['icon'] ?? null,
                status: $d['status'] ?? [],
            ))
            ->filter(fn (TuyaDeviceDTO $d) => $d->id !== '')
            ->values()
            ->all();
    }

    // -------------------------------------------------------------------------
    // CustomerApi: AES-GCM + X-sign (apigw.iotbing.com)
    // -------------------------------------------------------------------------

    private function randomNonce(int $length = 12): string
    {
        $chars = self::NONCE_ALPHABET;
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $result;
    }

    private function aesGcmEncrypt(string $data, string $secret): string
    {
        $nonce = $this->randomNonce(12);
        $tag = '';
        $encrypted = openssl_encrypt(
            $data,
            'aes-128-gcm',
            $secret,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );
        if ($encrypted === false) {
            throw new \RuntimeException('AES-GCM encrypt failed');
        }

        return base64_encode($nonce).base64_encode($encrypted.$tag);
    }

    private function aesGcmDecrypt(string $cipherData, string $secret): string
    {
        $raw = base64_decode($cipherData, true);
        if ($raw === false || strlen($raw) < 12 + 16) {
            throw new \RuntimeException('Invalid cipher data for AES-GCM decrypt');
        }
        $nonce = substr($raw, 0, 12);
        $tag = substr($raw, -16);
        $ciphertext = substr($raw, 12, -16);
        $decrypted = openssl_decrypt(
            $ciphertext,
            'aes-128-gcm',
            $secret,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );
        if ($decrypted === false) {
            throw new \RuntimeException('AES-GCM decrypt failed');
        }

        return $decrypted;
    }

    /**
     * Requisição autenticada ao CustomerApi (apigw.iotbing.com).
     * Criptografa params/body com AES-GCM e assina com X-sign.
     *
     * @return array<string, mixed>|null decoded result (array) or null on error
     */
    public function customerRequest(
        string $method,
        string $path,
        string $accessToken,
        string $refreshToken,
        ?array $params = null,
        ?array $body = null,
        ?string $endpoint = null,
    ): ?array {
        $rid = (string) Str::uuid();
        $sid = '';
        $hashKey = md5($rid.$refreshToken);
        $secret = substr(hash_hmac('sha256', $hashKey, $rid), 0, 16);
        $timestamp = (string) (int) (microtime(true) * 1000);

        $queryEncdata = null;
        $bodyEncdata = null;
        $bodyJson = null;

        if ($params !== null && $params !== []) {
            $paramsJson = json_encode($params, JSON_THROW_ON_ERROR);
            $queryEncdata = $this->aesGcmEncrypt($paramsJson, $secret);
        }
        if ($body !== null && $body !== []) {
            $bodyJson = json_encode($body, JSON_THROW_ON_ERROR);
            $bodyEncdata = $this->aesGcmEncrypt($bodyJson, $secret);
        }

        $headers = [
            'Accept' => 'application/json',
            'X-appKey' => self::CLIENT_ID,
            'X-requestId' => $rid,
            'X-sid' => $sid,
            'X-time' => $timestamp,
            'X-token' => $accessToken,
        ];

        // Match Python _restful_sign: only include header in sign_str when value != ""
        $signParts = [];
        foreach (['X-appKey' => self::CLIENT_ID, 'X-requestId' => $rid, 'X-sid' => $sid, 'X-time' => $timestamp, 'X-token' => $accessToken] as $key => $val) {
            if ($val !== '') {
                $signParts[] = $key.'='.$val;
            }
        }
        $signStr = implode('||', $signParts);
        if ($queryEncdata !== null) {
            $signStr .= $queryEncdata;
        }
        if ($bodyEncdata !== null) {
            $signStr .= $bodyEncdata;
        }
        $headers['X-sign'] = hash_hmac('sha256', $signStr, $hashKey);

        $baseUrl = $this->normalizeEndpoint($endpoint);
        $url = $baseUrl.$path;
        if ($queryEncdata !== null) {
            $url .= (str_contains($path, '?') ? '&' : '?').'encdata='.urlencode($queryEncdata);
        }

        // Diagnóstico sign invalid — remover antes de produção
        Log::debug('[Tuya] customerRequest debug', [
            'method' => $method,
            'path' => $path,
            'body_json' => $bodyJson,
            'body_encdata_len' => $bodyEncdata !== null ? strlen($bodyEncdata) : null,
            'body_encdata_prefix' => $bodyEncdata !== null ? substr($bodyEncdata, 0, 40) : null,
            'sign_str_prefix' => substr($signStr, 0, 100),
            'url' => $url,
        ]);

        $http = Http::withHeaders($headers)->timeout(15);
        if ($bodyEncdata !== null) {
            $response = $http->withBody(
                json_encode(['encdata' => $bodyEncdata], JSON_THROW_ON_ERROR),
                'application/json'
            )->post($url);
        } else {
            $response = $http->get($url);
        }

        // Diagnóstico sign invalid — remover antes de produção
        Log::debug('[Tuya] customerRequest response raw', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if (! $response->successful()) {
            Log::error('TuyaQrAuthService customerRequest HTTP error', [
                'method' => $method,
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();
        if (! ($data['success'] ?? false)) {
            Log::warning('TuyaQrAuthService customerRequest API error', [
                'path' => $path,
                'code' => $data['code'] ?? null,
                'msg' => $data['msg'] ?? null,
            ]);

            return null;
        }

        $encryptedResult = $data['result'] ?? null;
        if ($encryptedResult === null) {
            return [];
        }
        if (is_array($encryptedResult)) {
            return $encryptedResult;
        }

        try {
            $decrypted = $this->aesGcmDecrypt((string) $encryptedResult, $secret);
            $decoded = json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            Log::warning('TuyaQrAuthService customerRequest decrypt failed', [
                'path' => $path,
                'message' => $e->getMessage(),
                'result_length' => is_string($encryptedResult) ? strlen($encryptedResult) : null,
            ]);

            return null;
        }
    }

    private function normalizeEndpoint(?string $endpoint): string
    {
        $endpoint = trim((string) $endpoint);
        if ($endpoint === '') {
            return self::BASE_URL;
        }
        if (! str_starts_with($endpoint, 'http://') && ! str_starts_with($endpoint, 'https://')) {
            $endpoint = 'https://'.$endpoint;
        }

        return rtrim($endpoint, '/');
    }
}
