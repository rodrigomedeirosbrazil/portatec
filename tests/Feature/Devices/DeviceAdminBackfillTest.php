<?php

declare(strict_types=1);

namespace Tests\Feature\Devices;

use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * O backfill roda dentro da migração, então estes testes reproduzem a regra
 * chamando os mesmos passos sobre dados criados depois do `migrate`. O que
 * está sob teste é a REGRA de resolução do admin, não o `artisan migrate`.
 */
class DeviceAdminBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_with_existing_link_keeps_oldest_user_as_admin(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $device = Device::withoutEvents(fn (): Device => Device::create([
            'name' => 'Portão',
            'brand' => 'tuya',
        ]));

        $device->deviceUsers()->create(['user_id' => $first->id]);
        $device->deviceUsers()->create(['user_id' => $second->id]);

        $this->runBackfill();

        $this->assertSame('admin', DB::table('device_user')
            ->where('device_id', $device->id)->where('user_id', $first->id)->value('role'));
        $this->assertSame('user', DB::table('device_user')
            ->where('device_id', $device->id)->where('user_id', $second->id)->value('role'));
    }

    public function test_device_without_link_takes_oldest_place_admin(): void
    {
        $admin = User::factory()->create();
        $host = User::factory()->create();
        $place = Place::create(['name' => 'Condomínio']);

        PlaceUser::create(['place_id' => $place->id, 'user_id' => $host->id, 'role' => 'host']);
        PlaceUser::create(['place_id' => $place->id, 'user_id' => $admin->id, 'role' => 'admin']);

        $device = Device::withoutEvents(fn (): Device => Device::create([
            'name' => 'Portão',
            'brand' => 'portatec',
            'place_id' => $place->id,
        ]));

        $this->runBackfill();

        $this->assertSame('admin', DB::table('device_user')
            ->where('device_id', $device->id)->where('user_id', $admin->id)->value('role'));
    }

    public function test_device_without_place_stays_without_admin(): void
    {
        $device = Device::withoutEvents(fn (): Device => Device::create([
            'name' => 'Órfão',
            'brand' => 'portatec',
        ]));

        $this->runBackfill();

        $this->assertSame(0, DB::table('device_user')->where('device_id', $device->id)->count());
    }

    public function test_other_place_admins_get_a_grant_so_they_can_reattach(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $host = User::factory()->create();
        $place = Place::create(['name' => 'Village']);

        PlaceUser::create(['place_id' => $place->id, 'user_id' => $first->id, 'role' => 'admin']);
        PlaceUser::create(['place_id' => $place->id, 'user_id' => $second->id, 'role' => 'admin']);
        PlaceUser::create(['place_id' => $place->id, 'user_id' => $host->id, 'role' => 'host']);

        $device = Device::withoutEvents(fn (): Device => Device::create([
            'name' => 'Portão',
            'brand' => 'portatec',
            'place_id' => $place->id,
        ]));

        $this->runBackfill();

        // O admin mais antigo do local manda no dispositivo.
        $this->assertSame('admin', DB::table('device_user')
            ->where('device_id', $device->id)->where('user_id', $first->id)->value('role'));

        // O segundo admin do local recebe uso: sem isso ele consegue
        // desanexar o dispositivo do local e nao consegue anexar de volta.
        $this->assertSame('user', DB::table('device_user')
            ->where('device_id', $device->id)->where('user_id', $second->id)->value('role'));

        $device->refresh();
        $this->assertTrue($device->isUsableBy($second));
        $this->assertFalse($device->isAdministeredBy($second));

        // `host` nunca pode desanexar, entao nao ha porta de mao unica para
        // fechar - e um vinculo lhe daria acesso que ele nao tinha.
        $this->assertNull(DB::table('device_user')
            ->where('device_id', $device->id)->where('user_id', $host->id)->value('role'));
    }

    private function runBackfill(): void
    {
        require_once database_path('migrations/2026_09_08_000001_add_role_to_device_user_table.php');

        $migration = include database_path('migrations/2026_09_08_000001_add_role_to_device_user_table.php');

        (fn () => $this->backfill())->call($migration);
    }
}
