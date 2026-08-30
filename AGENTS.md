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

---

## 11. Integração Tuya — Referência técnica

### Visão geral

O Portatec integra dispositivos Tuya **sem conta de developer** e **sem credenciais do portal
`iot.tuya.com`**. A autenticação e a comunicação usam o mesmo mecanismo do Home Assistant — o
[`tuya-device-sharing-sdk`](https://github.com/tuya/tuya-device-sharing-sdk) — iniciando em
`apigw.iotbing.com` e seguindo no endpoint regional devolvido pelo login.

**O que este canal faz:** listar e importar dispositivos, ler capabilities e status, enviar
comandos de DP e receber eventos por MQTT.

**O que ele NÃO faz:** criar senha temporária em fechadura. Ver §11.6 — é uma fronteira
comercial da Tuya, não uma limitação do nosso código. Não gaste tempo procurando contorno.

---

### 11.1 Arquitetura em três camadas

| Camada | Classe | Responsabilidade |
|---|---|---|
| 1 — Login QR | `TuyaQrAuthService` | gera o QR e faz polling; sem assinatura |
| 2 — CustomerApi | `TuyaCustomerApiClient` | AES-128-GCM, `X-sign`, refresh de token |
| 3 — Domínio | `TuyaIntegrationService` | homes, devices, specifications, comandos DP |
| Push | `TuyaMqttService` + `tuya:subscribe` | eventos de status e online/offline |

**Toda chamada autenticada passa pelo `TuyaCustomerApiClient`.** Ele lança
`App\Exceptions\TuyaApiException` em falha e devolve o `result` já decifrado — inclusive quando
é escalar (`true`), que é sucesso e não pode ser confundido com erro.

---

### 11.2 Login QR

```
POST {BASE_URL}/v1.0/m/life/home-assistant/qrcode/tokens
     ?clientid=HA_3y9q4ak7g4ephrvke&usercode={user_code}&schema=haauthorize

GET  {BASE_URL}/v1.0/m/life/home-assistant/qrcode/tokens/{token}
     ?clientid=HA_3y9q4ak7g4ephrvke&usercode={user_code}
```

Constantes — **não alterar**:

```php
CLIENT_ID = 'HA_3y9q4ak7g4ephrvke'
SCHEMA    = 'haauthorize'   // parâmetro da requisição
QR_SCHEMA = 'tuyaSmart'     // prefixo do CONTEÚDO do QR: tuyaSmart--qrLogin?token=...
BASE_URL  = 'https://apigw.iotbing.com'
```

`SCHEMA` e `QR_SCHEMA` são coisas diferentes e já foram confundidas uma vez. O primeiro vai na
query string; o segundo compõe o texto que o app SmartLife escaneia.

A resposta traz `endpoint` (servidor regional, ex.: `https://apigw.tuyaus.com`), `terminal_id`
e `t` (relógio do servidor, em ms). **O `endpoint` regional deve ser usado em todas as chamadas
autenticadas**; o `apigw.iotbing.com` é só fallback.

---

### 11.3 CustomerApi — protocolo

Port de `tuya_sharing/customerapi.py`. Não é HMAC simples. Por requisição:

1. `rid` = UUID v4
2. `hash_key = MD5(rid + refresh_token)`
3. `secret = HMAC-SHA256(key=rid, msg=hash_key).hex()[:16]`
4. Params e body cifrados em **AES-128-GCM**; nonce de 12 chars do alfabeto
   `ABCDEFGHJKMNPQRSTWXYZabcdefhijkmnprstwxyz2345678`
5. Envio: `base64(nonce) + base64(ciphertext+tag)`
6. Headers `X-appKey`, `X-requestId`, `X-sid` (vazio), `X-time`, `X-token`
7. `X-sign = HMAC-SHA256(key=hash_key, msg="k=v||k=v..." + encdata)`, pulando headers vazios
8. O `result` volta cifrado — decifrar com o mesmo `secret`

#### Refresh de token — a causa do `sign invalid`

O `hash_key` deriva do **refresh token**. Um refresh token velho produz `sign invalid`, não
"token expirado" — o que torna o sintoma enganoso.

O client renova sozinho quando falta menos de 1 minuto para expirar:

```
GET {endpoint}/v1.0/m/token/{refresh_token}
→ { accessToken, refreshToken, uid, expireTime }
```

**Os dois tokens rotacionam** e são persistidos em `integrations`. Sem isso a integração
funciona por ~2h após o QR e depois quebra.

---

### 11.4 Endpoints usados

```
GET  /v1.0/m/life/users/homes                    lista homes (campo ownerId)
GET  /v1.0/m/life/ha/home/devices?encdata=...     devices do home (params cifrados)
GET  /v1.0/m/life/ha/devices/detail?encdata=...   detalhe + status atual
GET  /v1.1/m/life/{devId}/specifications          functions (graváveis) e status
GET  /v1.0/m/life/devices/{devId}/status          mapa dpId → statusCode
POST /v1.1/m/thing/{devId}/commands               envia DP (body cifrado)
POST /v1.0/m/life/ha/access/config                credenciais do broker MQTT
GET  /v1.0/m/token/{refreshToken}                 refresh
```

---

### 11.5 MQTT — canal de push

`POST /v1.0/m/life/ha/access/config` com `{"linkId": "..."}` devolve `url`, `clientId`,
`username`, `password`, `expireTime` (~7200s) e os templates de tópico.

```
url                 ssl://m1.tuyaus.com:8883
topic.ownerId.sub   cloud/group/{ownerId}/in
topic.devId.sub     cloud/device/{devId}/in/{hash}     (+ sufixo /pen ou /sta)
```

**`{ownerId}` é o `ownerId` do home, não o `uid` do usuário.** Trocar um pelo outro gera um
tópico válido que nunca recebe mensagem.

As mensagens chegam em **JSON puro, sem criptografia**. `protocol: 4` traz `data.devId` +
`data.status`; `protocol: 20` traz `data.bizCode` (`online`, `offline`, `nameUpdate`, …) com
`data.bizData.devId`.

As credenciais expiram em ~2h: o comando encerra e o supervisord reinicia com credenciais novas.

---

### 11.6 Senha temporária em fechadura — por que NÃO é possível

Esta seção existe para evitar que a investigação seja refeita. Conclusão apurada contra o
hardware real (Intelbras IFR 1001 e MFR 1001, ambas Zigbee).

**A Tuya vende credencial de fechadura como produto à parte.** Ela não trafega pelo canal de
controle de dispositivo: existe um *Smart Lock SDK* no app (licenciado a parceiros OEM) e um
*Smart Lock Open Service* na nuvem (assinatura à parte). Nenhum dos dois está incluído no canal
de device-sharing que usamos — nem no que o Home Assistant usa.

Evidência direta: ao cadastrar uma senha temporária pelo app da Tuya, **nenhum DP muda**, nem
durante o estado "Creating" nem depois do "effective". A senha vai por um caminho paralelo.

Corroborações:

- `home-assistant/core` **não tem `lock.py`** no componente Tuya — a integração oficial, que usa
  este mesmo SDK, não expõe fechadura alguma.
- 15 rotas plausíveis de `door-lock/*` foram testadas neste canal: todas devolvem
  `[1108] uri path invalid`. A requisição autentica e assina corretamente; a rota não existe.
- A Trial Edition da Tuya proíbe uso comercial e limita a 10 dispositivos controláveis; o plano
  pago não publica preço.

**DPs reais das fechaduras testadas:**

| Fechadura | Categoria | DPs graváveis |
|---|---|---|
| Intelbras IFR 1001 | `jtmspro` | 54 `unlock_method_create`, 55 `unlock_method_delete`, 48 `remote_no_pd_setkey`, 49 `remote_no_dp_key`, 57 `lock_motor_state`, 19 `key_tone` |
| Intelbras MFR 1001 | `ms` | **nenhum** — o produto não expõe modelo de DP neste canal |

O DP 54 é o de *cadastro de método de desbloqueio*: 9 bytes com tipo, estágio, flag de admin,
member ID e hardware ID — **não carrega senha nem validade**. O DP 24
(`temporary_password_creat`, payload de 21 bytes) é do conjunto de fechadura Zigbee residencial
antiga e **não existe** nestes modelos.

**Decisão:** fechadura sai do escopo da Tuya. Para PIN temporário a plataforma escolhida é o
**TTLock**, cuja API é aberta, gratuita e permite senha própria de 4–9 dígitos com janela e
revogação remota. A integração Tuya segue válida para sensores, interruptores, portões e hubs.

O código de senha temporária via DP 24 permanece no `TuyaIntegrationService` porque **funciona
em fechaduras que expõem esse DP** — existem modelos Tuya que expõem. O envio é protegido por
`Device::supportsTuyaTemporaryPassword()`, que exige o DP declarado em `/specifications`: em
fechadura sem o DP o sistema **recusa**, em vez de fingir sucesso e deixar um PIN fantasma.

---

### 11.7 Colunas no banco

`integrations`: `tuya_user_code`, `tuya_access_token`, `tuya_refresh_token`,
`tuya_token_expires_at`, `tuya_uid`, `tuya_endpoint`, `tuya_terminal_id`.

`devices`: `integration_id`, `tuya_category`, `tuya_product_id`, `tuya_product_name`,
`tuya_icon`, `tuya_online`, `tuya_status_payload`, `tuya_functions`.

`access_code_device_syncs`: rastreia o que cada dispositivo realmente recebeu
(`external_reference`, `synced_pin`, `status`).

O campo de identificação externa do device é **`external_device_id`**. Não existem
`external_id`, `type`, `status`, `category` nem `online` no model `Device`.

Enums: `DeviceBrandEnum` (`portatec`, `tuya`); `DeviceTypeEnum` (`switch`, `sensor`, `button` —
não há `Lock`); `DeviceStatusEnum` (`open`, `closed`, `on`, `off`).

---

### 11.8 SDK de referência

Quando houver dúvida de protocolo, a resposta está no fonte, não na documentação:

```bash
curl -sL -o /tmp/tuya-sdk.tar.gz \
  https://github.com/tuya/tuya-device-sharing-sdk/archive/refs/heads/main.tar.gz
tar xzf /tmp/tuya-sdk.tar.gz -C /tmp
# /tmp/tuya-device-sharing-sdk-main/tuya_sharing/{user,customerapi,device,home,mq,manager}.py
```

| Arquivo no SDK | Equivalente em PHP |
|---|---|
| `user.py` `LoginControl` | `TuyaQrAuthService` |
| `customerapi.py` `CustomerApi.__request` | `TuyaCustomerApiClient::request` |
| `device.py` `DeviceRepository` | `TuyaIntegrationService` |
| `home.py` `HomeRepository` | `TuyaIntegrationService::listDevices` |
| `mq.py` + `manager.py:on_message` | `TuyaMqttService` |
