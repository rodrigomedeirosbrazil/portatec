<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * O Inertia e uma ponte entre dois pacotes que precisam falar o MESMO protocolo:
 * inertiajs/inertia-laravel (servidor) e @inertiajs/react (cliente).
 *
 * Quando as majors divergem, o app quebra apenas EM TEMPO DE EXECUCAO NO NAVEGADOR:
 * a suite PHP passa (assertInertia so testa o lado servidor), o tsc passa e o build
 * compila. Foi exatamente o que aconteceu com o par 2.0.25 / 3.7.0: o Inertia 3 le a
 * pagina inicial de um <script data-page type="application/json">, enquanto o adaptador
 * 2.x renderiza o JSON num atributo data-page da <div>. O cliente nao acha o script,
 * assume null e estoura "Cannot read properties of null (reading 'component')".
 *
 * Este teste existe para que esse desalinhamento falhe no CI, e nao no navegador.
 */
class InertiaVersionPairingTest extends TestCase
{
    public function test_inertia_server_and_client_majors_match(): void
    {
        $serverMajor = $this->majorFromComposerLock('inertiajs/inertia-laravel');
        $clientMajor = $this->majorFromPackageLock('node_modules/@inertiajs/react');

        $this->assertSame(
            $serverMajor,
            $clientMajor,
            "A major do inertiajs/inertia-laravel (v{$serverMajor}) e a do @inertiajs/react "
            ."(v{$clientMajor}) precisam ser iguais. Majors diferentes quebram o bootstrap do "
            .'Inertia apenas no navegador, sem falhar em nenhuma outra verificacao.'
        );
    }

    private static function repoPath(string $file): string
    {
        return dirname(__DIR__, 2).'/'.$file;
    }

    private function majorFromComposerLock(string $package): int
    {
        $lock = json_decode((string) file_get_contents(self::repoPath('composer.lock')), true, 512, JSON_THROW_ON_ERROR);

        foreach ($lock['packages'] ?? [] as $entry) {
            if (($entry['name'] ?? '') === $package) {
                return (int) ltrim(explode('.', (string) $entry['version'])[0], 'v');
            }
        }

        $this->fail("Pacote {$package} nao encontrado no composer.lock.");
    }

    private function majorFromPackageLock(string $path): int
    {
        $lock = json_decode((string) file_get_contents(self::repoPath('package-lock.json')), true, 512, JSON_THROW_ON_ERROR);

        $version = $lock['packages'][$path]['version'] ?? null;

        if ($version === null) {
            $this->fail("Pacote {$path} nao encontrado no package-lock.json.");
        }

        return (int) explode('.', (string) $version)[0];
    }
}
