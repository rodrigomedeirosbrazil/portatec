<?php

declare(strict_types=1);

// Endereço do Reverb que o NAVEGADOR usa. É de propósito que ele não venha de
// broadcasting.connections.reverb.options: aquele é o endereço interno com que
// o PHP publica eventos (127.0.0.1:8080 em produção, mesmo container), e o
// navegador precisa do público.
//
// Os nomes VITE_* continuam porque já estão no .env do servidor e a regra 2 do
// AGENTS.md proíbe editar .env. O que mudou é quem os lê: antes o Vite, no
// build; agora o PHP, a cada requisição.

$scheme = env('VITE_REVERB_SCHEME', 'https');

return [
    'key' => env('VITE_REVERB_APP_KEY', env('REVERB_APP_KEY')),

    // Vazio significa "o mesmo host que serviu a página" — resolvido na view,
    // onde a requisição existe. Em produção o WebSocket passa pelo mesmo
    // domínio, então esse é o padrão certo.
    'host' => env('VITE_REVERB_HOST'),

    'port' => (int) env('VITE_REVERB_PORT', $scheme === 'https' ? 443 : 80),

    'scheme' => $scheme,
];
