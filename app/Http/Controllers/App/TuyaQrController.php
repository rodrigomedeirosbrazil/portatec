<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTuyaQrRequest;
use App\Models\Integration;
use App\Services\Tuya\DTOs\TuyaDeviceDTO;
use App\Services\Tuya\TuyaIntegrationService;
use App\Services\Tuya\TuyaQrAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Category C endpoints (migration spec §5.1) for the Tuya QR-login wizard —
 * see `App\Livewire\Integrations\TuyaConnect`.
 *
 * Security: the QR polling token, the Tuya access/refresh token and the
 * imported devices' metadata never leave the server. They live in the
 * session under the `tuya_connect.*` keys and are read back from there by
 * `TuyaConnectController::store()` — never trusted from the request payload.
 * Only the wizard step, the public `qrUrl`, its expiry, the device list
 * *without secrets*, and error messages are ever sent to the client.
 */
class TuyaQrController extends Controller
{
    /**
     * Replaces `TuyaConnect::generateQr()`.
     */
    public function store(StoreTuyaQrRequest $request): JsonResponse
    {
        $userCode = trim((string) $request->validated('user_code'));

        try {
            $dto = (new TuyaQrAuthService)->generateQrCode($userCode);
        } catch (\RuntimeException $e) {
            return response()->json(['errorMessage' => $e->getMessage()], 422);
        }

        if (! $dto) {
            return response()->json(['errorMessage' => __('app.tuya_qr_generation_failed')], 422);
        }

        $request->session()->forget('tuya_connect');
        $request->session()->put('tuya_connect.step', 'qr');
        $request->session()->put('tuya_connect.user_code', $userCode);
        $request->session()->put('tuya_connect.qr_code', $dto->qrCode);
        $request->session()->put('tuya_connect.qr_url', $dto->qrUrl);
        $request->session()->put('tuya_connect.qr_expires_at', $dto->expireTime);

        return response()->json([
            'step' => 'qr',
            'qrUrl' => $dto->qrUrl,
            'qrExpiresAt' => $dto->expireTime,
        ]);
    }

    /**
     * Replaces `TuyaConnect::pollQr()`. Polled by the client every 3s while
     * `step === 'qr'` (the Livewire equivalent of `wire:poll.3000ms`); the
     * client is responsible for stopping the polling once the step changes
     * or the component unmounts, since there is no server-driven poll here.
     */
    public function show(Request $request): JsonResponse
    {
        $session = $request->session();

        if ($session->get('tuya_connect.step') !== 'qr') {
            return response()->json(['status' => 'pending']);
        }

        $qrCode = (string) $session->get('tuya_connect.qr_code', '');
        $userCode = (string) $session->get('tuya_connect.user_code', '');

        try {
            $token = (new TuyaQrAuthService)->pollLogin($qrCode, $userCode);

            if ($token === null) {
                return response()->json(['status' => 'pending']);
            }

            $session->put('tuya_connect.token', [
                'access_token' => $token->accessToken,
                'refresh_token' => $token->refreshToken,
                'expire_time' => $token->expireTime,
                'uid' => $token->uid,
                'endpoint' => $token->endpoint,
                'terminal_id' => $token->terminalId,
                'server_time' => $token->serverTime,
            ]);

            $probe = new Integration([
                'tuya_access_token' => $token->accessToken,
                'tuya_refresh_token' => $token->refreshToken,
                'tuya_endpoint' => $token->endpoint,
            ]);
            $probe->tuya_token_expires_at = now()->addSeconds($token->expireTime);

            $deviceDtos = app(TuyaIntegrationService::class)->listDevices($probe)->all();

            $devices = array_values(array_map(
                fn (TuyaDeviceDTO $d): array => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'category' => $d->category,
                    'categoryLabel' => $d->categoryLabel(),
                    'online' => $d->online,
                    'productId' => $d->productId,
                    'productName' => $d->productName,
                    'icon' => $d->icon,
                    'status' => $d->status,
                    'selected' => TuyaDeviceDTO::isAccessCategory($d->category),
                ],
                $deviceDtos
            ));

            $session->put('tuya_connect.devices', $devices);
            $session->put('tuya_connect.step', 'devices');
            $session->forget('tuya_connect.error');

            return response()->json([
                'status' => 'confirmed',
                'step' => 'devices',
                'devices' => $devices,
            ]);
        } catch (\RuntimeException $e) {
            $session->put('tuya_connect.step', 'form');
            $session->forget([
                'tuya_connect.qr_url',
                'tuya_connect.qr_expires_at',
                'tuya_connect.qr_code',
            ]);
            $session->put('tuya_connect.error', $e->getMessage());

            return response()->json([
                'status' => 'error',
                'step' => 'form',
                'errorMessage' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Replaces `TuyaConnect::resetQr()`: clears the whole wizard state.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->session()->forget('tuya_connect');

        return response()->json(['status' => 'reset', 'step' => 'form']);
    }
}
