<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ImpersonationSession;
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

        $response = $this->actingAs($superAdmin)
            ->followingRedirects()
            ->get(route('admin.impersonations.start', ['user' => $clientUser]));
        $response->assertOk();

        $this->assertAuthenticatedAs($clientUser);

        $session = \App\Models\ImpersonationSession::query()->first();
        $this->assertNotNull($session);
        $this->assertSame($superAdmin->id, $session->impersonator_user_id);
        $this->assertSame($clientUser->id, $session->impersonated_user_id);
        $this->assertNotNull($session->started_at);
        $this->assertNull($session->ended_at);

        $this->post(route('app.impersonations.stop'), [
            '_token' => session()->token(),
        ])->assertRedirect('/admin');

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

    public function test_super_admin_cannot_impersonate_another_super_admin(): void
    {
        $superAdmin = User::factory()->create([
            'email' => 'contato@medeirostec.com.br',
        ]);
        $otherSuperAdmin = User::factory()->create([
            'email' => 'segundo@medeirostec.com.br',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.impersonations.start', ['user' => $otherSuperAdmin]))
            ->assertRedirect();

        $this->assertAuthenticatedAs($superAdmin);
        $this->assertDatabaseCount('impersonation_sessions', 0);
    }

    public function test_stop_impersonation_logs_out_when_session_is_invalid_for_current_user(): void
    {
        $superAdmin = User::factory()->create([
            'email' => 'contato@medeirostec.com.br',
        ]);
        $clientUser = User::factory()->create();
        $otherClientUser = User::factory()->create();

        $session = ImpersonationSession::query()->create([
            'impersonator_user_id' => $superAdmin->id,
            'impersonated_user_id' => $otherClientUser->id,
            'started_at' => now(),
        ]);

        $this->actingAs($clientUser)
            ->withSession([
                'impersonator_id' => $superAdmin->id,
                'impersonation_session_id' => $session->id,
            ])
            ->get(route('app.dashboard'));

        $this->post(route('app.impersonations.stop'), [
            '_token' => session()->token(),
        ])->assertRedirect('/app/login');

        $this->assertGuest();
    }

    /**
     * Finalizar a sessao assumida leva o super admin de volta ao painel /admin, que e
     * Filament e nao Inertia.
     *
     * Com um redirect comum, o cliente Inertia segue o 302 por XHR, recebe HTML sem o
     * cabecalho x-inertia e despeja o painel inteiro num modal de depuracao em vez de
     * navegar. A resposta correta e 409 com X-Inertia-Location, que manda o cliente
     * fazer uma visita de pagina inteira.
     */
    public function test_stopping_impersonation_sends_the_client_to_a_full_page_visit(): void
    {
        $superAdmin = User::factory()->create(['email' => 'contato@medeirostec.com.br']);
        $clientUser = User::factory()->create();

        $this->actingAs($superAdmin)
            ->get(route('admin.impersonations.start', ['user' => $clientUser]));

        $response = $this->withHeader('X-Inertia', 'true')
            ->post(route('app.impersonations.stop'), []);

        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', '/admin');
    }
}
