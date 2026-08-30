<?php

declare(strict_types=1);

namespace App\Services\Tuya;

use App\Services\Tuya\DTOs\TuyaQrCodeDTO;
use App\Services\Tuya\DTOs\TuyaTokenDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TuyaQrAuthService
{
    private const CLIENT_ID = 'HA_3y9q4ak7g4ephrvke';

    private const SCHEMA = 'haauthorize';

    /** Prefixo do conteúdo do QR — diferente do parâmetro `schema` da requisição. */
    private const QR_SCHEMA = 'tuyaSmart';

    private const BASE_URL = 'https://apigw.iotbing.com';

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
            qrUrl: self::QR_SCHEMA.'--qrLogin?token='.$qrCode,
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
            terminalId: data_get($data, 'result.terminal_id'),
            serverTime: is_numeric($data['t'] ?? null) ? (int) $data['t'] : null,
        );
    }
}
