<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\DeviceBrandEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTuyaConnectRequest;
use App\Models\Device;
use App\Models\Integration;
use App\Models\Platform;
use App\Services\Tuya\DTOs\TuyaTokenDTO;
use App\Services\Tuya\TuyaIntegrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tuya QR-login wizard — see `App\Livewire\Integrations\TuyaConnect`.
 *
 * Security: the wizard's intermediate state (QR polling token, Tuya
 * access/refresh token, discovered devices with their metadata) lives in the
 * session under `tuya_connect.*`, never as an Inertia prop — see
 * `TuyaQrController` for the step-by-step flow. This controller only renders
 * the current step (`create`) and finalizes the integration (`store`), and
 * `store` reads the selected devices' metadata back from the session, never
 * from the request payload.
 */
class TuyaConnectController extends Controller
{
    public function create(Request $request): Response
    {
        $session = $request->session();

        return Inertia::render('devices/integrations/tuya-connect', [
            'step' => $session->get('tuya_connect.step', 'form'),
            'qrUrl' => $session->get('tuya_connect.qr_url'),
            'qrExpiresAt' => $session->get('tuya_connect.qr_expires_at'),
            'devices' => $session->get('tuya_connect.devices', []),
            'errorMessage' => $session->get('tuya_connect.error'),
        ]);
    }

    /**
     * Replaces `TuyaConnect::saveIntegration()`. Category B mutation
     * (migration spec §5.1): persists the integration and imports the
     * selected devices once the QR flow (Category C, `TuyaQrController`) has
     * produced a token.
     */
    public function store(StoreTuyaConnectRequest $request): RedirectResponse
    {
        $session = $request->session();
        $tokenData = $session->get('tuya_connect.token');

        if (! is_array($tokenData)) {
            $session->put('tuya_connect.step', 'form');
            $session->put('tuya_connect.error', __('app.tuya_session_expired'));

            return redirect()->route('app.devices.integrations.tuya-connect');
        }

        $userCode = (string) $session->get('tuya_connect.user_code', '');

        $token = new TuyaTokenDTO(
            accessToken: $tokenData['access_token'],
            refreshToken: $tokenData['refresh_token'],
            expireTime: (int) $tokenData['expire_time'],
            uid: $tokenData['uid'],
            endpoint: $tokenData['endpoint'] ?? null,
            terminalId: $tokenData['terminal_id'] ?? null,
            serverTime: isset($tokenData['server_time']) ? (int) $tokenData['server_time'] : null,
        );

        $expiresAt = $token->serverTime !== null
            ? now()->setTimestamp(intdiv($token->serverTime, 1000))->addSeconds($token->expireTime)
            : now()->addSeconds($token->expireTime);

        $platform = Platform::query()->firstOrCreate(
            ['slug' => 'tuya'],
            ['name' => 'Tuya SmartLife'],
        );

        $integration = Integration::updateOrCreate(
            [
                'platform_id' => $platform->id,
                'user_id' => Auth::id(),
                'tuya_uid' => $token->uid,
            ],
            [
                'tuya_user_code' => $userCode,
                'tuya_access_token' => $token->accessToken,
                'tuya_refresh_token' => $token->refreshToken,
                'tuya_token_expires_at' => $expiresAt,
                'tuya_endpoint' => $token->endpoint,
                'tuya_terminal_id' => $token->terminalId,
            ],
        );

        // Security: device metadata (name, category, productId, productName,
        // icon, status) comes from the session snapshot taken during the QR
        // poll — never from the client payload, which only carries the
        // selected IDs. A forged ID that isn't in that snapshot is ignored.
        $devicesById = collect($session->get('tuya_connect.devices', []))->keyBy('id');

        foreach ($request->validated('device_ids') as $id) {
            $meta = $devicesById->get($id);

            if (! is_array($meta)) {
                continue;
            }

            $device = Device::updateOrCreate(
                ['external_device_id' => $meta['id']],
                [
                    'name' => $meta['name'],
                    'integration_id' => $integration->id,
                    'brand' => DeviceBrandEnum::Tuya,
                    'external_device_id' => $meta['id'],
                    'tuya_category' => $meta['category'] ?? null,
                    'tuya_product_id' => $meta['productId'] ?? null,
                    'tuya_product_name' => $meta['productName'] ?? null,
                    'tuya_icon' => $meta['icon'] ?? null,
                    'tuya_online' => $meta['online'] ?? null,
                    'tuya_status_payload' => $meta['status'] ?? [],
                    'last_sync' => now(),
                ]
            );
            $device->deviceUsers()->firstOrCreate(['user_id' => Auth::id()]);

            try {
                app(TuyaIntegrationService::class)->syncDeviceSpecifications($device);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $session->forget('tuya_connect');
        $session->put('tuya_connect.step', 'done');

        return redirect()
            ->route('app.devices.integrations.tuya-connect')
            ->with('status', __('app.tuya_connected_flash'));
    }
}
