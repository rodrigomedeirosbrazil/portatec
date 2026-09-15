# Imagem de produção e deploy — plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tirar a imagem de produção de 1.34 GB para menos de 700 MB e remover o `npm run build` do arranque do container, sem mudar o que a aplicação faz.

**Architecture:** Deleções primeiro (contexto de build, pacotes não usados, porta e bloco `build:`), depois a única mudança de comportamento — as quatro variáveis de conexão do Reverb passam a ser entregues pelo servidor em runtime, em vez de embutidas no bundle —, e só então o Dockerfile vira multi-stage com o Node confinado ao estágio de build.

**Tech Stack:** Laravel 12 (PHP 8.4), Inertia + React, Vite 6 com plugin do wayfinder, Reverb, Horizon, supervisord, nginx, Docker, GitHub Actions.

**Spec:** [2026-09-15-imagem-e-deploy-design.md](../specs/2026-09-15-imagem-e-deploy-design.md)

---

## Regras deste repositório que valem para todas as tarefas

Do [AGENTS.md](../../../AGENTS.md):

- **Todo comando PHP/Composer/Artisan/NPM roda via `./vendor/bin/sail`**, nunca no host.
- **Nunca leia, edite ou exponha o `.env`.** A Task 4 foi desenhada em volta disso: ela reaproveita nomes de variável que já existem no servidor justamente para não precisar editar `.env` nenhum.
- **Não commite sem o usuário pedir.** Os passos de commit estão escritos, mas **pergunte uma vez, antes do primeiro**, e siga a resposta para os demais.
- `./vendor/bin/sail pint` e `./vendor/bin/sail test` antes de considerar qualquer tarefa pronta.
- Commits em Conventional Commits.

## Estrutura de arquivos

| Arquivo | Responsabilidade |
|---|---|
| `.dockerignore` (criar) | o que não entra no contexto de build — `.env` e `.git` acima de tudo |
| `docker/prod/Dockerfile` (modificar) | Tasks 2 e 5: deleções, depois multi-stage |
| `docker-compose-prod.yml` (modificar) | Tasks 3 e 6: porta, `build:`, `mem_limit`, healthcheck |
| `config/reverb_client.php` (criar) | endereço **público** do Reverb, separado do interno |
| `resources/views/app.blade.php` (modificar) | entrega esse endereço ao navegador no documento |
| `resources/js/echo.js` (modificar) | lê do documento em vez de `import.meta.env` |
| `docker/prod/docker-entrypoint.sh` (modificar) | deixa de reconstruir o frontend no arranque |
| `tests/Feature/ReverbClientConfigTest.php` (criar) | fixa que a configuração chega à página |
| `AGENTS.md` (modificar) | corrige a descrição do frontend e do entrypoint |

Ordem obrigatória: **a Task 5 depende da Task 4.** O Node só pode sair da imagem depois que o entrypoint parar de chamar `npm run build`.

---

### Task 1: `.dockerignore`

Hoje não existe nenhum, e o Dockerfile faz `COPY . $APP_HOME`: o contexto é o repositório inteiro, 936 MB. O `.git` está dentro da imagem publicada, e o `.env` só não está porque o runner do CI não tem um.

**Files:**
- Create: `.dockerignore`

- [ ] **Step 1: Escrever o arquivo**

```
# Contexto de build da imagem de produção. Duas coisas moram aqui: o que não
# pode vazar e o que não deve inchar a imagem. A primeira é a razão de existir.

# Segredos. A imagem viaja por scp e fica em disco na VPS; um .env assado numa
# camada é vazamento, não desperdício.
.env
.env.*
!.env.example

# Reinstalados dentro do build, com --no-dev. Com `**/` porque o padrão simples
# casa só na raiz do contexto, e há cópias inteiras destes diretórios dentro dos
# worktrees de agente em .claude/ — 400 MB que iam parar dentro da imagem.
vendor
node_modules
**/vendor
**/node_modules

# Worktrees de agente e configuração de ferramenta. Nada disso tem o que fazer
# numa imagem de produção.
.claude

# O histórico inteiro está hoje dentro da imagem publicada. Todo segredo que já
# passou por ele viaja junto.
.git
.gitignore
.github

# storage inteiro, e não subcaminhos: a suíte cria diretórios ali como root, e
# excluir item a item sempre deixa de fora o próximo que o Laravel inventar.
# O Dockerfile recria a estrutura que o framework exige.
storage

# Ferramental de desenvolvimento e artefatos locais.
tests
docs
phpunit.xml
pint.json
.editorconfig
.phpunit.result.cache
docker-compose-prod.yml
docker-compose.yml
database/database.sqlite
.DS_Store
```

