<?php

declare(strict_types=1);

namespace App\Livewire\Integrations;

use App\Enums\DeviceBrandEnum;
use App\Models\Device;
use App\Models\Integration;
use App\Models\Platform;
use App\Services\Tuya\DTOs\TuyaDeviceDTO;
use App\Services\Tuya\DTOs\TuyaTokenDTO;
use App\Services\Tuya\TuyaQrAuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TuyaConnect extends Component
{
    public string $step = 'form';

    public string $userCode = '';

    public string $qrUrl = '';

    public ?int $qrExpiresAt = null;

    /** Token do QR usado no polling (interno). */
    public string $qrCode = '';

    public string $tokenJson = '';

    /** @var array<int, array{id: string, name: string, categoryLabel: string, online: bool, selected: bool}> */
    public array $devices = [];

    public string $errorMessage = '';

    public function generateQr(): void
    {
        $this->resetValidation();
        $this->errorMessage = '';

        $this->validate([
            'userCode' => ['required', 'string', 'min:1'],
        ], [], [
            'userCode' => 'Código do Usuário',
        ]);

        try {
            $service = new TuyaQrAuthService;
            $dto = $service->generateQrCode(trim($this->userCode));
        } catch (\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        if (! $dto) {
            $this->errorMessage = 'Não foi possível gerar o QR code. Verifique o código do usuário.';

            return;
        }

        $this->qrCode = $dto->qrCode;
        $this->qrUrl = $dto->qrUrl;
        $this->qrExpiresAt = $dto->expireTime;
        $this->step = 'qr';
    }

    public function pollQr(): void
    {
        if ($this->step !== 'qr') {
            return;
        }

        try {
            $service = new TuyaQrAuthService;
            $token = $service->pollLogin($this->qrCode, trim($this->userCode));

            if ($token === null) {
                return;
            }

            $this->tokenJson = json_encode([
                'access_token' => $token->accessToken,
                'refresh_token' => $token->refreshToken,
                'expire_time' => $token->expireTime,
                'uid' => $token->uid,
            ], JSON_THROW_ON_ERROR);

            $deviceDtos = $service->getDevices($token);
            $this->devices = array_values(array_map(
                fn (TuyaDeviceDTO $d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'categoryLabel' => $d->categoryLabel(),
                    'online' => $d->online,
                    'selected' => TuyaDeviceDTO::isAccessCategory($d->category),
                ],
                $deviceDtos
            ));

            $this->step = 'devices';
        } catch (\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
            $this->step = 'form';
            $this->qrUrl = '';
            $this->qrExpiresAt = null;
            $this->qrCode = '';
        }
    }

    public function resetQr(): void
    {
        $this->step = 'form';
        $this->qrUrl = '';
        $this->qrExpiresAt = null;
        $this->qrCode = '';
        $this->tokenJson = '';
        $this->devices = [];
        $this->errorMessage = '';
        $this->resetValidation();
    }

    public function toggleDevice(string $id): void
    {
        foreach ($this->devices as $i => $device) {
            if (($device['id'] ?? '') === $id) {
                $this->devices[$i]['selected'] = ! ($this->devices[$i]['selected'] ?? false);

                return;
            }
        }
    }

    public function saveIntegration(): void
    {
        if ($this->tokenJson === '') {
            $this->errorMessage = 'Sessão inválida. Por favor, recomece.';
            $this->step = 'form';

            return;
        }

        $tokenData = json_decode($this->tokenJson, true, 512, JSON_THROW_ON_ERROR);
        $token = new TuyaTokenDTO(
            accessToken: $tokenData['access_token'],
            refreshToken: $tokenData['refresh_token'],
            expireTime: $tokenData['expire_time'],
            uid: $tokenData['uid'],
        );

        $platform = Platform::where('slug', 'tuya')->firstOrCreate(
            ['slug' => 'tuya'],
            ['name' => 'Tuya SmartLife'],
        );

        Integration::updateOrCreate(
            [
                'platform_id' => $platform->id,
                'user_id' => Auth::id(),
            ],
            [
                'tuya_user_code' => $this->userCode,
                'tuya_access_token' => $token->accessToken,
                'tuya_refresh_token' => $token->refreshToken,
                'tuya_token_expires_at' => now()->addSeconds($token->expireTime),
                'tuya_uid' => $token->uid,
                'tuya_endpoint' => config('tuya.base_url', 'https://openapi.tuyaus.com'),
            ],
        );

        foreach ($this->devices as $d) {
            if (! ($d['selected'] ?? false)) {
                continue;
            }

            Device::updateOrCreate(
                ['external_device_id' => $d['id']],
                [
                    'name' => $d['name'],
                    'brand' => DeviceBrandEnum::Tuya,
                    'external_device_id' => $d['id'],
                ]
            );
        }

        $this->step = 'done';
        session()->flash('status', 'Integração Tuya conectada com sucesso!');
    }

    public function render(): View
    {
        return view('livewire.integrations.tuya-connect')
            ->layout('layouts.client');
    }
}
