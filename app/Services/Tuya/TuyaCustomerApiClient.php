<?php

declare(strict_types=1);

namespace App\Services\Tuya;

use App\Exceptions\TuyaApiException;
use App\Models\Integration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Camada CustomerApi do device sharing da Tuya.
 * Port de tuya_sharing/customerapi.py (CustomerApi.__request).
 */
class TuyaCustomerApiClient
{
    public const CLIENT_ID = 'HA_3y9q4ak7g4ephrvke';

    public const BASE_URL = 'https://apigw.iotbing.com';

    private const NONCE_ALPHABET = 'ABCDEFGHJKMNPQRSTWXYZabcdefhijkmnprstwxyz2345678';

    /** Evita recursão infinita quando o próprio refresh dispara um request. */
    private bool $refreshing = false;

    /** @param array<string, mixed>|null $params */
    public function get(Integration $integration, string $path, ?array $params = null): mixed
    {
        return $this->request($integration, 'GET', $path, $params, null);
    }

    /**
     * @param  array<string, mixed>|null  $params
     * @param  array<string, mixed>|null  $body
     */
    public function post(Integration $integration, string $path, ?array $params = null, ?array $body = null): mixed
    {
        return $this->request($integration, 'POST', $path, $params, $body);
    }

    /**
     * @param  array<string, mixed>|null  $params
     * @param  array<string, mixed>|null  $body
     *
     * @throws TuyaApiException
     */
    private function request(
        Integration $integration,
        string $method,
        string $path,
        ?array $params,
        ?array $body,
    ): mixed {
        $this->refreshTokenIfNeeded($integration);

        $rid = (string) Str::uuid();
        $sid = '';
        $hashKey = md5($rid.(string) $integration->tuya_refresh_token);
        $secret = substr(hash_hmac('sha256', $hashKey, $rid), 0, 16);
        $timestamp = (string) (int) (microtime(true) * 1000);

        $queryEncdata = ($params === null || $params === [])
            ? null
            : $this->encrypt($this->toJson($params), $secret);

        $bodyEncdata = ($body === null || $body === [])
            ? null
            : $this->encrypt($this->toJson($body), $secret);

        $headers = [
            'Accept' => 'application/json',
            'X-appKey' => self::CLIENT_ID,
            'X-requestId' => $rid,
            'X-sid' => $sid,
            'X-time' => $timestamp,
            'X-token' => (string) $integration->tuya_access_token,
        ];

        $headers['X-sign'] = $this->sign($headers, $hashKey, $queryEncdata, $bodyEncdata);

        $url = $this->endpoint($integration).$path;
        if ($queryEncdata !== null) {
            $url .= (str_contains($path, '?') ? '&' : '?').'encdata='.urlencode($queryEncdata);
        }

        $pending = Http::withHeaders($headers)->timeout(15);

        $response = $bodyEncdata === null
            ? $pending->send($method, $url)
            : $pending->withBody(
                $this->toJson(['encdata' => $bodyEncdata]),
                'application/json'
            )->send($method, $url);

        if (! $response->successful()) {
            throw TuyaApiException::http($path, $response->status(), $response->body());
        }

        $data = $response->json();

        if (! is_array($data) || ! ($data['success'] ?? false)) {
            throw TuyaApiException::api(
                $path,
                isset($data['code']) ? (string) $data['code'] : null,
                isset($data['msg']) ? (string) $data['msg'] : null,
            );
        }

        return $this->decodeResult($data['result'] ?? null, $secret);
    }

    /**
     * O SDK renova quando falta menos de 1 min para expirar.
     * O refresh_token rotaciona — e como hash_key = MD5(rid + refresh_token),
     * usar um refresh_token velho produz "sign invalid".
     */
    private function refreshTokenIfNeeded(Integration $integration): void
    {
        if ($this->refreshing) {
            return;
        }

        $expiresAt = $integration->tuya_token_expires_at;
        if ($expiresAt !== null && $expiresAt->subMinute()->isFuture()) {
            return;
        }

        if (blank($integration->tuya_refresh_token)) {
            return;
        }

        $this->refreshing = true;

        try {
            $result = $this->request(
                $integration,
                'GET',
                '/v1.0/m/token/'.$integration->tuya_refresh_token,
                null,
                null,
            );

            if (! is_array($result)) {
                return;
            }

            $integration->forceFill([
                'tuya_access_token' => $result['accessToken'] ?? $integration->tuya_access_token,
                'tuya_refresh_token' => $result['refreshToken'] ?? $integration->tuya_refresh_token,
                'tuya_uid' => $result['uid'] ?? $integration->tuya_uid,
                'tuya_token_expires_at' => now()->addSeconds((int) ($result['expireTime'] ?? 7200)),
            ])->save();
        } finally {
            $this->refreshing = false;
        }
    }

    private function endpoint(Integration $integration): string
    {
        $endpoint = trim((string) $integration->tuya_endpoint);

        if ($endpoint === '') {
            return self::BASE_URL;
        }

        if (! str_starts_with($endpoint, 'http://') && ! str_starts_with($endpoint, 'https://')) {
            $endpoint = 'https://'.$endpoint;
        }

        return rtrim($endpoint, '/');
    }

    /** @param array<string, string> $headers */
    private function sign(array $headers, string $hashKey, ?string $queryEncdata, ?string $bodyEncdata): string
    {
        $parts = [];
        foreach (['X-appKey', 'X-requestId', 'X-sid', 'X-time', 'X-token'] as $key) {
            $value = $headers[$key] ?? '';
            if ($value !== '') {
                $parts[] = $key.'='.$value;
            }
        }

        $signStr = implode('||', $parts).($queryEncdata ?? '').($bodyEncdata ?? '');

        return hash_hmac('sha256', $signStr, $hashKey);
    }

    /** @param array<string, mixed> $value */
    private function toJson(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function randomNonce(int $length = 12): string
    {
        $alphabet = self::NONCE_ALPHABET;
        $max = strlen($alphabet) - 1;
        $nonce = '';
        for ($i = 0; $i < $length; $i++) {
            $nonce .= $alphabet[random_int(0, $max)];
        }

        return $nonce;
    }

    private function encrypt(string $data, string $secret): string
    {
        $nonce = $this->randomNonce();
        $tag = '';
        $cipher = openssl_encrypt($data, 'aes-128-gcm', $secret, OPENSSL_RAW_DATA, $nonce, $tag);

        if ($cipher === false) {
            throw new TuyaApiException('Falha ao cifrar payload Tuya (AES-GCM).');
        }

        return base64_encode($nonce).base64_encode($cipher.$tag);
    }

    /**
     * O `result` vem cifrado como string. Pode decodificar para array, bool, número ou string —
     * todos são resultados válidos e precisam ser distinguíveis de erro (que vira exceção).
     */
    private function decodeResult(mixed $result, string $secret): mixed
    {
        if ($result === null) {
            return null;
        }

        if (! is_string($result)) {
            return $result;
        }

        $raw = base64_decode($result, true);
        if ($raw === false || strlen($raw) < 28) {
            throw new TuyaApiException('Resposta Tuya cifrada com tamanho inválido.');
        }

        $plain = openssl_decrypt(
            substr($raw, 12, -16),
            'aes-128-gcm',
            $secret,
            OPENSSL_RAW_DATA,
            substr($raw, 0, 12),
            substr($raw, -16),
        );

        if ($plain === false) {
            throw new TuyaApiException('Falha ao decifrar resposta Tuya (AES-GCM).');
        }

        try {
            return json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $plain;
        }
    }
}