- [ ] **Step 2: Verificar que o contexto encolheu e que segredo não entra**

Run:
```bash
cd /Users/rodrigo/dev/portatec
printf 'FROM alpine\nCOPY . /ctx\n' > /tmp/ctx-check.Dockerfile
docker build -q --no-cache -f /tmp/ctx-check.Dockerfile -t portatec-ctx-check . >/dev/null
docker run --rm portatec-ctx-check sh -c 'ls -a /ctx | grep -E "^\.env$|^vendor$|^\.git$|^node_modules$" || echo "AUSENTES: .env, .git, vendor, node_modules"; du -sh /ctx'
```
Expected: `AUSENTES: .env, .git, vendor, node_modules`, e o contexto abaixo de 50 MB (hoje são 936 MB).

- [ ] **Step 3: Limpar**

```bash
docker rmi -f portatec-ctx-check && rm /tmp/ctx-check.Dockerfile
```

- [ ] **Step 4: Commit**

```bash
git add .dockerignore
git commit -m "build: dockerignore, para .env e .git nao virarem camada de imagem

O Dockerfile faz COPY . e nao havia dockerignore: o .git inteiro esta
dentro da imagem publicada, e o .env so nao esta porque o runner do CI
nao tem um - um build local assaria as credenciais numa camada.

O corte de contexto (936 MB para menos de 50 MB) e consequencia."
```

---

### Task 2: tirar `certbot` e `nano` da imagem

`certbot` e `python3-certbot-nginx` estão instalados e nunca são usados: quem emite e renova certificado é o container do repositório `medeirostec`. Confirmado que o binário existe na imagem em produção.

**Files:**
- Modify: `docker/prod/Dockerfile`

- [ ] **Step 1: Editar o `apt-get`**

Trocar o bloco atual:

```dockerfile
RUN apt-get update \
    && curl -sL https://deb.nodesource.com/setup_22.x -o nodesource_setup.sh \
    && bash nodesource_setup.sh \
    && apt install -y \
    gettext libzip-dev libxml2-dev libpng-dev \
    nginx nano cron git unzip supervisor \
    certbot python3-certbot-nginx \
    nodejs \
    && rm -r /var/lib/apt/lists/*
```

por:

```dockerfile
# certbot e python3-certbot-nginx saíram: TLS é do container nginx do
# repositório medeirostec, e aqui eles nunca foram chamados. nano e cron
# também — o agendamento roda pelo supervisord, não pelo cron do sistema.
RUN apt-get update \
    && curl -sL https://deb.nodesource.com/setup_22.x -o nodesource_setup.sh \
    && bash nodesource_setup.sh \
    && apt install -y \
    gettext libzip-dev libxml2-dev libpng-dev \
    nginx git unzip supervisor \
    nodejs \
    && rm -r /var/lib/apt/lists/*
```

O `nodejs` continua **nesta tarefa** porque o entrypoint ainda chama `npm run build`. Ele sai na Task 5, depois da Task 4.

- [ ] **Step 2: Construir e conferir**

Run:
```bash
cd /Users/rodrigo/dev/portatec
docker build -f docker/prod/Dockerfile -t portatec:verify .
docker run --rm --entrypoint sh portatec:verify -c 'command -v certbot && echo "FALHA: certbot ainda na imagem" || echo "certbot: fora, ok"; command -v php && command -v nginx && command -v supervisord >/dev/null && echo "php, nginx e supervisord: presentes"'
docker image inspect portatec:verify --format '{{.Size}}' | awk '{printf "tamanho: %.0f MB\n", $1/1024/1024}'
```
Expected: `certbot: fora, ok`, os três binários presentes, e o tamanho abaixo dos 1341 MB de hoje.

