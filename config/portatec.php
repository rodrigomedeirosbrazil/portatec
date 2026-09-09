<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Super admins
    |--------------------------------------------------------------------------
    |
    | Não há tabela de papéis: super admin é decidido comparando o e-mail do
    | usuário com esta lista, alimentada pelo CSV da env
    | `PORTATEC_SUPER_ADMIN_EMAILS`.
    |
    | A leitura do `env()` precisa acontecer AQUI, e não dentro de
    | `User::hasRole()`. Em produção o entrypoint roda `artisan optimize`, que
    | grava o config cacheado; com o cache no lugar o Laravel deixa de carregar
    | o `.env`, e o `.env` chega ao container como arquivo montado (veja
    | `docker-compose-prod.yml`), não como variável de ambiente do processo.
    | Um `env()` fora de `config/` passa então a devolver `null` e cair no
    | default — era exatamente o que acontecia antes desta mudança, deixando a
    | lista do `.env` sem efeito nenhum em produção. Já dentro de `config/` a
    | avaliação ocorre durante o `config:cache`, com o `.env` ainda carregado,
    | e o valor certo é congelado no cache.
    |
    | Os e-mails são normalizados aqui (trim + minúsculas) para que a
    | comparação em `User::hasRole()` seja uma igualdade simples.
    |
    */

    'super_admin_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('PORTATEC_SUPER_ADMIN_EMAILS', 'contato@medeirostec.com.br'))
    ))),

];
