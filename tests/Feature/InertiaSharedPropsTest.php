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
}
