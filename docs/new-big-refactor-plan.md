# Portatec — Documento de Arquitetura e Refatoração

> **Status:** Rascunho · Versão 0.2
> **Objetivo:** Servir como documento vivo para guiar a refatoração do sistema. Deve ser atualizado à medida que decisões forem sendo tomadas.

---

## 1. O que é o Portatec

Sistema de gerenciamento de controle de acesso (portões, fechaduras) voltado para condomínios e anfitriões de hospedagem por temporada (Airbnb, etc.).

**Escopo atual:** pequeno — aproximadamente 2 condomínios, até ~20 usuários. Não é SaaS público por enquanto.

**Atores principais:**

- **Admin (Portatec):** gerencia clientes, plataformas e dispositivos — acesso via painel interno simplificado
- **Cliente / Host:** cadastra seus locais (Places), dispositivos, bookings e códigos de acesso
- **Colaborador:** faxineira, zelador — recebe um PIN com janela de acesso entre estadias, cadastrado pelo host
- **Hóspede:** recebe um PIN com validade pela duração da estadia (sem conta no sistema, sem notificação automática por enquanto)
- **Dispositivo Portatec (ESP8266):** fica no local, recebe comandos remotos, valida PINs localmente via WiFi aberto
- **Dispositivo Tuya:** fechaduras e relés gerenciados via API Tuya

---

## 2. Situação atual

### Stack

| Camada | Tecnologia atual |
|---|---|
| Backend | Laravel 11 |
| Admin UI | FilamentPHP (painel admin) |
| App UI | FilamentPHP (painel do cliente) |
| Banco de dados | SQLite |
| Comunicação dispositivo | WebSocket (Laravel Broadcasting + Reverb/Pusher) |
| Integração externa | iCal (Airbnb) + Tuya API |
| Jobs | Laravel Queues + Horizon |

### Modelos existentes (manter com ajustes)

```
User
Place              → tem vários Devices, AccessCodes, Bookings, Integrations
PlaceUser          → pivot com role (admin, host)
Device             → brand (portatec | tuya), external_device_id, last_sync, default_pin
DeviceFunction     → tipo (switch, sensor, button), pin, status
PlaceDeviceFunction→ pivot Device ↔ Place
AccessCode         → pin, start, end, place_id, user_id?, booking_id?
Booking            → check_in, check_out, guest_name, integration_id, source (manual | ical)
Integration        → platform_id, user_id (ex: conexão com Airbnb)
Platform           → airbnb, booking.com, etc.
AccessEvent        → log de cada tentativa de acesso
CommandLog         → log de comandos enviados ao dispositivo
```

### Problemas identificados

- **FilamentPHP** dificulta regras de negócio customizadas — será descartado do painel do cliente
- **WebSocket** tem gerado instabilidade e não há confirmação (ACK) confiável de execução dos comandos

---

## 3. Decisões tomadas

| # | Questão | Decisão |
|---|---|---|
| 1 | Frontend | **Livewire** (Blade + Livewire + Alpine) |
| 2 | Comunicação dispositivo | **MQTT** (retomar broker em container) |
| 3 | Isolamento de dados | **Filtros por user_id/place_id nas queries** — sem multi-tenancy |
| 4 | Notificação ao hóspede | **Não implementar por enquanto** |
| 5 | Billing/planos | **Não implementar por enquanto** |
| 6 | FilamentPHP | **Descartar** do painel do cliente; manter somente painel admin interno simplificado |
| 7 | ESP32 | Fora do escopo deste repositório |
| 8 | App mobile | **Não** — web responsiva que funcione no navegador do celular |
| 9 | Banco de dados | **SQLite** — escala atual não exige PostgreSQL |

---

## 4. Regras de acesso e isolamento de dados

Não há multi-tenancy com bancos separados. O controle é feito por regras de negócio nas queries, usando os relacionamentos existentes.

### Princípio geral

O usuário autenticado só enxerga e opera dados que lhe pertencem ou foram explicitamente compartilhados com ele.