- [ ] **Step 3: Commit**

```bash
git add docker/prod/Dockerfile
git commit -m "build: remove certbot, nano e cron da imagem de producao

TLS e do container nginx do medeirostec; certbot nunca foi chamado aqui.
O agendamento roda pelo supervisord, nao pelo cron do sistema."
```

---

### Task 3: compose de produção — porta 5173 e bloco `build:`

A 5173 é a porta do servidor de desenvolvimento do Vite, publicada em `0.0.0.0` na VPS, sem nada escutando. O bloco `build:` permite que um `docker compose up -d` no servidor decida compilar — num vCPU compartilhado com outros três sites — e é também o caminho pelo qual um `.env` local entraria numa imagem.

**Files:**
- Modify: `docker-compose-prod.yml`

- [ ] **Step 1: Editar o serviço `portatec`**

Remover o bloco `build:` inteiro:

```yaml
        build:
            context: ./
            dockerfile: ./docker/prod/Dockerfile
            args:
                WWWGROUP: '${WWWGROUP}'
```

e trocar o bloco `ports:` atual:

```yaml
        ports:
            # - '${APP_PORT:-80}:80'
            # - '${HTTPS_PORT:-443}:443'
            - '${VITE_PORT:-5173}:${VITE_PORT:-5173}'
            # - '${REVERB_PORT:-8080}:${REVERB_PORT:-8080}'
```

por um comentário, sem nenhuma porta publicada:

```yaml
        # Nenhuma porta publicada: quem fala com este container é o nginx do
        # medeirostec, pela rede medeirostec-network, por nome de serviço. A
        # 5173 que ficava aqui é a do servidor de desenvolvimento do Vite —
        # aberta na VPS, sem nada escutando.
```

Manter `image: portatec`, que é o que o `docker load` do deploy popula.

- [ ] **Step 2: Validar a sintaxe e a ausência de portas**

Run:
```bash
cd /Users/rodrigo/dev/portatec
docker compose -f docker-compose-prod.yml config --quiet && echo "sintaxe ok"
docker compose -f docker-compose-prod.yml config | grep -A3 "portatec:" | grep -c "published:" || echo "servico portatec sem porta publicada"
docker compose -f docker-compose-prod.yml config | grep -c "dockerfile:" || echo "sem bloco build"
```
Expected: `sintaxe ok`, `servico portatec sem porta publicada`, `sem bloco build`.

- [ ] **Step 3: Commit**

```bash
git add docker-compose-prod.yml
git commit -m "build: fecha a porta 5173 e tira o bloco build do compose de producao

A 5173 e a do servidor de desenvolvimento do Vite, publicada na VPS sem
nada escutando. O bloco build deixava o up -d compilar no servidor, num
vCPU dividido com outros tres sites."
```

---

### Task 4: conexão do Reverb entregue em runtime

O `docker-entrypoint.sh` roda `npm run build` a cada arranque — 16,57 s de Vite medidos no log de produção — só para embutir quatro valores no bundle. Eles passam a vir do servidor, no documento.

**Por que os nomes `VITE_*` continuam:** eles já estão no `.env` do servidor com o endereço público, e a regra 2 do `AGENTS.md` proíbe editar `.env`. Manter os nomes é o que faz esta mudança não exigir nenhuma alteração de configuração em produção. Quem passa a lê-los é o PHP, não o Vite.

**Files:**
- Create: `config/reverb_client.php`
- Create: `tests/Feature/ReverbClientConfigTest.php`
- Modify: `resources/views/app.blade.php`
- Modify: `resources/js/echo.js`
- Modify: `docker/prod/docker-entrypoint.sh`

- [ ] **Step 1: Escrever o teste que falha**

`tests/Feature/ReverbClientConfigTest.php`:

