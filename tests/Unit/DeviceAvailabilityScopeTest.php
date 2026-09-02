<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\DeviceBrandEnum;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A regra de disponibilidade passa a existir em dois lugares: o método
 * `isAvailable()` (usado nas telas, item a item) e os scopes (usados na lista
 * paginada, que precisa filtrar em SQL). Este teste existe para que os dois não
 * divirjam — se alguém mudar a janela de 10 minutos num lugar só, ele quebra.
 */
class DeviceAvailabilityScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_scopes_agree_with_the_is_available_method(): void
    {
        $onlinePortatec = Device::factory()->create([
            'brand' => DeviceBrandEnum::Portatec,
            'last_sync' => now()->subMinutes(2),
        ]);
        $offlinePortatec = Device::factory()->create([
            'brand' => DeviceBrandEnum::Portatec,
            'last_sync' => now()->subMinutes(30),
        ]);
        $neverSynced = Device::factory()->create([
            'brand' => DeviceBrandEnum::Portatec,
            'last_sync' => null,
        ]);
        $onlineTuya = Device::factory()->create([
            'brand' => DeviceBrandEnum::Tuya,
            'tuya_online' => true,
        ]);
        $offlineTuya = Device::factory()->create([
            'brand' => DeviceBrandEnum::Tuya,
            'tuya_online' => false,
        ]);

        $availableIds = Device::query()->available()->pluck('id')->sort()->values()->all();
        $unavailableIds = Device::query()->unavailable()->pluck('id')->sort()->values()->all();

        $expectedAvailable = Device::all()
            ->filter(fn (Device $device) => $device->isAvailable())
            ->pluck('id')->sort()->values()->all();
        $expectedUnavailable = Device::all()
            ->reject(fn (Device $device) => $device->isAvailable())
            ->pluck('id')->sort()->values()->all();

        $this->assertSame($expectedAvailable, $availableIds);
        $this->assertSame($expectedUnavailable, $unavailableIds);

        $this->assertContains($onlinePortatec->id, $availableIds);
        $this->assertContains($onlineTuya->id, $availableIds);
        $this->assertContains($offlinePortatec->id, $unavailableIds);
        $this->assertContains($neverSynced->id, $unavailableIds);
        $this->assertContains($offlineTuya->id, $unavailableIds);
    }

    public function test_the_two_scopes_partition_the_whole_table(): void
    {
        Device::factory()->count(6)->create();

        $this->assertSame(
            Device::query()->count(),
            Device::query()->available()->count() + Device::query()->unavailable()->count(),
        );
    }
}
