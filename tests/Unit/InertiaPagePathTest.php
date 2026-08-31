<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

/**
 * O `assertInertia` confere que o componente existe em disco, procurando nos
 * `inertia.testing.page_paths`. O padrao do pacote e `resource_path('js/Pages')`,
 * com P maiusculo, enquanto este projeto usa `js/pages`.
 *
 * No macOS o sistema de arquivos e case-insensitive e encontra assim mesmo; no
 * Linux do CI e da imagem de producao, nao. Sem `config/inertia.php` corrigindo
 * o caminho, dezenas de testes passam na maquina do desenvolvedor e quebram no CI.
 *
 * `is_dir()` tambem e case-insensitive no macOS, entao aqui a comparacao e feita
 * contra os nomes REAIS lidos do diretorio pai - isso falha nos dois sistemas.
 */
class InertiaPagePathTest extends TestCase
{
    public function test_configured_page_paths_match_the_real_directory_names(): void
    {
        $paths = array_merge(
            config('inertia.page_paths', []),
            config('inertia.testing.page_paths', []),
        );

        $this->assertNotEmpty($paths, 'Nenhum page_path configurado para o Inertia.');

        foreach (array_unique($paths) as $path) {
            $parent = dirname($path);
            $expected = basename($path);

            $this->assertDirectoryExists($parent);

            $this->assertContains(
                $expected,
                scandir($parent) ?: [],
                "O caminho configurado [{$path}] nao existe com esse exato uso de maiusculas. "
                .'Em sistemas de arquivos case-sensitive (CI e producao) o Inertia nao acharia '
                .'os componentes, e os testes com assertInertia falhariam.'
            );
        }
    }
}