```php
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
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `./vendor/bin/sail test --filter=ReverbClientConfigTest`
Expected: FAIL — a página não contém `window.__reverb`.

- [ ] **Step 3: Criar `config/reverb_client.php`**

```php
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
```

- [ ] **Step 4: Entregar a configuração no documento**

Em `resources/views/app.blade.php`, inserir **antes** da linha `@viteReactRefresh` (linha 12):

```blade
    {{-- Conexão do Reverb, entregue pelo servidor em runtime. Antes vinha de
         import.meta.env, o que obrigava a reconstruir o bundle no arranque do
         container para embutir a URL pública — 17 s a cada deploy e o Node
         inteiro dentro da imagem. Precisa vir ANTES do @vite: o echo.js é
         importado no topo do app.tsx e constrói o Echo no momento do import. --}}
    <script>
        window.__reverb = @json(array_merge(config('reverb_client'), [
            'host' => config('reverb_client.host') ?: request()->getHost(),
        ]));
    </script>
```

- [ ] **Step 5: Ler do documento no `echo.js`**

Substituir `resources/js/echo.js` inteiro por:

```js
import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Vem do documento, não do bundle: ver resources/views/app.blade.php. Com
// import.meta.env, estes quatro valores eram assados no build, e o container
// precisava reconstruir o frontend no arranque para acertá-los.
const reverb = window.__reverb ?? {};

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: reverb.key,
    wsHost: reverb.host,
    wsPort: reverb.port,
    wssPort: reverb.port,
    forceTLS: reverb.scheme === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

- [ ] **Step 6: Rodar e ver passar**

Run: `./vendor/bin/sail test --filter=ReverbClientConfigTest`
Expected: PASS (2 testes).

- [ ] **Step 7: Tirar o `npm run build` do entrypoint**

Em `docker/prod/docker-entrypoint.sh`, remover o bloco:

```sh
# Rebuild frontend assets so VITE_* (e.g. VITE_REVERB_*) from the mounted .env are embedded.
# The image is built in CI without .env, so the initial build has undefined Reverb URL and WebSocket fails.
chown -R www-data:www-data /var/www/storage
chown www-data:www-data /var/www/database/database.sqlite
# wayfinder:generate (run during the build below) writes into these dirs as www-data.
chown -R www-data:www-data /var/www/resources/js/actions /var/www/resources/js/routes /var/www/resources/js/wayfinder

echo "Building frontend assets with current .env (VITE_* for Reverb/WebSocket)..."
su www-data -s /bin/sh -c "cd /var/www && npm run build"
```

e deixar no lugar:

```sh
# storage e o banco são montados do host: o dono precisa ser acertado a cada
# arranque. Os chowns de resources/js/* saíram junto com o `npm run build` —
# o bundle agora vem pronto do CI, e a conexão do Reverb chega em runtime pelo
# documento (config/reverb_client.php).
chown -R www-data:www-data /var/www/storage
chown www-data:www-data /var/www/database/database.sqlite
```

- [ ] **Step 8: Rodar a suíte inteira e o lint**

Run:
```bash
./vendor/bin/sail pint
./vendor/bin/sail test
```
Expected: Pint sem alteração pendente e a suíte inteira passando.

- [ ] **Step 9: Verificar no navegador, localmente**

Run: `./vendor/bin/sail npm run build` e abrir o app no navegador.
Expected: no console, `window.__reverb` traz os quatro campos preenchidos, e um dispositivo mudando de estado atualiza **sem recarregar a página**. Nenhum critério visual substitui este: é o que prova que o WebSocket continua conectando.

- [ ] **Step 10: Commit**

```bash
git add config/reverb_client.php resources/views/app.blade.php resources/js/echo.js docker/prod/docker-entrypoint.sh tests/Feature/ReverbClientConfigTest.php
git commit -m "feat: conexao do Reverb entregue em runtime, nao embutida no bundle

O entrypoint reconstruia o frontend a cada arranque (16,57 s medidos em
producao) so para embutir quatro valores de conexao. Eles passam a vir do
servidor no documento, e o bundle volta a ser o que o CI produziu.

Os nomes VITE_* continuam porque ja estao no .env do servidor e a regra 2
do AGENTS.md proibe edita-lo; quem os le agora e o PHP."
```

---

### Task 5: Dockerfile multi-stage — Node fora da imagem final

Só agora, com o entrypoint sem `npm`, o Node pode sair. São 306 MB de `node_modules` mais o runtime do Node, e o composer (134 MB) junto.

