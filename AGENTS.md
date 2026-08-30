# AGENTS.md

Documento único de instruções para agentes de IA (Claude Code, Gemini CLI, Cursor, Codex etc.)
que trabalham neste repositório. `CLAUDE.md` e `GEMINI.md` apenas apontam para cá.

---

## 1. Visão geral

**PortaTec** — sistema de controle de acesso e automação para imóveis de aluguel de curta
temporada: gera PINs temporários por reserva, sincroniza esses PINs com fechaduras/dispositivos,
importa reservas via iCal (Airbnb e afins) e controla dispositivos em tempo real.

| Item | Valor |
|---|---|
| Framework | Laravel 11 (`laravel/framework ^11.31`) |
| PHP | `^8.2` no composer; imagens e CI usam **8.4** |
| UI do app | Livewire 3 + Blade + Tailwind CSS 4 (Vite) |
| UI de admin | **Filament 4** (`filament/filament ^4.0`) no painel `/admin` |
| Filas | Redis + **Horizon** |
| Realtime | **Reverb** (WebSocket) + Laravel Echo |
| IoT | MQTT (`php-mqtt/laravel-client`) e API Tuya |
| iCal | `sabre/vobject` |
| Testes | PHPUnit 11 |
| Locale padrão | `pt_BR` |

---

## 2. Ambiente de desenvolvimento (Sail obrigatório)

**Todo comando PHP/Composer/Artisan/NPM roda dentro do Sail.** Nunca execute no host.

```bash
./vendor/bin/sail up -d          # sobe app, redis e mosquitto
./vendor/bin/sail down
./vendor/bin/sail composer <cmd>
./vendor/bin/sail artisan <cmd>
./vendor/bin/sail npm <cmd>
./vendor/bin/sail test           # suíte completa
./vendor/bin/sail pint           # formatação
```

Proibido neste repo: `composer ...`, `php artisan ...`, `phpunit ...`, `pint ...` e `npm ...`
direto no host.

Serviços do `docker-compose.yml`: `laravel.test` (app + Vite + Reverb), `redis`, `mosquitto`.
**Não há container de banco**: em desenvolvimento o padrão é **SQLite** no arquivo
`database/database.sqlite` (`config/database.php` usa `env('DB_CONNECTION', 'sqlite')`). Para
apontar para outro banco, ajuste as variáveis `DB_*` do `.env` (veja `.env.example`).

Configuração local típica (definida no `.env`): SQLite como banco, **Redis** para cache, sessão
e filas, broadcast via **Reverb**, MQTT apontando para o serviço `mosquitto` e Telescope
desligado. Como `QUEUE_CONNECTION=redis`, jobs enfileirados (ex.: `SyncIntegrationBookingsJob`)
só rodam com o Horizon ativo — se um teste manual "não faz nada", verifique isso antes.
As credenciais Tuya ficam comentadas por padrão: sem elas, o caminho de dispositivos Tuya não
funciona localmente.

Processos de longa duração (mantidos pelo supervisord em produção; em dev, rode sob demanda):

```bash
./vendor/bin/sail artisan horizon        # filas
./vendor/bin/sail artisan reverb:start   # websocket
./vendor/bin/sail artisan mqtt:subscribe # ponte MQTT <-> app
./vendor/bin/sail artisan schedule:work  # agendador
```

---

## 3. Mapa do repositório

```
app/
  Console/Commands/    mqtt:subscribe, access-codes:sync, bookings:sync
  Contracts/           ICalParserInterface
  DTOs/                BookingDTO
  Enums/               DeviceBrandEnum, DeviceTypeEnum, DeviceStatusEnum,
                       PlaceRoleEnum, BookingDeletionReasonEnum
  Events/              eventos de broadcast de dispositivo/place/access code
  Filament/            painel administrativo (Resources + Pages)
  Http/Controllers/    auth, impersonation, DeviceController (API de firmware)
  Jobs/                SyncIntegrationBookingsJob
  Livewire/            telas do app do cliente (/app/*)
  Models/              domínio (ver seção 4)
  Observers/           AccessCodeObserver, BookingObserver
  Policies/            autorização por model
  Providers/Filament/  AdminPanelProvider (painel id "admin", path /admin)
  Services/            regra de negócio (ver seção 5)
config/                inclui tuya.php e mqtt-client.php customizados
database/migrations/   ~20 migrations
docker/8.4/            imagem de desenvolvimento (Sail)
docker/prod/           imagem de produção (nginx + php-fpm + supervisord)
resources/lang/pt_BR/  traduções da aplicação (app.php, auth.php, validation.php, ...)
resources/views/       layouts, components reutilizáveis, telas livewire
routes/                web.php, api.php, channels.php, console.php (schedule)
tests/                 Unit/ e Feature/ + Fixtures/ (arquivos .ics reais)
```

