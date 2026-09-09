<?php

declare(strict_types=1);

namespace Tests\Feature\Devices;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevicePermissionScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);
    }

    public function test_admin_grants_by_exact_email(): void
    {
        $admin = User::factory()->create();
        $resident = User::factory()->create(['email' => 'morador@exemplo.com']);
        $device = $this->deviceOwnedBy($admin);

        $this->actingAs($admin)
            ->post("/app/devices/{$device->id}/permissions", ['email' => 'morador@exemplo.com'])
            ->assertRedirect("/app/devices/{$device->id}/permissions");

        $this->assertTrue($device->deviceUsers()->where('user_id', $resident->id)->where('role', 'user')->exists());
    }

    public function test_partial_email_finds_nobody(): void
    {
        $admin = User::factory()->create();
        User::factory()->create(['email' => 'morador@exemplo.com']);
        $device = $this->deviceOwnedBy($admin);

        $this->actingAs($admin)
            ->post("/app/devices/{$device->id}/permissions", ['email' => 'morador'])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, $device->deviceUsers()->count());
    }

    public function test_grantee_cannot_grant(): void
    {
        $admin = User::factory()->create();
        $resident = User::factory()->create();
        $third = User::factory()->create(['email' => 'terceiro@exemplo.com']);

        $device = $this->deviceOwnedBy($admin);
        $device->deviceUsers()->create(['user_id' => $resident->id, 'role' => 'user']);

        $this->actingAs($resident)
            ->post("/app/devices/{$device->id}/permissions", ['email' => $third->email])
            ->assertForbidden();
    }

    public function test_transfer_swaps_the_roles(): void
    {
        $admin = User::factory()->create();
        $newAdmin = User::factory()->create(['email' => 'novo@exemplo.com']);
        $device = $this->deviceOwnedBy($admin);

        $this->actingAs($admin)
            ->post("/app/devices/{$device->id}/transfer", ['email' => 'novo@exemplo.com'])
            ->assertRedirect("/app/devices/{$device->id}/permissions");

        $this->assertTrue($device->fresh()->isAdministeredBy($newAdmin));
        $this->assertFalse($device->fresh()->isAdministeredBy($admin));
        $this->assertTrue($device->fresh()->isUsableBy($admin));
    }

    private function deviceOwnedBy(User $user): Device
    {
        $device = Device::withoutEvents(fn (): Device => Device::create([
            'name' => 'Garagem A',
            'brand' => 'portatec',
            'external_device_id' => 'chip-a',
        ]));

        $device->deviceUsers()->create(['user_id' => $user->id, 'role' => 'admin']);

        return $device;
    }
}