**Atenção:** o `vite build` roda o plugin do **wayfinder**, que chama `php artisan`. O estágio de build precisa de PHP **e** do `vendor` — um estágio só de Node não funciona.

**Files:**
- Modify: `docker/prod/Dockerfile`

- [ ] **Step 1: Reescrever o Dockerfile**

```dockerfile
# Estágio de build: dependências PHP e bundle do frontend.
#
# Precisa de PHP e do vendor, e não só de Node: o `vite build` roda o plugin do
# wayfinder, que chama `php artisan` para gerar as rotas em TypeScript.
FROM php:8.4.3-fpm AS build

RUN apt-get update \
    && curl -sL https://deb.nodesource.com/setup_22.x -o nodesource_setup.sh \
    && bash nodesource_setup.sh \
    && apt install -y \
    gettext libzip-dev libxml2-dev libpng-dev \
    git unzip \
    nodejs \
    && rm -r /var/lib/apt/lists/*

RUN pecl install --force redis \
    && rm -rf /tmp/pear \
    && docker-php-ext-enable redis

RUN docker-php-ext-install \
    zip calendar dom gd \
    intl pcntl bcmath

ENV APP_HOME=/var/www
WORKDIR $APP_HOME

COPY . $APP_HOME

# Antes do composer, e não depois: o `composer install` dispara
# `artisan package:discover`, que boota o framework e resolve o caminho de cache
# das views. Sem os diretórios ele morre com "Please provide a valid cache
# path" — e eles não chegam pelo contexto, porque o .dockerignore exclui
# storage/ inteiro.
RUN mkdir -p storage/app storage/logs \
             storage/framework/sessions \
             storage/framework/views \
             storage/framework/cache

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && composer install --no-dev --optimize-autoloader

RUN npm ci && npm run build

# Imagem final: sem Node, sem composer, sem node_modules.
FROM php:8.4.3-fpm

# Mesma lista do estágio de build, menos o que era só de build: nodejs (e o
# repositório da nodesource), git e unzip. certbot, nano e cron já tinham saído.
RUN apt-get update \
    && apt install -y \
    gettext libzip-dev libxml2-dev libpng-dev \
    nginx supervisor \
    && rm -r /var/lib/apt/lists/*

RUN pecl install --force redis \
    && rm -rf /tmp/pear \
    && docker-php-ext-enable redis

RUN docker-php-ext-install \
    zip calendar dom gd \
    intl pcntl bcmath

ENV APP_HOME=/var/www
WORKDIR $APP_HOME

# O código vem do contexto (sem vendor e sem node_modules, pelo .dockerignore);
# vendor e o bundle vêm prontos do estágio de build.
COPY --chown=www-data:www-data . $APP_HOME
COPY --from=build --chown=www-data:www-data /var/www/vendor $APP_HOME/vendor
COPY --from=build --chown=www-data:www-data /var/www/public/build $APP_HOME/public/build

RUN mkdir -p storage/app \
    && mkdir -p storage/logs \
    && mkdir -p storage/framework/sessions \
    && mkdir -p storage/framework/views \
    && mkdir -p storage/framework/cache \
    && chown -R www-data:www-data storage \
    && chmod -R 775 storage

# O `php artisan optimize` que rodava aqui saiu: no build não existe .env, então
# ele cacheava configuração com valores de placeholder. O entrypoint já roda
# optimize em runtime, com o .env montado — que é onde faz sentido.

COPY ./docker/prod/supervisord.conf /etc/supervisord.conf
COPY ./docker/prod/nginx.conf /etc/nginx/nginx.conf
COPY ./docker/prod/www.conf /usr/local/etc/php-fpm.d/www.conf
RUN mv /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
COPY ./docker/prod/sites/* /etc/nginx/sites-available/

COPY ./docker/prod/docker-entrypoint.sh /
RUN chmod +x /docker-entrypoint.sh

CMD ["/docker-entrypoint.sh"]
```

- [ ] **Step 2: Construir e medir**

Run:
```bash
cd /Users/rodrigo/dev/portatec
docker build -f docker/prod/Dockerfile -t portatec:verify .
docker image inspect portatec:verify --format '{{.Size}}' | awk '{printf "tamanho: %.0f MB (era 1341 MB)\n", $1/1024/1024}'
```
Expected: build conclui, e o tamanho abaixo de 700 MB.

