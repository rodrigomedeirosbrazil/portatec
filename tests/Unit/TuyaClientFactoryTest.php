<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Place;
use App\Models\TuyaCredential;
use App\Services\Tuya\Client as TuyaClient;
use App\Services\Tuya\TuyaClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TuyaClientFactoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'tuya.client_id' => 'test_id',
            'tuya.client_secret' => 'test_secret',
            'tuya.base_url' => 'https://openapi.tuyaus.com',
        ]);
    }

    public function test_client_for_place_returns_null_when_no_credential(): void
    {
        $place = Place::query()->create(['name' => 'No Tuya']);
        $factory = new TuyaClientFactory;

        $this->assertNull($factory->clientForPlace($place));
    }

    public function test_client_for_place_returns_authenticated_client_when_credential_valid(): void
    {
        $place = Place::query()->create(['name' => 'With Tuya']);
        TuyaCredential::query()->create([
            'place_id' => $place->id,
            'access_token' => 'valid_at',
            'refresh_token' => 'valid_rt',
            'expires_at' => now()->addHour(),
            'uid' => 'uid1',
        ]);

        $factory = new TuyaClientFactory;
        $client = $factory->clientForPlace($place);

        $this->assertInstanceOf(TuyaClient::class, $client);
        $this->assertTrue($client->isAuthenticated());
    }

    public function test_client_for_place_refreshes_and_returns_client_when_expiring_soon(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'result' => [
                    'access_token' => 'new_at',
                    'refresh_token' => 'new_rt',
                    'expire_time' => 7200,
                    'uid' => 'uid1',
                ],
            ], 200),
        ]);

        $place = Place::query()->create(['name' => 'Expiring']);
        $cred = TuyaCredential::query()->create([
            'place_id' => $place->id,
            'access_token' => 'old_at',
            'refresh_token' => 'old_rt',
            'expires_at' => now()->addSeconds(100),
            'uid' => 'uid1',
        ]);

        $factory = new TuyaClientFactory;
        $client = $factory->clientForPlace($place);

        $this->assertInstanceOf(TuyaClient::class, $client);
        $this->assertTrue($client->isAuthenticated());

        $cred->refresh();
        $this->assertSame('new_at', $cred->access_token);
        $this->assertSame('new_rt', $cred->refresh_token);
    }

    public function test_refresh_credential_updates_model_and_returns_it(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'result' => [
                    'access_token' => 'refreshed_at',
                    'refresh_token' => 'refreshed_rt',
                    'expire_time' => 3600,
                    'uid' => 'uid1',
                ],
            ], 200),
        ]);

        $place = Place::query()->create(['name' => 'Place']);
        $cred = TuyaCredential::query()->create([
            'place_id' => $place->id,
            'access_token' => 'old_at',
            'refresh_token' => 'old_rt',
            'expires_at' => now()->subMinute(),
            'uid' => 'uid1',
        ]);

        $factory = new TuyaClientFactory;
        $updated = $factory->refreshCredential($cred);

        $this->assertNotNull($updated);
        $this->assertSame($cred->id, $updated->id);
        $updated->refresh();
        $this->assertSame('refreshed_at', $updated->access_token);
    }

    public function test_refresh_credential_returns_null_when_api_fails(): void
    {
        Http::fake(['*' => Http::response(['success' => false], 200)]);

        $place = Place::query()->create(['name' => 'Place']);
        $cred = TuyaCredential::query()->create([
            'place_id' => $place->id,
            'access_token' => 'old_at',
            'refresh_token' => 'old_rt',
            'expires_at' => now()->subMinute(),
            'uid' => 'uid1',
        ]);

        $factory = new TuyaClientFactory;
        $this->assertNull($factory->refreshCredential($cred));
    }
}
