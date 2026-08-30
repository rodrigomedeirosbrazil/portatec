<p align="center">
    <h1 align="center">PortaTec</h1>
    <p align="center">Controle de acesso e automação para imóveis de temporada</p>
</p>

## Sobre

O PortaTec conecta as reservas de um imóvel aos dispositivos instalados nele: importa as
reservas das plataformas de aluguel, gera um PIN temporário para cada hóspede, envia esse PIN
para as fechaduras e permite controlar e acompanhar os dispositivos em tempo real.

### Funcionalidades

- **PINs de acesso** — geração de códigos de 6 dígitos com janela de validade, únicos por
  imóvel, sincronizados automaticamente com os dispositivos.
- **Reservas** — cadastro manual ou importação automática via feed iCal (Airbnb e outras
  plataformas), com criação e revogação de PIN acompanhando a reserva.
- **Dispositivos** — controle e monitoramento de dispositivos com firmware próprio (via MQTT)
  e de dispositivos Tuya (via API), com status e confirmação de comando em tempo real.
- **Imóveis e equipe** — múltiplos imóveis por conta, membros com papéis (`admin`, `host`),
  clonagem de configuração entre imóveis.
- **Auditoria** — registro de eventos de acesso e de todos os comandos enviados aos
  dispositivos.
- **Painel administrativo** — área interna em Filament, com sessão de impersonação registrada
  para suporte.

## Stack

Laravel 11 · PHP 8.4 · Livewire 3 · Filament 4 · Tailwind CSS 4 (Vite) · Redis + Horizon ·
Laravel Reverb (WebSocket) · MQTT

## Ambiente de desenvolvimento

O ambiente roda em Docker via **Laravel Sail**. Todos os comandos de PHP, Composer, Artisan e
NPM devem ser executados através do Sail — nunca diretamente no host.

### Pré-requisitos

- Docker e Docker Compose

O banco não é provisionado pelo `docker-compose.yml`: em desenvolvimento o padrão é SQLite no
arquivo `database/database.sqlite`. Para usar outro banco, ajuste as variáveis `DB_*` do `.env`.

### Instalação

```bash
git clone <url-do-repositorio> portatec
cd portatec
cp .env.example .env
touch database/database.sqlite
```

Ajuste as variáveis do `.env` (banco, MQTT, Reverb, credenciais Tuya) e então:

```bash
docker run --rm -v "$(pwd)":/var/www/html -w /var/www/html laravelsail/php84-composer:latest composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

A aplicação fica disponível na porta definida por `APP_PORT` (por exemplo,
`http://localhost:8088`): `/app` para o app do cliente e `/admin` para o painel administrativo.

### Serviços do compose

| Serviço | Função |
|---|---|
| `laravel.test` | aplicação, Vite e Reverb |
| `redis` | cache e filas |
| `mosquitto` | broker MQTT local |

### Processos auxiliares

```bash
./vendor/bin/sail artisan horizon        # filas
./vendor/bin/sail artisan reverb:start   # websocket
./vendor/bin/sail artisan mqtt:subscribe # ponte MQTT
./vendor/bin/sail artisan schedule:work  # agendador
```

## Comandos do dia a dia

```bash
./vendor/bin/sail test                   # suíte de testes
./vendor/bin/sail pint                   # formatação de código
./vendor/bin/sail artisan bookings:sync      # importa reservas dos feeds iCal
./vendor/bin/sail artisan access-codes:sync  # ressincroniza PINs nos dispositivos
```

## Deploy

O deploy é automatizado pelo GitHub Actions: uma tag no formato `20*-*-*.*` (ou execução manual
do workflow) constrói a imagem de `docker/prod/Dockerfile`, envia para o servidor e sobe os
containers. O entrypoint executa build de assets, `migrate --force` e `optimize`; o supervisord
mantém nginx, php-fpm, scheduler, Horizon, Reverb e o subscriber MQTT.

## Contribuindo

As convenções do projeto — arquitetura, domínio, padrões de código, testes, i18n e CI/deploy —
estão em [AGENTS.md](./AGENTS.md). Leia antes de abrir um PR. Commits seguem
[Conventional Commits](https://www.conventionalcommits.org/pt-br/v1.0.0/).

## Segurança

Encontrou uma vulnerabilidade? Envie um e-mail para
[security@portatec.com](mailto:security@portatec.com) em vez de abrir uma issue pública.

## Licença

Software proprietário. Todos os direitos reservados.