### Casos de compartilhamento suportados

- **Um cliente compartilha um Place com outro usuário** via `PlaceUser` (roles: `admin`, `host`)
- **Um Device de um Place pode ser vinculado a outro Place** via `PlaceDeviceFunction` — o dispositivo físico é o mesmo, mas aparece em dois locais com funções potencialmente diferentes

### Regras por recurso

| Recurso | Regra de acesso |
|---|---|
| Place | Usuário deve ter registro em `place_users` para o place |
| Device | Acesso via `place_users` do place ao qual o device pertence |
| AccessCode | Pertence a um Place — acesso via place |
| Booking | Pertence a um Place — acesso via place |
| Integration | Pertence diretamente ao `user_id` do usuário |
| CommandLog | Filtrado pelo `user_id` que emitiu o comando |

### Implementação

Usar **Policy classes** (já existem no projeto) combinadas com **query scopes** nos controllers. Não utilizar Global Scopes automáticos para evitar comportamentos invisíveis e difíceis de depurar.

Padrão a seguir nos controllers:

```php
// Sempre partir do usuário autenticado para chegar aos dados
$places = auth()->user()
    ->placeUsers()
    ->with('place')
    ->get()
    ->pluck('place');

// Nunca buscar direto sem filtro
// ❌ Place::all()
// ✅ Place::whereHas('placeUsers', fn($q) => $q->where('user_id', auth()->id()))
```

---

## 5. Regras de negócio — AccessCode e Bookings

### Bookings

- Bookings podem ser criados de duas formas:
  - **Automática:** importação via iCal (Airbnb e similares)
  - **Manual:** o cliente cria diretamente no sistema, informando datas e nome do hóspede
- O campo `source` distingue a origem: `manual | ical`
- Bookings com soft delete mantêm o histórico com `deletion_reason`

### AccessCode

- Todo Booking gera automaticamente um AccessCode ao ser criado (via Observer ou Service)
- O AccessCode pode ser **editado manualmente** pelo cliente após criado (ex: ajustar datas, trocar o PIN)
- Um AccessCode pode existir **sem Booking** (ex: PIN para colaborador com janela fixa)
- Um AccessCode pode estar vinculado a um `user_id` (colaborador com conta) ou ser avulso (sem usuário, só pin + datas)
- O PIN deve ser **único por dispositivo no período de vigência** — validar na geração e na edição
- AccessCodes expirados não são deletados — ficam no histórico

### Dispositivos Portatec — PIN padrão

Todo dispositivo Portatec (ESP8266) possui um **PIN padrão de acesso** (`default_pin`), cadastrado no momento do registro do dispositivo. Esse PIN:

- Funciona como acesso de emergência / manutenção
- **Nunca expira**
- Deve ser sincronizado para o dispositivo junto com os demais AccessCodes
- Não deve aparecer para hóspedes — apenas para o admin e o host do lugar

> **Decisão:** o `default_pin` é armazenado como um AccessCode permanente no banco, com o campo `is_default_pin = true` e sem data de expiração (`end = null`). Entra no mesmo fluxo de sync dos demais PINs, sem lógica especial no firmware.

---

## 6. Frontend — Blade + Livewire

### Stack de frontend

- **Laravel Breeze** como ponto de partida (autenticação + scaffolding básico)
  - Breeze tem integração nativa com Livewire: `php artisan breeze:install livewire`
  - Fornece login, registro, reset de senha e verificação de e-mail prontos
- **Livewire v3** para componentes interativos (controle de portão, listas reativas)
- **Alpine.js** para micro-interações no frontend (dropdowns, toggles, feedback visual)
- **Tailwind CSS** para estilização

### Painel do cliente (app)

Substituir completamente o FilamentPHP. Telas planejadas:

- Dashboard — visão geral dos Places com status dos dispositivos
- Controle do portão — botão de abrir/fechar com feedback em tempo real
- Bookings — lista, criação manual, visualização de AccessCodes vinculados
- AccessCodes — lista de PINs ativos, criação de PIN para colaborador, edição
- Dispositivos — lista e status
- Integrações — gerenciar conexões com Airbnb/iCal

