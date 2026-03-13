<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Place;
use App\Models\TuyaCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TuyaCredentialTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_place(): void
    {
        $place = Place::query()->create(['name' => 'Test Place']);
        $credential = TuyaCredential::query()->create([
            'place_id' => $place->id,
            'access_token' => 'at',
            'refresh_token' => 'rt',
            'expires_at' => now()->addHour(),
            'uid' => 'tuya-uid-1',
        ]);

        $this->assertTrue($credential->place->is($place));
        $this->assertTrue($place->tuyaCredential->is($credential));
    }

    public function test_is_expired_or_expiring_soon_returns_true_when_expired(): void
    {
        $place = Place::query()->create(['name' => 'Test Place']);
        $credential = TuyaCredential::query()->create([
            'place_id' => $place->id,
            'access_token' => 'at',
            'refresh_token' => 'rt',
            'expires_at' => now()->subMinute(),
            'uid' => 'tuya-uid-1',
        ]);

        $this->assertTrue($credential->isExpiredOrExpiringSoon(300));
    }

    public function test_is_expired_or_expiring_soon_returns_true_when_expiring_within_buffer(): void
    {
        $place = Place::query()->create(['name' => 'Test Place']);
        $credential = TuyaCredential::query()->create([
            'place_id' => $place->id,
            'access_token' => 'at',
            'refresh_token' => 'rt',
            'expires_at' => now()->addSeconds(200),
            'uid' => 'tuya-uid-1',
        ]);

        $this->assertTrue($credential->isExpiredOrExpiringSoon(300));
    }

    public function test_is_expired_or_expiring_soon_returns_false_when_valid_beyond_buffer(): void
    {
        $place = Place::query()->create(['name' => 'Test Place']);
        $credential = TuyaCredential::query()->create([
            'place_id' => $place->id,
            'access_token' => 'at',
            'refresh_token' => 'rt',
            'expires_at' => now()->addHour(),
            'uid' => 'tuya-uid-1',
        ]);

        $this->assertFalse($credential->isExpiredOrExpiringSoon(300));
    }
}
