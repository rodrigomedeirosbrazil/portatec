<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * O seeder escreve direto com `DB::table()`, entao ele nao quebra quando um
 * model some - so quando a TABELA some, e ai em runtime. Foi o que aconteceu
 * ao remover `place_device_functions`: a suite seguiu verde e `db:seed` parou.
 */
class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_runs(): void
    {
        $this->assertSame(0, Artisan::call('db:seed', ['--force' => true]));
    }
}
