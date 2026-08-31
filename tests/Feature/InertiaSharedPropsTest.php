<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * O HandleInertiaRequests compartilha auth, impersonation, flash e translations com
 * TODAS as telas. O layout e os componentes contam com essas props: sem elas, a pagina
 * quebra no navegador ao ler, por exemplo, flash.status de um objeto indefinido.
 *
 * Nenhum teste cobria a presenca dessas props, so o conteudo de cada tela. Por isso o
 * middleware pode deixar de ser aplicado sem que nada na suite acuse.
 */
class InertiaSharedPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_screens_receive_the_shared_props(): void
    {
        $this->get('/app/login')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('auth/login')
                ->has('flash')
                ->has('translations')
                ->where('auth.user', null)
        );
    }

    public function test_authenticated_screens_receive_the_shared_props(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/app/dashboard')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('dashboard')
                ->where('auth.user.id', $user->id)
                ->where('auth.user.email', $user->email)
                ->has('impersonation')
                ->has('flash')
                ->has('translations')
        );
    }

    public function test_translations_are_shared_so_the_client_can_render_labels(): void
    {
        $this->get('/app/login')->assertInertia(
            fn (AssertableInertia $page) => $page->where(
                'translations.place_roles.admin',
                __('app.place_roles.admin')
            )
        );
    }

    /**
     * O item "Admin" do menu leva ao painel Filament, que so aceita super admin
     * (User::canAccessPanel). Sem essa informacao nas props, o menu nao tem como
     * decidir e acaba mostrando o link para todo mundo - levando a um 403.
     */
    public function test_super_admin_is_flagged_in_the_shared_props(): void
    {
        $superAdmin = User::factory()->create(['email' => 'contato@medeirostec.com.br']);

        $this->actingAs($superAdmin)->get('/app/dashboard')->assertInertia(
            fn (AssertableInertia $page) => $page->where('auth.user.is_super_admin', true)
        );
    }

    public function test_regular_user_is_not_flagged_as_super_admin(): void
    {
        $user = User::factory()->create(['email' => 'host@portatec.test']);

        $this->actingAs($user)->get('/app/dashboard')->assertInertia(
            fn (AssertableInertia $page) => $page->where('auth.user.is_super_admin', false)
        );
    }

    /**
     * Em sessao assumida o usuario efetivo e o cliente, nao o super admin: os poderes
     * precisam ser os dele. O painel ja devolve 403 nesse caso, e a flag acompanha,
     * para o menu nao oferecer um caminho que nao funciona.
     */
    public function test_super_admin_is_not_flagged_while_impersonating_a_client(): void
    {
        $superAdmin = User::factory()->create(['email' => 'contato@medeirostec.com.br']);
        $clientUser = User::factory()->create(['email' => 'cliente@portatec.test']);

        $this->actingAs($superAdmin)
            ->get(route('admin.impersonations.start', ['user' => $clientUser]));

        $this->get('/app/dashboard')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('auth.user.email', $clientUser->email)
                ->where('auth.user.is_super_admin', false)
                ->where('impersonation.active', true)
        );
    }
}
