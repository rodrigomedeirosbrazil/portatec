<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\AccessCode;
use App\Models\Device;
use App\Services\AccessCodeSyncService;
use App\Services\Device\DeviceCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class AccessCodeSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);
    }

    public function test_sync_access_codes_to_device_only_sends_currently_valid_codes(): void
    {
        $placeId = DB::table('places')->insertGetId([
            'name' => 'Sync Place',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $device = Device::create([
            'place_id' => $placeId,
            'name' => 'Device A',
            'brand' => 'portatec',
            'external_device_id' => 'chip-001',
        ]);

        AccessCode::withoutEvents(function () use ($placeId): void {
            AccessCode::create([
                'place_id' => $placeId,
                'pin' => '111111',
                'label' => 'Valid',
                'start' => now()->subHour(),
                'end' => now()->addHour(),
            ]);

            AccessCode::create([
                'place_id' => $placeId,
                'pin' => '222222',
                'label' => 'Expired',
                'start' => now()->subDays(3),
                'end' => now()->subDay(),
            ]);
        });

        $mock = Mockery::mock(DeviceCommandService::class);
        $mock->shouldReceive('syncAccessCodes')
            ->once()
            ->with(
                Mockery::on(fn ($value): bool => $value instanceof Device && $value->id === $device->id),
                Mockery::on(function ($value): bool {
                    if (!$value instanceof Collection) {
                        return false;
                    }

                    return $value->count() === 1 && $value->first()->pin === '111111';
                })
            );

        $this->app->instance(DeviceCommandService::class, $mock);

        $service = app(AccessCodeSyncService::class);
        $service->syncAccessCodesToDevice($device);
    }
}
