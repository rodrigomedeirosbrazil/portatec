# AGENTS.md

## Execucao de PHP/Composer

- Sempre execute comandos de PHP e Composer via Sail neste repositório.
- Use `./vendor/bin/sail` como prefixo para comandos de desenvolvimento e manutenção.

## Exemplos obrigatórios

- Composer: `./vendor/bin/sail composer <comando>`
- Artisan: `./vendor/bin/sail artisan <comando>`
- PHPUnit/Pest: `./vendor/bin/sail test` ou `./vendor/bin/sail php artisan test`
- Pint: `./vendor/bin/sail pint`

## Proibido (neste repo)

- Não executar `composer ...`, `php artisan ...`, `phpunit ...` e `pint ...` diretamente no host.