### Duas interfaces distintas

- **`/app/*` — app do cliente**: Livewire (`app/Livewire`), rotas nomeadas `app.*` em
  `routes/web.php`, protegidas pelo middleware `auth`.
- **`/admin` — painel interno**: Filament, acessível somente a super admin
  (`User::canAccessPanel`).

---

## 4. Domínio

- **Place** — o imóvel. Centro do modelo: dispositivos, reservas, PINs e membros pendem dele.
- **PlaceUser / PlaceRoleEnum** (`admin`, `host`) — vínculo usuário↔place. **Toda consulta do
  app do cliente deve ser escopada pelos places do usuário**
  (ver `tests/Feature/PlaceUsersIsolationTest.php`).
- **Device / DeviceFunction / PlaceDeviceFunction** — dispositivo físico e suas funções
  (`switch`, `sensor`, `button`); marca via `DeviceBrandEnum` (`portatec` = firmware próprio
  via MQTT, `tuya` = API Tuya).
- **AccessCode** — PIN de 6 dígitos com janela `start`/`end`, único por place
  (`AccessCodeGeneratorService`).
- **Booking** — reserva (`SoftDeletes`), origem manual ou integração; `deletion_reason` via
  `BookingDeletionReasonEnum`. Dispara a criação de AccessCode pelo `BookingObserver`.
- **Integration / Platform** — feed iCal de uma plataforma (ex.: Airbnb) associado a um place.
- **AccessEvent / CommandLog** — auditoria de acessos e de comandos enviados a dispositivos.
- **ImpersonationSession** — super admin assume a sessão de um cliente; início e fim são
  registrados (`StartImpersonationController` / `StopImpersonationController`, encerrada
  também no logout).

### Papéis

Não há pacote de roles. `User::hasRole('super_admin')` compara o e-mail com a lista da env
`PORTATEC_SUPER_ADMIN_EMAILS` (CSV); qualquer outro papel retorna `false`. A autorização real
de recursos vive nas **Policies**.

---

## 5. Serviços e integrações

| Serviço | Responsabilidade |
|---|---|
| `Services/AccessCode/AccessCodeGeneratorService` | gera PIN de 6 dígitos sem colisão no place |
| `Services/AccessCodeSyncService` | envia o conjunto de PINs válidos aos dispositivos do place |
| `Services/Device/DeviceCommandService` | envia comandos e trata ack/status/pulse/access-event |
| `Services/DeviceService` | operações de dispositivo |
| `Services/ICalParser` + `ICalSyncService` | baixa e interpreta feeds iCal, cria/atualiza bookings |
| `Services/PlaceCloneService` | duplica um place com dispositivos/membros |
| `Services/Tuya/{Client,TuyaService}` | assinatura e chamadas à API Tuya (`config/tuya.php`) |

### MQTT

Tópicos assinados por `mqtt:subscribe` (o `+` é o `chip_id` do dispositivo):

```
device/+/ack                 device/+/pulse
device/+/status              device/+/event
device/+/access-codes/ack
```

Payloads são JSON; mensagens inválidas são logadas e descartadas.

### Broadcast (`routes/channels.php`)

- `device-sync.{chipId}` — público, usado no provisionamento.
- `Place.Device.Status.{placeId}`, `Place.Device.Command.Ack.{placeId}` e
  `Place.Device.Function.Status.{placeId}` — exigem super admin **ou** vínculo em `place_users`.

### Agendamento (`routes/console.php`)

