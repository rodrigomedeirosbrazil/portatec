<?php

declare(strict_types=1);

namespace Tests\Feature\Places;

use App\Models\Device;
use App\Models\DeviceFunction;
use App\Models\Place;
use App\Models\PlaceDeviceFunction;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlacesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);
    }

    private function makePlaceWithAdmin(User $user, string $name = 'Casa da Praia'): Place
    {
        $place = Place::create(['name' => $name]);

        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'label' => $user->name,
        ]);

        return $place;
    }

    // ------------------------------------------------------------------
    // GET screens render the right Inertia component
    // ------------------------------------------------------------------

    public function test_index_renders_places_index(): void
    {
        $user = User::factory()->create();
        $this->makePlaceWithAdmin($user);

        $this->actingAs($user)
            ->get('/app/places')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('places/index'));
    }

    public function test_create_renders_places_create(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/places/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('places/create'));
    }

    public function test_show_renders_places_show(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $this->actingAs($user)
            ->get("/app/places/{$place->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('places/show'));
    }

    public function test_edit_renders_places_edit(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $this->actingAs($user)
            ->get("/app/places/{$place->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('places/edit'));
    }

    public function test_control_renders_places_control(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $this->actingAs($user)
            ->get("/app/places/{$place->id}/control")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('places/control'));
    }

    public function test_members_renders_places_members(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $this->actingAs($user)
            ->get("/app/places/{$place->id}/members")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('places/members'));
    }

    public function test_clone_renders_places_clone(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $this->actingAs($user)
            ->get("/app/places/{$place->id}/clone")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('places/clone'));
    }

    public function test_attach_device_renders_places_attach_device(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $this->actingAs($user)
            ->get("/app/places/{$place->id}/devices/attach")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('places/attach-device'));
    }

    // ------------------------------------------------------------------
    // Place isolation — the critical rule of the domain
    // ------------------------------------------------------------------

    public function test_user_cannot_view_place_of_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignPlace = $this->makePlaceWithAdmin($otherUser, 'Place Estranho');

        $this->actingAs($user)
            ->get("/app/places/{$foreignPlace->id}")
            ->assertForbidden();
    }

    public function test_user_cannot_edit_place_of_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignPlace = $this->makePlaceWithAdmin($otherUser);

        $this->actingAs($user)
            ->get("/app/places/{$foreignPlace->id}/edit")
            ->assertForbidden();
    }

    public function test_user_cannot_update_place_of_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignPlace = $this->makePlaceWithAdmin($otherUser);

        $this->actingAs($user)
            ->put("/app/places/{$foreignPlace->id}", ['name' => 'Hackeado'])
            ->assertForbidden();

        $this->assertDatabaseHas('places', [
            'id' => $foreignPlace->id,
            'name' => $foreignPlace->name,
        ]);
    }

    public function test_user_cannot_control_place_of_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignPlace = $this->makePlaceWithAdmin($otherUser);

        $this->actingAs($user)
            ->get("/app/places/{$foreignPlace->id}/control")
            ->assertForbidden();
    }

    public function test_user_cannot_manage_members_of_place_of_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignPlace = $this->makePlaceWithAdmin($otherUser);

        $this->actingAs($user)
            ->get("/app/places/{$foreignPlace->id}/members")
            ->assertForbidden();
    }

    public function test_user_cannot_clone_place_of_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignPlace = $this->makePlaceWithAdmin($otherUser);

        $this->actingAs($user)
            ->get("/app/places/{$foreignPlace->id}/clone")
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Create / update
    // ------------------------------------------------------------------

    public function test_store_creates_place_and_admin_place_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/app/places', ['name' => 'Nova Casa']);

        $place = Place::query()->where('name', 'Nova Casa')->firstOrFail();
        $response->assertRedirect("/app/places/{$place->id}");

        $this->assertDatabaseHas('place_users', [
            'place_id' => $place->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);
    }

    public function test_update_persists_the_new_name(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user, 'Nome Antigo');

        $this->actingAs($user)
            ->put("/app/places/{$place->id}", ['name' => 'Nome Novo'])
            ->assertRedirect("/app/places/{$place->id}");

        $this->assertDatabaseHas('places', [
            'id' => $place->id,
            'name' => 'Nome Novo',
        ]);
    }

    // ------------------------------------------------------------------
    // Device removal
    // ------------------------------------------------------------------

    public function test_removing_device_deletes_place_device_functions_detaches_and_reassigns_place_id(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $otherPlace = $this->makePlaceWithAdmin($user, 'Outra Casa');

        $device = Device::withoutEvents(fn () => Device::create([
            'place_id' => $place->id,
            'name' => 'Fechadura',
            'brand' => 'portatec',
        ]));
        $device->places()->attach([$place->id, $otherPlace->id]);

        $function = DeviceFunction::create([
            'device_id' => $device->id,
            'type' => 'switch',
            'pin' => '1',
        ]);

        PlaceDeviceFunction::create([
            'place_id' => $place->id,
            'device_function_id' => $function->id,
        ]);

        $this->actingAs($user)
            ->delete("/app/places/{$place->id}/devices/{$device->id}")
            ->assertRedirect("/app/places/{$place->id}");

        $this->assertDatabaseMissing('place_device_functions', [
            'place_id' => $place->id,
            'device_function_id' => $function->id,
        ]);

        $this->assertDatabaseMissing('device_place', [
            'place_id' => $place->id,
            'device_id' => $device->id,
        ]);

        $device->refresh();
        $this->assertSame($otherPlace->id, $device->place_id);
    }

    public function test_removing_device_reassigns_null_place_id_when_no_places_remain(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $device = Device::withoutEvents(fn () => Device::create([
            'place_id' => $place->id,
            'name' => 'Fechadura',
            'brand' => 'portatec',
        ]));
        $device->places()->attach($place->id);

        $this->actingAs($user)
            ->delete("/app/places/{$place->id}/devices/{$device->id}")
            ->assertRedirect("/app/places/{$place->id}");

        $device->refresh();
        $this->assertNull($device->place_id);
    }

    // ------------------------------------------------------------------
    // Members
    // ------------------------------------------------------------------

    public function test_store_member_adds_member(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $newMember = User::factory()->create();

        $this->actingAs($user)
            ->post("/app/places/{$place->id}/members", [
                'user_id' => $newMember->id,
                'role' => 'host',
                'label' => 'Cuidador',
            ])
            ->assertRedirect("/app/places/{$place->id}/members");

        $this->assertDatabaseHas('place_users', [
            'place_id' => $place->id,
            'user_id' => $newMember->id,
            'role' => 'host',
        ]);
    }

    public function test_store_member_rejects_duplicate_member(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $duplicateMember = User::factory()->create();

        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $duplicateMember->id,
            'role' => 'host',
            'label' => null,
        ]);

        $this->actingAs($user)
            ->post("/app/places/{$place->id}/members", [
                'user_id' => $duplicateMember->id,
                'role' => 'host',
            ])
            ->assertSessionHasErrors('user_id');

        $this->assertSame(
            1,
            PlaceUser::query()
                ->where('place_id', $place->id)
                ->where('user_id', $duplicateMember->id)
                ->count()
        );
    }

    public function test_destroy_member_does_not_remove_last_admin(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $adminPlaceUser = PlaceUser::query()
            ->where('place_id', $place->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->actingAs($user)
            ->delete("/app/places/{$place->id}/members/{$adminPlaceUser->id}")
            ->assertRedirect("/app/places/{$place->id}/members")
            ->assertSessionHasErrors('member');

        $this->assertDatabaseHas('place_users', ['id' => $adminPlaceUser->id]);
    }

    public function test_destroy_member_removes_non_last_admin(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $secondAdmin = User::factory()->create();
        $secondAdminPlaceUser = PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $secondAdmin->id,
            'role' => 'admin',
            'label' => null,
        ]);

        $this->actingAs($user)
            ->delete("/app/places/{$place->id}/members/{$secondAdminPlaceUser->id}")
            ->assertRedirect("/app/places/{$place->id}/members")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('place_users', ['id' => $secondAdminPlaceUser->id]);
    }

    // ------------------------------------------------------------------
    // Member search endpoint
    // ------------------------------------------------------------------

    public function test_member_search_returns_empty_with_less_than_two_characters(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        User::factory()->create(['name' => 'Ana Silva']);

        $this->actingAs($user)
            ->getJson("/app/places/{$place->id}/members/search?search=a")
            ->assertOk()
            ->assertJson(['data' => []]);
    }

    public function test_member_search_excludes_existing_members_and_limits_to_ten(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $existingMember = User::factory()->create(['name' => 'Zeta Existente']);
        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $existingMember->id,
            'role' => 'host',
            'label' => null,
        ]);

        foreach (range(1, 12) as $i) {
            User::factory()->create(['name' => "Zeta Candidato {$i}"]);
        }

        $response = $this->actingAs($user)
            ->getJson("/app/places/{$place->id}/members/search?search=Zeta")
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(10, $data);
        $this->assertNotContains($existingMember->id, array_column($data, 'id'));
    }

    public function test_member_search_denies_access_to_user_who_cannot_manage_members(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignPlace = $this->makePlaceWithAdmin($otherUser);

        $this->actingAs($user)
            ->getJson("/app/places/{$foreignPlace->id}/members/search?search=an")
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Clone
    // ------------------------------------------------------------------

    public function test_clone_creates_new_place_ignoring_empty_rows_and_self(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user, 'Original');
        $otherMember = User::factory()->create();

        $this->actingAs($user)
            ->post("/app/places/{$place->id}/clone", [
                'name' => 'Clone da Original',
                'additionalMembers' => [
                    ['user_id' => null, 'role' => 'host'],
                    ['user_id' => $user->id, 'role' => 'admin'],
                    ['user_id' => $otherMember->id, 'role' => 'host', 'label' => 'Convidado'],
                ],
            ])
            ->assertRedirect();

        $newPlace = Place::query()->where('name', 'Clone da Original')->firstOrFail();

        $this->assertDatabaseHas('place_users', [
            'place_id' => $newPlace->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $this->assertDatabaseHas('place_users', [
            'place_id' => $newPlace->id,
            'user_id' => $otherMember->id,
            'role' => 'host',
        ]);

        $this->assertSame(
            2,
            PlaceUser::query()->where('place_id', $newPlace->id)->count()
        );
    }

    // ------------------------------------------------------------------
    // Attach device
    // ------------------------------------------------------------------

    public function test_attach_device_creates_place_device_functions(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $device = Device::withoutEvents(fn () => Device::create([
            'name' => 'Sensor Livre',
            'brand' => 'portatec',
        ]));

        $function = DeviceFunction::create([
            'device_id' => $device->id,
            'type' => 'switch',
            'pin' => '2',
        ]);

        $this->actingAs($user)
            ->post("/app/places/{$place->id}/devices/attach", ['deviceId' => $device->id])
            ->assertRedirect("/app/places/{$place->id}");

        $this->assertDatabaseHas('place_device_functions', [
            'place_id' => $place->id,
            'device_function_id' => $function->id,
        ]);

        $device->refresh();
        $this->assertSame($place->id, $device->place_id);
    }

    public function test_attach_device_does_not_duplicate_if_already_associated(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $device = Device::withoutEvents(fn () => Device::create([
            'place_id' => $place->id,
            'name' => 'Ja Associado',
            'brand' => 'portatec',
        ]));
        $device->places()->attach($place->id);

        $this->actingAs($user)
            ->post("/app/places/{$place->id}/devices/attach", ['deviceId' => $device->id])
            ->assertRedirect("/app/places/{$place->id}");

        $this->assertSame(
            1,
            $device->places()->wherePivot('place_id', $place->id)->count()
        );
    }

    // ------------------------------------------------------------------
    // Send command
    // ------------------------------------------------------------------

    public function test_send_command_denies_device_out_of_place(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);
        $otherPlace = $this->makePlaceWithAdmin($user, 'Outra Casa');

        $device = Device::withoutEvents(fn () => Device::create([
            'place_id' => $otherPlace->id,
            'name' => 'Fora do Place',
            'brand' => 'portatec',
            'external_device_id' => 'ext-1',
        ]));
        $device->places()->attach($otherPlace->id);

        $this->actingAs($user)
            ->postJson("/app/places/{$place->id}/commands", [
                'device_id' => $device->id,
                'action' => 'toggle',
                'pin' => '1',
            ])
            ->assertNotFound();
    }

    public function test_send_command_denies_non_numeric_pin(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $device = Device::withoutEvents(fn () => Device::create([
            'place_id' => $place->id,
            'name' => 'Fechadura',
            'brand' => 'portatec',
            'external_device_id' => 'ext-2',
        ]));
        $device->places()->attach($place->id);

        $this->actingAs($user)
            ->postJson("/app/places/{$place->id}/commands", [
                'device_id' => $device->id,
                'action' => 'toggle',
                'pin' => 'abc',
            ])
            ->assertStatus(422);
    }
}
