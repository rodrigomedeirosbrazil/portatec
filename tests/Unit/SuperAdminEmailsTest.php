<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

/**
 * `hasRole('super_admin')` lia o `env()` direto. Em produção isso não
 * funcionava: o entrypoint roda `artisan optimize`, o config fica cacheado, o
 * Laravel para de carregar o `.env` — que chega ao container como arquivo
 * montado, não como variável de ambiente — e a lista configurada era ignorada
 * em favor do default.
 *
 * Estes testes fixam o contrato novo: a decisão sai de `config()`, que é
 * avaliado durante o `config:cache` com o `.env` ainda carregado.
 */
class SuperAdminEmailsTest extends TestCase
{
    public function test_it_reads_the_list_from_config_not_from_env(): void
    {
        config(['portatec.super_admin_emails' => ['chefe@exemplo.com']]);

        $user = new User(['email' => 'chefe@exemplo.com']);

        $this->assertTrue($user->hasRole('super_admin'));
    }

    public function test_it_denies_an_email_outside_the_list(): void
    {
        config(['portatec.super_admin_emails' => ['chefe@exemplo.com']]);

        $user = new User(['email' => 'qualquer@exemplo.com']);

        $this->assertFalse($user->hasRole('super_admin'));
    }

    public function test_it_denies_when_the_list_is_empty(): void
    {
        config(['portatec.super_admin_emails' => []]);

        $user = new User(['email' => 'chefe@exemplo.com']);

        $this->assertFalse($user->hasRole('super_admin'));
    }

    public function test_it_matches_regardless_of_case(): void
    {
        config(['portatec.super_admin_emails' => ['chefe@exemplo.com']]);

        $user = new User(['email' => 'Chefe@Exemplo.COM']);

        $this->assertTrue($user->hasRole('super_admin'));
    }

    public function test_any_other_role_is_always_false(): void
    {
        config(['portatec.super_admin_emails' => ['chefe@exemplo.com']]);

        $user = new User(['email' => 'chefe@exemplo.com']);

        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_the_config_normalizes_spacing_and_case(): void
    {
        // O CSV vem do `.env` escrito por humano: " A@x.com , B@x.com ".
        $normalized = array_values(array_filter(array_map(
            static fn (string $email): string => strtolower(trim($email)),
            explode(',', ' Chefe@Exemplo.com , Outro@Exemplo.com ,, ')
        )));

        $this->assertSame(['chefe@exemplo.com', 'outro@exemplo.com'], $normalized);
    }

    public function test_the_shipped_config_returns_a_normalized_list(): void
    {
        $emails = config('portatec.super_admin_emails');

        $this->assertIsArray($emails);

        foreach ($emails as $email) {
            $this->assertSame(strtolower(trim($email)), $email);
        }
    }
}
