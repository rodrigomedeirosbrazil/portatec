<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Place;
use App\Models\TuyaAccount;
use App\Models\TuyaDevice;
use App\Services\Tuya\TuyaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TuyaIntegrationController extends Controller
{
    public function __construct(
        private TuyaService $tuyaService
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

        $tokenData = $this->tuyaService->getQRToken();
        if (! $tokenData) {
            return view('layouts.client', [
                'slot' => view('tuya.connect', [
                    'error' => 'Não foi possível obter o código QR. Verifique as configurações (TUYA_CLIENT_ID, TUYA_CLIENT_SECRET) e tente novamente.',
                ]),
            ]);
        }

        return view('layouts.client', [
            'slot' => view('tuya.connect', [
                'qrcode_url' => $tokenData['qrcode'],
                'expire_time' => $tokenData['expire_time'],
                'poll_token' => $tokenData['token'],
            ]),
        ]);
    }

    public function pollLogin(string $token): JsonResponse
    {
        $status = $this->tuyaService->pollLoginStatus($token);
        if (! $status || ! $status['is_login']) {
            return response()->json(['linked' => false]);
        }

        TuyaAccount::query()->updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'uid' => $status['uid'],
                'access_token' => $status['access_token'],
                'refresh_token' => $status['refresh_token'],
                'expires_at' => now()->addSeconds($status['expire_time']),
                'platform_url' => $status['platform_url'],
                'active' => true,
            ]
        );

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
        TuyaAccount::query()
            ->where('user_id', auth()->id())
            ->update(['active' => false]);

        return redirect()->route('app.dashboard')->with('status', 'Conta Tuya desvinculada.');
    }
}
