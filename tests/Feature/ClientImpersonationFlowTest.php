<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientImpersonationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_start_and_stop_client_impersonation_with_audit_trail(): void
    {
        $superAdmin = User::factory()->create([
            'email' => 'contato@medeirostec.com.br',
        ]);
        $clientUser = User::factory()->create();

        $this->actingAs($superAdmin)
            ->get(route('admin.impersonations.start', ['user' => $clientUser]))
            ->assertRedirect('/app/dashboard');

        $this->assertAuthenticatedAs($clientUser);

        $session = \App\Models\ImpersonationSession::query()->first();
        $this->assertNotNull($session);
        $this->assertSame($superAdmin->id, $session->impersonator_user_id);
        $this->assertSame($clientUser->id, $session->impersonated_user_id);
        $this->assertNotNull($session->started_at);
        $this->assertNull($session->ended_at);

        $this->post(route('app.impersonations.stop'))
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($superAdmin);

        $session->refresh();
        $this->assertNotNull($session->ended_at);
    }

    public function test_non_super_admin_cannot_start_impersonation(): void
    {
        $regularAdmin = User::factory()->create();
        $clientUser = User::factory()->create();

        $this->actingAs($regularAdmin)
            ->get(route('admin.impersonations.start', ['user' => $clientUser]))
            ->assertForbidden();
    }
}