- `access-codes:sync` diariamente às 02:00.
- `bookings:sync` diariamente às 06:00 em `America/Sao_Paulo`.

Ao mexer no schedule, atualize `tests/Unit/ScheduleTest.php`.

---

## 6. Padrões de código

- PSR-12 + convenções do Laravel; formate com `./vendor/bin/sail pint` (preset padrão, sem
  `pint.json`).
- `declare(strict_types=1);` no topo de arquivos novos em `app/` (padrão majoritário do repo).
- Tipagem explícita em parâmetros e retornos; injeção de dependência no construtor.
- **Early return** em vez de `if/else` aninhado.
- Prefira helpers a Facades quando houver equivalente (`auth()`, `config()`, `now()`); mantenha
  `auth()` — não troque por `filament()`.
- Eloquent/Query Builder em vez de SQL cru; use as relações já declaradas nos models.
- Regra de negócio em Services, não em componentes Livewire, controllers ou resources Filament.
- Efeitos colaterais de modelo em Observers (`AccessCodeObserver`, `BookingObserver`).
- Autorização sempre via Policy.
- Nada de segredo hard-coded: use `config('...')`, e `env()` apenas dentro de `config/`.

### Frontend

- Já existem componentes Blade reutilizáveis em `resources/views/components`
  (`page-header`, `empty-state`, `status-badge`, `search-input`, `place-select`,
  `loading-overlay`, `device-control/*`). **Reutilize antes de criar novos.**
- Tailwind 4 via Vite; nada de CSS solto.

### i18n

Todo texto visível ao usuário passa por `__('app.<chave>')`, com a tradução em
`resources/lang/pt_BR/app.php`. Não escreva strings literais em views novas.

---

## 7. Testes

- PHPUnit 11, suítes `Unit` e `Feature` (`phpunit.xml`); conexão `testing` = SQLite em memória.
- Use `RefreshDatabase`; `Tests\TestCase` já aplica `withoutVite()` e desliga o broadcasting.
- Toda feature nova precisa de teste; todo bugfix precisa de teste de regressão.
- Fixtures reais de iCal ficam em `tests/Fixtures/` — prefira-as a inventar um `.ics` novo.

```bash
./vendor/bin/sail test
./vendor/bin/sail test --filter=AccessCodeGeneratorServiceTest
./vendor/bin/sail artisan test tests/Feature/BookingAccessCodeFlowTest.php
```

Nunca declare "pronto" sem rodar os testes e ver a saída.

---

## 8. Banco de dados

- Migrations em `database/migrations`.
- **Não crie migration que altere outra migration criada no mesmo commit/branch** — edite a
  original enquanto ela não foi mergeada.
- Índices e chaves estrangeiras explícitos; como os testes rodam em SQLite, evite recursos
  exclusivos de MySQL nas migrations.

---

## 9. Git, CI e deploy

- Commits em **Conventional Commits** (`feat:`, `fix:`, `chore:`, `refactor:`), como no histórico.
- `.github/workflows/run-tests.yml`: em push na `main` e em PRs que tocam PHP, roda
  `php artisan migrate:fresh && php artisan test` em PHP 8.4 (no CI, sem Sail).
- `.github/workflows/deploy.yml`: dispara em tags no formato `20*-*-*.*` (ou manualmente),
  builda `docker/prod/Dockerfile`, envia a imagem por SSH e sobe com `docker compose`.
- O entrypoint de produção roda `npm run build`, `artisan migrate --force` e `artisan optimize`.
- Em produção o supervisord mantém: php-fpm, nginx, scheduler, horizon, reverb e mqtt-subscriber.

---

## 10. Regras rígidas (resumo)

1. Nunca rode PHP/Composer/Artisan/NPM fora do Sail.
2. Nunca leia, edite ou exponha o conteúdo do `.env`.
3. Não commite nem faça push sem o usuário pedir.
4. Não adicione dependência sem checar se o Laravel ou o próprio projeto já resolve.
5. Não introduza strings de UI fora de `resources/lang/pt_BR`.
6. Não burle Policies nem consulte dados sem escopo de place.
7. Não afirme que algo funciona sem ter executado o teste correspondente.
