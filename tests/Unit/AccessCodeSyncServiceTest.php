<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\AccessCode;
use App\Models\AccessCodeDeviceSync;
use App\Models\Device;
use App\Services\AccessCodeSyncService;
use App\Services\Device\DeviceCommandService;
use App\Services\Tuya\TuyaIntegrationService;
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
                'start' => now()->subHour(),
                'end' => now()->addHour(),
            ]);

            AccessCode::create([
                'place_id' => $placeId,
                'pin' => '222222',
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
                    if (! $value instanceof Collection) {
                        return false;
                    }

                    return $value->count() === 1 && $value->first()->pin === '111111';
                })
            );

        $tuyaMock = Mockery::mock(TuyaIntegrationService::class);

        $this->app->instance(DeviceCommandService::class, $mock);
        $this->app->instance(TuyaIntegrationService::class, $tuyaMock);

        $service = app(AccessCodeSyncService::class);
        $service->syncAccessCodesToDevice($device);
    }

    public function test_sync_new_access_code_to_tuya_lock_creates_remote_password_and_tracks_sync(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Tuya User',
            'email' => 'tuya-sync@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $platformId = DB::table('platforms')->insertGetId([
            'name' => 'Tuya SmartLife',
            'slug' => 'tuya',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $integrationId = DB::table('integrations')->insertGetId([
            'platform_id' => $platformId,
            'user_id' => $userId,
            'tuya_access_token' => 'token',
            'tuya_refresh_token' => 'refresh',
            'tuya_token_expires_at' => now()->addHour(),
            'tuya_uid' => 'uid-1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $placeId = DB::table('places')->insertGetId([
            'name' => 'Lock Place',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $device = Device::create([
            'place_id' => $placeId,
            'integration_id' => $integrationId,
            'name' => 'IFR 1001',
            'brand' => 'tuya',
            'external_device_id' => 'tuya-lock-1',
            'tuya_category' => 'ms',
        ]);

        $accessCode = AccessCode::withoutEvents(fn (): AccessCode => AccessCode::create([
            'place_id' => $placeId,
            'pin' => '333333',
            'start' => now()->subMinute(),
            'end' => now()->addHour(),
        ]));

        $deviceCommandMock = Mockery::mock(DeviceCommandService::class);
        $tuyaMock = Mockery::mock(TuyaIntegrationService::class);
        $tuyaMock->shouldReceive('createTemporaryPasswordViaDP')
            ->once()
            ->with(
                Mockery::on(fn ($value): bool => $value instanceof Device && $value->id === $device->id),
                '333333',
                $accessCode->start->timestamp,
                $accessCode->end->timestamp,
            )
            ->andReturn('12345:67890');

        $this->app->instance(DeviceCommandService::class, $deviceCommandMock);
        $this->app->instance(TuyaIntegrationService::class, $tuyaMock);

        app(AccessCodeSyncService::class)->syncNewAccessCode($accessCode);

        $this->assertDatabaseHas('access_code_device_syncs', [
            'access_code_id' => $accessCode->id,
            'device_id' => $device->id,
            'provider' => 'tuya',
            'external_reference' => '12345:67890',
            'synced_pin' => '333333',
            'status' => 'synced',
        ]);
    }

    public function test_sync_updated_access_code_to_tuya_lock_recreates_remote_password_when_payload_changes(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Tuya User',
            'email' => 'tuya-update@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $platformId = DB::table('platforms')->insertGetId([
            'name' => 'Tuya SmartLife',
            'slug' => 'tuya',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $integrationId = DB::table('integrations')->insertGetId([
            'platform_id' => $platformId,
            'user_id' => $userId,
            'tuya_access_token' => 'token',
            'tuya_refresh_token' => 'refresh',
            'tuya_token_expires_at' => now()->addHour(),
            'tuya_uid' => 'uid-2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $placeId = DB::table('places')->insertGetId([
            'name' => 'Lock Place 2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $device = Device::create([
            'place_id' => $placeId,
            'integration_id' => $integrationId,
            'name' => 'IFR 1001',
            'brand' => 'tuya',
            'external_device_id' => 'tuya-lock-2',
            'tuya_category' => 'ms',
        ]);

        $accessCode = AccessCode::withoutEvents(fn (): AccessCode => AccessCode::create([
            'place_id' => $placeId,
            'pin' => '444444',
            'start' => now()->subMinute(),
            'end' => now()->addHour(),
        ]));

        AccessCodeDeviceSync::create([
            'access_code_id' => $accessCode->id,
            'device_id' => $device->id,
            'provider' => 'tuya',
            'external_reference' => 'remote-password-old',
            'synced_start' => $accessCode->start,
            'synced_end' => $accessCode->end,
            'synced_pin' => '111111',
            'status' => 'synced',
            'last_synced_at' => now()->subMinute(),
        ]);

        AccessCode::withoutEvents(function () use ($accessCode): void {
            $accessCode->update(['pin' => '555555']);
        });

        $deviceCommandMock = Mockery::mock(DeviceCommandService::class);
        $tuyaMock = Mockery::mock(TuyaIntegrationService::class);
        $tuyaMock->shouldReceive('deleteTemporaryPassword')
            ->once()
            ->with(
                Mockery::on(fn ($value): bool => $value instanceof Device && $value->id === $device->id),
                'remote-password-old',
            )
            ->andReturn(true);
        $tuyaMock->shouldReceive('createTemporaryPasswordViaDP')
            ->once()
            ->andReturn('12345:67890');

        $this->app->instance(DeviceCommandService::class, $deviceCommandMock);
        $this->app->instance(TuyaIntegrationService::class, $tuyaMock);

        app(AccessCodeSyncService::class)->syncUpdatedAccessCode($accessCode->fresh());

        $this->assertDatabaseHas('access_code_device_syncs', [
            'access_code_id' => $accessCode->id,
            'device_id' => $device->id,
            'external_reference' => '12345:67890',
            'synced_pin' => '555555',
            'status' => 'synced',
        ]);
    }

    public function test_sync_deleted_access_code_to_tuya_lock_marks_sync_as_deleted(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Tuya User',
            'email' => 'tuya-delete@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $platformId = DB::table('platforms')->insertGetId([
            'name' => 'Tuya SmartLife',
            'slug' => 'tuya',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $integrationId = DB::table('integrations')->insertGetId([
            'platform_id' => $platformId,
            'user_id' => $userId,
            'tuya_access_token' => 'token',
            'tuya_refresh_token' => 'refresh',
            'tuya_token_expires_at' => now()->addHour(),
            'tuya_uid' => 'uid-3',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $placeId = DB::table('places')->insertGetId([
            'name' => 'Lock Place 3',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $device = Device::create([
            'place_id' => $placeId,
            'integration_id' => $integrationId,
            'name' => 'IFR 1001',
            'brand' => 'tuya',
            'external_device_id' => 'tuya-lock-3',
            'tuya_category' => 'ms',
        ]);

        $accessCode = AccessCode::withoutEvents(fn (): AccessCode => AccessCode::create([
            'place_id' => $placeId,
            'pin' => '666666',
            'start' => now()->subMinute(),
            'end' => now()->addHour(),
        ]));

        AccessCodeDeviceSync::create([
            'access_code_id' => $accessCode->id,
            'device_id' => $device->id,
            'provider' => 'tuya',
            'external_reference' => 'remote-password-delete',
            'synced_start' => $accessCode->start,
            'synced_end' => $accessCode->end,
            'synced_pin' => '666666',
            'status' => 'synced',
            'last_synced_at' => now()->subMinute(),
        ]);

        $deviceCommandMock = Mockery::mock(DeviceCommandService::class);
        $tuyaMock = Mockery::mock(TuyaIntegrationService::class);
        $tuyaMock->shouldReceive('deleteTemporaryPassword')
            ->once()
            ->andReturn(true);

        $this->app->instance(DeviceCommandService::class, $deviceCommandMock);
        $this->app->instance(TuyaIntegrationService::class, $tuyaMock);

        app(AccessCodeSyncService::class)->syncDeletedAccessCode($accessCode);

        $this->assertDatabaseHas('access_code_device_syncs', [
            'access_code_id' => $accessCode->id,
            'device_id' => $device->id,
            'status' => 'deleted',
            'external_reference' => null,
        ]);
    }

    public function test_tuya_device_is_available_uses_online_snapshot(): void
    {
        $device = Device::create([
            'name' => 'IFR 1001',
            'brand' => 'tuya',
            'external_device_id' => 'tuya-lock-4',
            'tuya_category' => 'ms',
            'tuya_online' => true,
            'last_sync' => now()->subDays(3),
        ]);

        $this->assertTrue($device->isAvailable());

        $device->update(['tuya_online' => false]);

        $this->assertFalse($device->fresh()->isAvailable());
    }
}