**Compare local com local.** Os 1341 MB são de amd64 na VPS; uma máquina arm64
dá outro número para a mesma coisa. Meça a imagem atual antes de reescrever o
Dockerfile (`-t portatec:baseline`) e compare com essa. Medido em arm64 na
execução de 2026-09-15: **1251 MB → 693 MB**.

- [ ] **Step 3: Conferir o conteúdo da imagem**

Run:
```bash
docker run --rm --entrypoint sh portatec:verify -c '
command -v npm && echo "FALHA: npm ainda na imagem" || echo "npm: fora, ok"
command -v composer && echo "FALHA: composer ainda na imagem" || echo "composer: fora, ok"
command -v certbot && echo "FALHA: certbot ainda na imagem" || echo "certbot: fora, ok"
test -d /var/www/node_modules && echo "FALHA: node_modules na imagem" || echo "node_modules: fora, ok"
test -f /var/www/.env && echo "FALHA: .env na imagem" || echo ".env: fora, ok"
test -d /var/www/.git && echo "FALHA: .git na imagem" || echo ".git: fora, ok"
test -f /var/www/vendor/autoload.php && echo "vendor: presente, ok"
test -d /var/www/public/build && echo "bundle: presente, ok"
php -m | tr "\n" " "'
```
Expected: todas as linhas terminando em `ok`, e entre os módulos: `redis`, `intl`, `gd`, `zip`, `bcmath`, `pcntl`, `calendar`, `dom`.

Se faltar extensão, **pare**: a lista do estágio final tem que ser idêntica à de hoje, e qualquer ausência quebra a aplicação em produção de um jeito que só aparece na rota que usa aquela extensão.

- [ ] **Step 4: Commit**

```bash
git add docker/prod/Dockerfile
git commit -m "build: Dockerfile multi-stage, sem Node nem composer na imagem final

node_modules (306 MB), o runtime do Node e o composer (134 MB) existiam na
imagem final so porque o entrypoint reconstruia o frontend no arranque.
Com isso resolvido na tarefa anterior, o bundle vem pronto do estagio de
build - que precisa de PHP e vendor, porque o vite build chama o wayfinder,
que chama artisan.

Sai tambem o artisan optimize do build: sem .env ali, ele cacheava
configuracao com placeholder, e o entrypoint ja refaz em runtime."
```

---

### Task 6: limite de memória e healthcheck

O serviço é o maior consumidor de RAM da VPS (472 MB) e divide 3.8 GB sem swap com o mysql do biolitoral. Sem limite, um vazamento aqui faz o OOM killer escolher a vítima por heurística.

**Files:**
- Modify: `docker-compose-prod.yml`

- [ ] **Step 1: Acrescentar `mem_limit` e `healthcheck` ao serviço `portatec`**

```yaml
        # 472 MB observados em produção; o teto existe para um vazamento aqui
        # ser contido neste container em vez de sorteado pelo OOM killer entre
        # todos os da VPS — inclusive o mysql do biolitoral.
        mem_limit: 768m
        healthcheck:
            # /up é a rota de saúde que o bootstrap/app.php já expõe, servida
            # pelo nginx interno. Cobre nginx e php-fpm; NÃO cobre os outros
            # cinco processos do supervisor, que seguem invisíveis ao docker ps.
            test: ["CMD", "curl", "-fsS", "http://127.0.0.1/up"]
            interval: 30s
            timeout: 5s
            retries: 3
            start_period: 60s
```

- [ ] **Step 2: Conferir que o `curl` existe na imagem final**

Run: `docker run --rm --entrypoint sh portatec:verify -c 'command -v curl >/dev/null && echo "curl: ok" || echo "curl: AUSENTE"'`
Expected: `curl: ok`. Se estiver ausente, troque o teste do healthcheck por
`["CMD", "php", "-r", "exit(@file_get_contents('http://127.0.0.1/up') ? 0 : 1);"]` — não acrescente `curl` ao `apt` só para isso.

- [ ] **Step 3: Validar a sintaxe**

