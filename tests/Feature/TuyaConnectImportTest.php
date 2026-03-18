<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Integrations\TuyaConnect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TuyaConnectImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_integration_persists_device_tuya_metadata_and_integration_link(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(TuyaConnect::class)
            ->set('userCode', 'user-code-123')
            ->set('tokenJson', json_encode([
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expire_time' => 7200,
                'uid' => 'tuya-uid-1',
                'endpoint' => 'https://openapi.tuyaus.com',
            ], JSON_THROW_ON_ERROR))
            ->set('devices', [[
                'id' => 'device-1',
                'name' => 'IFR 1001',
                'category' => 'ms',
                'categoryLabel' => 'Fechadura',
                'online' => true,
                'productId' => 'product-1',
                'productName' => 'Lock Product',
                'icon' => 'https://example.test/icon.png',
                'status' => [['code' => 'doorcontact_state', 'value' => true]],
                'selected' => true,
            ]])
            ->call('saveIntegration')
            ->assertHasNoErrors();

        $integration = \App\Models\Integration::query()->where('tuya_uid', 'tuya-uid-1')->firstOrFail();

        $this->assertDatabaseHas('devices', [
            'external_device_id' => 'device-1',
            'integration_id' => $integration->id,
            'tuya_category' => 'ms',
            'tuya_product_id' => 'product-1',
            'tuya_product_name' => 'Lock Product',
            'tuya_online' => true,
        ]);
    }
}
