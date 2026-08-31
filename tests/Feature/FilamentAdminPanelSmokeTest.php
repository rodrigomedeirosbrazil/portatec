<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test da fase de remoção do Livewire do app do cliente (app/Livewire,
 * resources/views/livewire e os blades órfãos em cascata).
 *
 * O painel /admin roda em Filament 4, que depende do pacote livewire/livewire
 * (mantido no composer.json mesmo após a remoção do código Livewire do app do
 * cliente). Este teste prova que o painel continua respondendo normalmente:
 * super admin consegue acessar o dashboard do Filament (200, sem 500), e um
 * usuário comum é barrado (302/403), o que já é sinal de que a rota e o
 * bootstrap do painel (Livewire incluso) continuam funcionando.
 */
class FilamentAdminPanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_the_filament_panel(): void
    {
        // E-mail já configurado em phpunit.xml (PORTATEC_SUPER_ADMIN_EMAILS),
        // sem precisar tocar em .env ou em config() em tempo de execução.
        $admin = User::factory()->create([
            'email' => 'contato@medeirostec.com.br',
        ]);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
    }

    public function test_regular_user_is_denied_the_filament_panel_without_a_server_error(): void
    {
        $user = User::factory()->create([
            'email' => 'nao-e-admin@example.com',
        ]);

        $response = $this->actingAs($user)->get('/admin');

        // O importante aqui é que a rota do painel não quebra com 500: um
        // usuário sem o papel de super_admin é redirecionado/barrado antes de
        // renderizar o dashboard, mas o bootstrap do Filament (e do Livewire
        // que ele carrega) segue intacto.
        $response->assertStatus(403);
    }
}