Run: `docker compose -f docker-compose-prod.yml config --quiet && echo "sintaxe ok"`
Expected: `sintaxe ok`

- [ ] **Step 4: Commit**

```bash
git add docker-compose-prod.yml
git commit -m "build: mem_limit e healthcheck no servico de producao

O container e o maior consumidor de RAM da VPS e dividia 3.8 GB sem swap
sem teto nenhum. O healthcheck em /up cobre nginx e php-fpm - os outros
cinco processos do supervisor seguem invisiveis ao docker ps."
```

---

### Task 7: `AGENTS.md`

A seção 3 descreve o app do cliente como **Livewire**. O `resources/views/app.blade.php` carrega `app.tsx` com `@viteReactRefresh` e `resources/js/hooks/` usa `useEcho` em componentes React: a interface é Inertia com React. A seção 9 descreve um entrypoint que a Task 4 mudou.

`AGENTS.md` é o arquivo que todo agente lê antes de mexer no repositório — errado ali custa mais que em qualquer outro lugar.

**Files:**
- Modify: `AGENTS.md`

- [ ] **Step 1: Corrigir a descrição do app do cliente (seção 3)**

Trocar:

```markdown
- **`/app/*` — app do cliente**: Livewire (`app/Livewire`), rotas nomeadas `app.*` em
  `routes/web.php`, protegidas pelo middleware `auth`.
```

por:

```markdown
- **`/app/*` — app do cliente**: Inertia + React (`resources/js/app.tsx`, páginas em
  `resources/js/pages`), rotas nomeadas `app.*` em `routes/web.php`, protegidas pelo
  middleware `auth`. O documento é `resources/views/app.blade.php`.
```

- [ ] **Step 2: Corrigir a descrição do deploy (seção 9)**

Trocar:

```markdown
- O entrypoint de produção roda `npm run build`, `artisan migrate --force` e `artisan optimize`.
```

por:

```markdown
- O entrypoint de produção roda `artisan migrate --force` e `artisan optimize`. O bundle
  do frontend vem pronto da imagem: ele é construído uma vez, no CI. A conexão do Reverb
  usada pelo navegador chega em runtime pelo documento (`config/reverb_client.php`), e não
  embutida no bundle — ver
  [docs/superpowers/specs/2026-09-15-imagem-e-deploy-design.md](docs/superpowers/specs/2026-09-15-imagem-e-deploy-design.md).
```

- [ ] **Step 3: Conferir que nada mais no arquivo cita Livewire**

Run: `grep -n -i "livewire" AGENTS.md CLAUDE.md`
Expected: nenhuma linha. Se houver, corrija também.

- [ ] **Step 4: Commit**

```bash
git add AGENTS.md
git commit -m "docs: corrige o frontend e o entrypoint no AGENTS.md

A secao 3 descrevia o app do cliente como Livewire; ele e Inertia + React
desde o redesign. A secao 9 descrevia o npm run build no arranque, que
saiu nesta entrega."
```

---

## Verificação depois do deploy

Não são passos de implementação: são os critérios da seção 9 da spec, para rodar uma vez depois da tag que publicar isto.

- [ ] `ssh` na VPS e `docker image inspect portatec:latest --format '{{.Size}}'` abaixo de 700 MB (eram 1341 MB).
- [ ] O log de arranque vai das migrations direto ao supervisor, **sem** `Building frontend assets`.
- [ ] Tempo entre o `up -d` e o primeiro 200 do site **pelo menos 15 s menor** que o de hoje.
- [ ] Com o app aberto e um dispositivo mudando de estado, o status atualiza **sem recarregar a página**.
- [ ] `docker ps` mostra o container `healthy` e `docker stats` dentro do `mem_limit`.
- [ ] `https://portatec.medeirostec.com.br` responde, e os outros três sites da VPS seguem no ar.

## Se precisar parar no meio

As Tasks 1, 2, 3, 6 e 7 são independentes e reversíveis por `git revert` — nenhuma muda comportamento de aplicação. **A Task 4 muda**, e a Task 5 depende dela. Parar depois da Task 3 deixa o repositório melhor que o atual e sem risco pendente.