### Painel admin (interno Portatec)

Manter FilamentPHP **somente** para o painel admin interno, com escopo reduzido:

- CRUD de usuários/clientes (incluindo reset de senha)
- Visualização de dispositivos e status de comunicação
- Visualização de logs (CommandLog, AccessEvent)
- Nenhuma regra de negócio complexa — só operações de suporte e manutenção

---

## 7. Comunicação com dispositivos — MQTT

### Por que voltar ao MQTT

O WebSocket tem gerado instabilidade. O MQTT é o protocolo padrão para IoT:

- **Leve:** adequado ao ESP8266 (memória limitada)
- **QoS garantido:** o broker garante entrega das mensagens (QoS 0, 1 ou 2)
- **Bidirecional por design:** o dispositivo publica e subscreve tópicos — ideal para ACK de comandos
- **Reconexão automática:** broker e bibliotecas do ESP8266 lidam com reconexão nativamente
- **Tempo real:** latência tipicamente < 100ms — adequado para "abrir portão agora"

### Fluxo de comando em tempo real

```
Cliente (browser) → clica "Abrir"
  → Laravel recebe requisição HTTP
  → Laravel publica mensagem MQTT: device/{chip_id}/command
  → Broker entrega ao ESP8266 em <100ms
  → ESP8266 executa (abre o portão)
  → ESP8266 publica ACK: device/{chip_id}/ack
  → Laravel recebe ACK via subscriber MQTT
  → Laravel dispara Broadcasting event (Reverb) ao frontend
  → Browser recebe confirmação e atualiza UI
```

O broker MQTT é responsável pela entrega confiável ao dispositivo. O Laravel Reverb continua sendo usado apenas para comunicação servidor → browser (o browser não precisa falar MQTT diretamente).

### Broker

O projeto já teve um broker MQTT em container Docker. Retomar com **Mosquitto** (simples, leve) ou **EMQX** (tem dashboard web para visualizar conexões e mensagens).

```yaml
# docker-compose.yml (exemplo com Mosquitto)
services:
  mqtt:
    image: eclipse-mosquitto:2
    ports:
      - "1883:1883"
      - "9001:9001"   # WebSocket port (para debug via browser)
    volumes:
      - ./mosquitto/config:/mosquitto/config
      - ./mosquitto/data:/mosquitto/data
```

### Tópicos MQTT

| Tópico | Direção | Descrição |
|---|---|---|
| `device/{chip_id}/command` | Servidor → Dispositivo | Comandos (open, close, toggle) |
| `device/{chip_id}/ack` | Dispositivo → Servidor | Confirmação de execução |
| `device/{chip_id}/access-codes/sync` | Servidor → Dispositivo | Sincronização de PINs |
| `device/{chip_id}/access-codes/ack` | Dispositivo → Servidor | Confirmação de sync |
| `device/{chip_id}/status` | Dispositivo → Servidor | Status de sensores |
| `device/{chip_id}/pulse` | Dispositivo → Servidor | Heartbeat / keep-alive |
| `device/{chip_id}/event` | Dispositivo → Servidor | Evento de acesso (tentativa de PIN) |

### Payload dos comandos

**Servidor → Dispositivo**

```json
// Comando de controle
{
  "command_id": "uuid",
  "action": "open" | "close" | "toggle",
  "pin": 2,
  "timestamp": "ISO8601"
}

// Sincronização de PINs
{
  "command_id": "uuid",
  "action": "sync_access_codes",
  "access_codes": [
    { "pin": "1234", "start": "ISO8601", "end": "ISO8601" }
  ]
}

// Heartbeat (servidor verifica se dispositivo está vivo)
{
  "action": "ping",
  "timestamp": "ISO8601"
}
```

**Dispositivo → Servidor**

