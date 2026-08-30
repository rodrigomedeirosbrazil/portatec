# CLAUDE.md

As instruções deste repositório estão centralizadas em [AGENTS.md](./AGENTS.md).

**Leia o `AGENTS.md` antes de qualquer alteração** — ele cobre stack, arquitetura, domínio,
padrões de código, testes, i18n, CI/deploy e as regras rígidas do projeto.

Lembretes rápidos (o detalhe está no `AGENTS.md`):

- Todo comando PHP/Composer/Artisan/NPM roda via `./vendor/bin/sail`, nunca no host.
- Rode `./vendor/bin/sail pint` e `./vendor/bin/sail test` antes de considerar o trabalho pronto.
- Não leia nem edite o `.env`.

Não duplique conteúdo aqui: qualquer regra nova vai para o `AGENTS.md`.
