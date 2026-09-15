<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReverbClientConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_pagina_entrega_a_conexao_do_reverb_no_documento(): void
    {
        config([
            'reverb_client.key' => 'chave-de-teste',
            'reverb_client.host' => 'portatec.medeirostec.com.br',
            'reverb_client.port' => 443,
            'reverb_client.scheme' => 'https',
        ]);

        $response = $this->get('/app/login');

        $response->assertOk();
        $response->assertSee('window.__reverb', false);
        $response->assertSee('chave-de-teste', false);
        $response->assertSee('portatec.medeirostec.com.br', false);
    }

    /**
     * Host vazio significa "o mesmo domínio que serviu a página". É o que torna
     * a configuração correta em produção sem depender de ninguém preencher o
     * .env, e o que evita apontar o WebSocket para o host errado num ambiente
     * novo.
     */
    public function test_host_vazio_cai_no_host_da_requisicao(): void
    {
        config([
            'reverb_client.key' => 'chave-de-teste',
            'reverb_client.host' => null,
            'reverb_client.port' => 443,
            'reverb_client.scheme' => 'https',
        ]);

        $response = $this->get('http://exemplo.test/app/login');

        $response->assertOk();
        $response->assertSee('exemplo.test', false);
    }
}