```json
// ACK de comando
{
  "command_id": "uuid",
  "status": "ok" | "error",
  "message": "...",
  "timestamp_device": 123456
}

// Evento de tentativa de acesso via PIN
{
  "event": "access_attempt",
  "pin": "1234",
  "result": "granted" | "denied",
  "timestamp_device": 123456
}

// Heartbeat
{
  "event": "pulse",
  "millis": 123456,
  "sensors": { "pin": 2, "status": "closed" }
}
```

### Integração no Laravel

Usar o pacote `php-mqtt/laravel-client` para publicar e subscrever mensagens MQTT.

A subscrição dos tópicos (para receber ACKs, pulses e eventos) deve rodar como um **processo de longa duração** — via artisan command registrado no Supervisor, separado dos workers de queue.

---

## 8. Arquitetura proposta (pós-refatoração)

```
┌─────────────────────────────────────────────────────────┐
│                     Portatec Server                      │
│                                                          │
│  ┌───────────────┐   ┌──────────────────────────────┐   │
│  │  Laravel App  │   │  Blade + Livewire (UI)        │   │
│  │               │   │  - Dashboard                  │   │
│  │  - Auth       │   │  - Controle de portão         │   │
│  │  - AccessCode │   │  - Bookings / AccessCodes     │   │
│  │  - Devices    │   │  - Dispositivos               │   │
│  │  - Bookings   │   │  - Integrações                │   │
│  │  - iCal sync  │   └──────────────────────────────┘   │
│  │  - Tuya API   │                                       │
│  └──────┬────────┘                                       │
│         │                                                 │
│   ┌─────▼──────┐    ┌──────────────┐                    │
│   │   Reverb   │    │ MQTT Broker  │◄── ESP8266 / Tuya  │
│   │ (browser ← │    │ (Mosquitto / │                     │
│   │  feedback) │    │  EMQX)       │                     │
│   └────────────┘    └──────────────┘                    │
│                                                          │
│   ┌────────────┐    ┌──────────────┐                    │
│   │   SQLite   │    │  Redis        │                    │
│   │  (dados)   │    │  (queue/cache)│                    │
│   └────────────┘    └──────────────┘                    │
└─────────────────────────────────────────────────────────┘
```

---

## 9. Plano de refatoração em fases

### Fase 1 — Fundação

- [ ] Instalar Laravel Breeze com Livewire: `php artisan breeze:install livewire`
- [ ] Criar rota e layout base do painel do cliente (separado do FilamentPHP)
- [ ] Implementar padrão de queries com filtro por `user_id` / `place_id` em todos os controllers
- [ ] Dashboard inicial: lista de Places e status dos dispositivos

### Fase 2 — Bookings e AccessCodes

- [ ] Tela de listagem de Bookings por Place
- [ ] Criação manual de Booking + geração automática de AccessCode
- [ ] Edição de AccessCode (ajuste de datas e PIN)
- [ ] `AccessCodeGeneratorService` — geração de PIN único com validação de conflito de período
- [ ] Listar PINs ativos por Place (hóspedes e colaboradores)
- [ ] Definir e implementar tratamento do `default_pin` do dispositivo Portatec

### Fase 3 — Controle de dispositivos em tempo real

- [ ] Subir broker MQTT em container (Mosquitto ou EMQX)
- [ ] Integrar `php-mqtt/laravel-client` no Laravel
- [ ] `DeviceCommandService` — publica comando MQTT e aguarda ACK
- [ ] Tela de controle do portão (Livewire) com feedback em tempo real via Reverb
- [ ] Sincronização de AccessCodes via MQTT ao criar/editar/expirar
- [ ] Dashboard de saúde dos dispositivos: `last_seen`, uptime, firmware version

### Fase 4 — Integração com plataformas

- [ ] Refatorar `ICalSyncService` — melhorar parsing e tratamento de erros
- [ ] UI para gerenciar integrações e ver último status de sync
- [ ] Geração automática de AccessCode ao importar Booking via iCal

### Fase 5 — Painel admin interno (Portatec)

