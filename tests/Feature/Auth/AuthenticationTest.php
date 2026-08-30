<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_renders(): void
    {
        $response = $this->get('/app/login');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('auth/login'));
    }

    public function test_register_screen_renders(): void
    {
        $response = $this->get('/app/register');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('auth/register'));
    }

    public function test_forgot_password_screen_renders(): void
    {
        $response = $this->get('/app/forgot-password');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('auth/forgot-password'));
    }

    public function test_reset_password_screen_renders(): void
    {
        $response = $this->get('/app/reset-password/some-token');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('auth/reset-password')
            ->where('token', 'some-token')
        );
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->post('/app/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/app/dashboard');
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->from('/app/login')->post('/app/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_registration_creates_user_logs_in_and_redirects(): void
    {
        $response = $this->post('/app/register', [
            'name' => 'Novo Usuário',
            'email' => 'novo@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'novo@example.com']);
        $this->assertAuthenticated();
        $response->assertRedirect('/app/dashboard');
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existente@example.com']);

        $response = $this->from('/app/register')->post('/app/register', [
            'name' => 'Outro Usuário',
            'email' => 'existente@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_forgot_password_sends_reset_link_for_valid_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/app/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status');
        $response->assertSessionDoesntHaveErrors();
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_with_valid_token_updates_password(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $this->post('/app/forgot-password', ['email' => $user->email]);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $response = $this->post('/app/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
    }

    public function test_reset_password_with_invalid_token_fails(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->from('/app/reset-password/invalid-token')->post('/app/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_guest_routes_redirect_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/app/login')->assertRedirect('/app/dashboard');
        $this->actingAs($user)->get('/app/register')->assertRedirect('/app/dashboard');
        $this->actingAs($user)->get('/app/forgot-password')->assertRedirect('/app/dashboard');
        $this->actingAs($user)->get('/app/reset-password/some-token')->assertRedirect('/app/dashboard');
    }
}
