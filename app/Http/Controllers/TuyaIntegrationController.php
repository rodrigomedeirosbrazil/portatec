<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Place;
use App\Models\TuyaAccount;
use App\Models\TuyaDevice;
use App\Services\Tuya\TuyaSharingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TuyaIntegrationController extends Controller
{
    public function __construct(
        private TuyaSharingService $tuyaService
    ) {}

    public function showQRCode(): RedirectResponse|View
    {
        $account = TuyaAccount::query()
            ->where('user_id', auth()->id())
            ->where('active', true)
            ->first();

        if ($account) {
            return redirect()->route('app.tuya.devices');
        }

        return $this->renderConnectView();
    }

    public function startConnect(Request $request): View
    {
        $validated = $request->validate([
            'user_code' => ['required', 'string'],
        ]);

        $tokenData = $this->tuyaService->createQr($validated['user_code']);
        if (! $tokenData) {
            return $this->renderConnectView('Não foi possível gerar o QR code. Verifique o User Code e as credenciais de Device Sharing.');
        }

        $request->session()->put([
            'tuya_user_code' => $validated['user_code'],
            'tuya_qr_token' => $tokenData['token'],
            'tuya_qr_payload' => $tokenData['qr_payload'],
            'tuya_qr_expire_time' => $tokenData['expire_time'],
        ]);

        return $this->renderConnectView();
    }

    public function pollLogin(string $token): JsonResponse
    {
        $userCode = (string) session('tuya_user_code', '');
        if ($userCode === '') {
            return response()->json(['linked' => false, 'error' => 'missing_user_code']);
        }

        $status = $this->tuyaService->pollLogin($userCode, $token);
        if (! $status || ! $status['ok']) {
            return response()->json(['linked' => false]);
        }

        $tokenInfo = $status['token_info'] ?? [];
        $accessToken = $tokenInfo['access_token'] ?? null;
        $refreshToken = $tokenInfo['refresh_token'] ?? null;

        TuyaAccount::query()->updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'user_code' => $userCode,
                'uid' => $status['uid'] ?? '',
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_info' => $tokenInfo,
                'terminal_id' => $status['terminal_id'] ?? null,
                'endpoint' => $status['endpoint'] ?? null,
                'expires_at' => now()->addSeconds((int) ($status['expire_time'] ?? 7200)),
                'platform_url' => $status['endpoint'] ?? config('tuya.base_url'),
                'active' => true,
            ]
        );

        session()->forget(['tuya_qr_token', 'tuya_qr_payload', 'tuya_qr_expire_time']);

        return response()->json(['linked' => true]);
    }

    public function listDevices(): RedirectResponse|View
    {
        $account = TuyaAccount::query()
            ->where('user_id', auth()->id())
            ->where('active', true)
            ->first();

        if (! $account) {
            return redirect()->route('app.tuya.connect');
        }

        $this->tuyaService->listDevices($account);

        $devices = $account->tuyaDevices()->orderBy('name')->get();
        $places = Place::query()
            ->whereHas('placeUsers', fn ($q) => $q->where('user_id', auth()->id()))
            ->orderBy('name')
            ->get();

        return view('layouts.client', [
            'slot' => view('tuya.devices', [
                'devices' => $devices,
                'places' => $places,
            ]),
        ]);
    }

    public function assignDeviceToPlace(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'tuya_device_id' => ['required', 'integer', 'exists:tuya_devices,id'],
            'place_id' => ['nullable', 'integer', 'exists:places,id'],
        ]);

        $device = TuyaDevice::query()
            ->whereHas('tuyaAccount', fn ($q) => $q->where('user_id', auth()->id()))
            ->findOrFail($validated['tuya_device_id']);

        $placeId = $validated['place_id'] ?? null;
        if ($placeId !== null) {
            $canAccess = Place::query()
                ->where('id', $placeId)
                ->whereHas('placeUsers', fn ($q) => $q->where('user_id', auth()->id()))
                ->exists();
            if (! $canAccess) {
                abort(403);
            }
        }

        $device->place_id = $placeId;
        $device->enabled = true;
        $device->save();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('app.tuya.devices')->with('status', 'Dispositivo associado ao local.');
    }

    public function enableDevice(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tuya_device_id' => ['required', 'integer', 'exists:tuya_devices,id'],
            'enabled' => ['required', 'boolean'],
        ]);

        $device = TuyaDevice::query()
            ->whereHas('tuyaAccount', fn ($q) => $q->where('user_id', auth()->id()))
            ->findOrFail($validated['tuya_device_id']);

        $device->enabled = $validated['enabled'];
        $device->save();

        return redirect()->route('app.tuya.devices')->with('status', 'Dispositivo atualizado.');
    }

    public function sendCommand(Request $request, string $deviceId): JsonResponse
    {
        $validated = $request->validate([
            'commands' => ['required', 'array'],
            'commands.*.code' => ['required', 'string'],
            'commands.*.value' => ['required'],
        ]);

        $device = TuyaDevice::query()
            ->where('device_id', $deviceId)
            ->whereHas('tuyaAccount', fn ($q) => $q->where('user_id', auth()->id())->where('active', true))
            ->firstOrFail();

        $account = $device->tuyaAccount;
        $ok = $this->tuyaService->sendCommand($account, $deviceId, $validated['commands']);

        return response()->json(['success' => $ok]);
    }

    public function disconnect(): RedirectResponse
    {
        $account = TuyaAccount::query()
            ->where('user_id', auth()->id())
            ->where('active', true)
            ->first();

        if ($account) {
            $this->tuyaService->disconnect($account);
        }

        TuyaAccount::query()
            ->where('user_id', auth()->id())
            ->update(['active' => false]);

        return redirect()->route('app.dashboard')->with('status', 'Conta Tuya desvinculada.');
    }

    private function renderConnectView(?string $error = null): View
    {
        return view('layouts.client', [
            'slot' => view('tuya.connect', [
                'error' => $error,
                'user_code' => session('tuya_user_code'),
                'qrcode_payload' => session('tuya_qr_payload'),
                'expire_time' => session('tuya_qr_expire_time'),
                'poll_token' => session('tuya_qr_token'),
            ]),
        ]);
    }
}