- [ ] Avaliar se FilamentPHP atende com escopo reduzido (CRUD simples) ou reescrever com Livewire
- [ ] Cadastro de clientes / reset de senhas
- [ ] Visualização de dispositivos, logs de comando e eventos de acesso

---

## 10. Estrutura de pastas proposta (Laravel)

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/              # API REST para dispositivos
│   │   └── Web/              # Controllers das páginas Blade
│   └── Middleware/
├── Livewire/
│   ├── Dashboard/
│   ├── Places/
│   ├── Devices/
│   ├── AccessCodes/
│   └── Bookings/
├── Services/
│   ├── AccessCode/
│   │   ├── AccessCodeGeneratorService.php
│   │   └── AccessCodeSyncService.php     # já existe, manter
│   ├── Device/
│   │   └── DeviceCommandService.php      # abstrai publicação MQTT
│   ├── Booking/
│   │   └── BookingImportService.php
│   └── Tuya/                             # já existe
├── Models/                               # manter os existentes com ajustes
├── Events/                               # manter, refinar
├── Jobs/                                 # manter
└── Policies/                             # manter, expandir
```

---

## 11. Questões resolvidas

| # | Questão | Decisão |
|---|---|---|
| 1 | `default_pin` — como armazenar? | AccessCode com `is_default_pin = true` e `end = null`. Mesmo fluxo de sync dos demais PINs. |
| 2 | Autenticação do ESP8266 no broker MQTT | Usuário/senha por dispositivo (configurado no firmware) |
| 3 | Broker MQTT | **Mosquitto** — simples, leve, container Docker |
| 4 | Hóspede recebe o PIN como? | Fora do sistema por enquanto (WhatsApp, e-mail manual pelo host) |
| 5 | FilamentPHP no painel admin | Manter com escopo reduzido (CRUD de suporte apenas) |

---

## 12. Notas de implementação — a refinar

### Formulários com relacionamentos no Livewire

Formulários que envolvem relacionamentos (ex: criar um Place e já associar usuários, criar um Device e vincular a um Place com suas funções) são um ponto de atenção. O Livewire resolve bem, mas exige um pouco mais de cuidado do que o FilamentPHP fazia automaticamente.

Abordagens a considerar quando chegar nessa parte:

- **Formulários em múltiplos steps (wizard):** criar o recurso principal primeiro, depois adicionar os relacionamentos em etapas separadas. Reduz a complexidade de um único componente Livewire grande.
- **Componentes Livewire aninhados:** separar a lógica do formulário principal dos sub-formulários de relacionamento em componentes menores e reutilizáveis.
- **`wire:model` com arrays:** Livewire suporta binding de arrays para coleções de relacionamentos (ex: lista de funções de um dispositivo), o que permite adicionar/remover itens dinamicamente antes de salvar.

> Este ponto deve ser refinado quando cada formulário for implementado. Não bloqueia o início da refatoração.

---

## 13. Referências e dependências

- [Laravel Breeze + Livewire](https://laravel.com/docs/starter-kits#breeze-and-livewire) — scaffolding de autenticação com Livewire
- [Livewire v3](https://livewire.laravel.com/) — componentes reativos server-side
- [Laravel Reverb](https://reverb.laravel.com/) — WebSocket server para feedback no browser
- [php-mqtt/laravel-client](https://github.com/php-mqtt/laravel-client) — cliente MQTT para Laravel
- [Eclipse Mosquitto](https://mosquitto.org/) — broker MQTT leve para Docker
- [EMQX](https://www.emqx.io/) — broker MQTT com dashboard web
- [Tuya IoT Platform](https://developer.tuya.com/) — integração com dispositivos Tuya

---

*v0.1 — criado em fevereiro/2026*
*v0.2 — decisões de frontend (Livewire), comunicação (MQTT), isolamento de dados, scope do projeto, regras de AccessCode/Booking e default_pin atualizadas*
*v0.3 — todas as questões em aberto resolvidas; default_pin definido como AccessCode com is_default_pin; nota sobre formulários Livewire com relacionamentos adicionada*
