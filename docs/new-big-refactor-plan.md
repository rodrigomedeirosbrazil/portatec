# Portatec — Documento de Arquitetura e Refatoração

> **Status:** Rascunho inicial · Versão 0.1
> **Objetivo:** Servir como documento vivo para guiar a refatoração do sistema. Deve ser atualizado à medida que decisões forem sendo tomadas.

---

## 1. O que é o Portatec

Sistema SaaS de gerenciamento de controle de acesso (portões, fechaduras) voltado para anfitriões de hospedagem por temporada (Airbnb, etc.).

**Atores principais:**
- **Admin (Portatec):** gerencia clientes, plataformas e dispositivos globais
- **Cliente / Host:** cadastra seus locais (Places), dispositivos e integra com plataformas de reserva
- **Hóspede:** recebe um PIN com validade pela duração da estadia
- **Colaborador:** faxineira, zelador — recebe um PIN com janela de acesso entre estadias
- **Dispositivo ESP8266 (Portatec):** fica no local, recebe comandos e valida PINs localmente
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
Device             → brand (portatec | tuya), external_device_id, last_sync
DeviceFunction     → tipo (switch, sensor, button), pin, status
PlaceDeviceFunction→ pivot Device ↔ Place
AccessCode         → pin, start, end, place_id, user_id?, booking_id?
Booking            → check_in, check_out, guest_name, integration_id
Integration        → platform_id, user_id (ex: conexão com Airbnb)
Platform           → airbnb, booking.com, etc.
AccessEvent        → log de cada tentativa de acesso
CommandLog         → log de comandos enviados ao dispositivo
```

### Problemas identificados
- **FilamentPHP** dificulta regras de negócio customizadas (visibilidade de dados por tenant, lógica de criação de PINs, etc.)
- **SQLite** não está pronto para produção multi-tenant com concorrência
- **WebSocket sem confirmação confiável:** não há garantia de que o dispositivo recebeu o comando
- Sem separação clara de tenant: qualquer usuário pode potencialmente ver dados de outro

---

## 3. Decisões de arquitetura a tomar

### 3.1 Frontend — Blade/Livewire vs Next.js

| Critério | Blade + Livewire | Next.js |
|---|---|---|
| Velocidade de desenvolvimento | ✅ Muito rápido | ⚠️ Mais setup |
| Time de manutenção Laravel | ✅ Mesma linguagem | ❌ Exige JS/React |
| UX em tempo real (status do portão) | ⚠️ Livewire + Alpine | ✅ React facilita |
| SEO | Neutro (app autenticado) | Neutro |
| Deploy | ✅ Mesmo servidor | ⚠️ Separado ou Vercel |
| Controle fino de UI/UX | ⚠️ Limitado | ✅ Total |

**Recomendação inicial:** Começar com **Blade + Livewire**. O ganho de controle vs FilamentPHP já é enorme, sem precisar de duas aplicações. Se o produto crescer e precisar de um app mobile ou UX muito interativa, migrar a SPA para Next.js depois. A API REST já estará pronta para isso.

**Decisão:** `[ ] Blade+Livewire` `[ ] Next.js` `[ ] Outro`

---

### 3.2 Banco de dados — SQLite → PostgreSQL

SQLite não suporta concorrência real nem múltiplos workers. Para produção, migrar para **PostgreSQL**.

- Mantém a mesma ORM (Eloquent), sem mudança de código
- Habilita row-level locking para geração de PINs únicos
- Suporte a `jsonb` para metadata de eventos

**Decisão:** `[x] PostgreSQL` (migração obrigatória antes de produção)

---

### 3.3 Multi-tenancy

Hoje não há isolamento de dados por cliente. Precisamos de uma estratégia.

**Opção A — Scoping manual:** Cada query filtra por `user_id` ou `place_id` do usuário autenticado. Simples, mas fácil de esquecer.

**Opção B — Pacote `stancl/tenancy`:** Multi-tenant com banco separado por cliente. Mais seguro, mais complexo.

**Opção C — Global Scopes no Eloquent:** Scopes aplicados automaticamente em todos os modelos baseados no usuário logado. Meio-termo entre A e B.

**Recomendação:** Opção C — Global Scopes + Policies (já existem no código) para garantir isolamento sem a complexidade de bancos separados.

**Decisão:** `[ ] A` `[ ] B` `[ ] C`

---

### 3.4 Comunicação com os dispositivos

#### Situação atual
O dispositivo ESP8266 conecta via WebSocket ao servidor Laravel (Reverb/Pusher). O servidor envia eventos e o dispositivo reage. Não há confirmação (ACK) confiável de que o comando foi executado.

#### Fluxos de comunicação
Existem dois fluxos distintos que precisam de soluções diferentes:

**Fluxo 1 — Sincronização de AccessCodes (não urgente)**
> Quando um PIN é criado/atualizado/expirado, sincronizar com o dispositivo.

Este fluxo **não precisa ser em tempo real imediato**. O dispositivo pode fazer polling ou receber push. O importante é que fique consistente.

Opções:
- **HTTP Polling pelo dispositivo:** A cada N minutos o ESP8266 faz GET /api/device/{id}/access-codes. Simples, funciona com ESP8266 limitado.
- **WebSocket push (atual):** Mais rápido, mais complexo.
- **MQTT:** Protocolo feito para IoT, leve, com QoS garantido. Requer um broker (ex: EMQX, Mosquitto, HiveMQ Cloud grátis).

**Fluxo 2 — Comando em tempo real (abrir/fechar portão)**
> O usuário clica "Abrir" no dashboard e quer feedback imediato.

Este fluxo **precisa de confirmação**. O usuário precisa saber se o portão abriu ou não.

Opções:
- **WebSocket bidirecional (melhorado):** Manter WebSocket, mas implementar ACK: ao receber o comando, o dispositivo envia de volta `{command_id, status: "executed"}`. O servidor confirma ao frontend via Broadcasting.
- **MQTT com QoS 1/2:** O broker garante a entrega. O dispositivo publica o resultado num tópico de resposta.

#### Recomendação

Adotar **MQTT** para ambos os fluxos:
- É o protocolo padrão para IoT (muito documentado para ESP8266/ESP32)
- Suporta QoS (Quality of Service) → garante entrega
- Broker free tier disponível (HiveMQ Cloud, EMQX Cloud)
- O Laravel publica via um cliente MQTT (ex: `php-mqtt/laravel-client`)
- O dispositivo subscreve tópicos por `chip_id`

**Alternativa mais simples no curto prazo:** Manter WebSocket, mas adicionar protocolo de ACK:
1. Servidor envia `{command_id, action, pin}` ao dispositivo
2. Dispositivo executa e responde `{command_id, status}`
3. Servidor recebe ACK e publica evento ao frontend

**Decisão:** `[ ] Manter WebSocket + ACK` `[ ] Migrar para MQTT` `[ ] Outro`

---

### 3.5 WiFi aberto no ESP8266

O dispositivo cria um WiFi aberto. Qualquer pessoa que conecta acessa uma interface local para controlar o portão via PIN.

**Riscos e melhorias a considerar:**
- PIN sem HTTPS na rede local — considerar se o ESP8266 pode servir HTTPS (difícil, limitação de hardware) ou se o risco é aceitável
- Implementar rate limiting local no firmware para tentativas de PIN errado
- Timeout de sessão local após inatividade

---

## 4. Arquitetura proposta (pós-refatoração)

```
┌─────────────────────────────────────────────────────┐
│                   Portatec Server                    │
│                                                      │
│  ┌──────────────┐  ┌──────────────────────────────┐ │
│  │  Laravel API  │  │  Blade + Livewire (UI)       │ │
│  │  (REST)       │  │  - Dashboard do cliente      │ │
│  │  - Auth       │  │  - Controle de portão        │ │
│  │  - AccessCode │  │  - Bookings / Calendário     │ │
│  │  - Devices    │  │  - Códigos de acesso         │ │
│  │  - Bookings   │  └──────────────────────────────┘ │
│  │  - Webhooks   │                                    │
│  └──────┬────────┘                                   │
│         │ Laravel Broadcasting (Reverb)               │
│         │ para feedback em tempo real no frontend     │
│  ┌──────▼────────┐                                   │
│  │  MQTT Broker  │ ◄─── ESP8266 / Tuya              │
│  │  (HiveMQ /   │                                    │
│  │   Mosquitto)  │                                   │
│  └──────────────┘                                    │
│                                                      │
│  ┌──────────────┐  ┌──────────────┐                 │
│  │  PostgreSQL  │  │  Redis        │                 │
│  │  (dados)     │  │  (queue/cache)│                 │
│  └──────────────┘  └──────────────┘                 │
└─────────────────────────────────────────────────────┘
```

---

## 5. Plano de refatoração em fases

### Fase 1 — Fundação (sem FilamentPHP na app do cliente)
- [ ] Criar sistema de autenticação próprio (Laravel Breeze ou do zero)
- [ ] Criar `PlaceScope` — middleware/global scope para multi-tenancy
- [ ] Migrar banco para PostgreSQL
- [ ] Criar primeira tela Livewire: Dashboard do cliente (lista de Places e status dos dispositivos)
- [ ] Criar tela de controle do portão em tempo real (Livewire + Broadcasting)

### Fase 2 — Regras de negócio de AccessCode
- [ ] Service `AccessCodeGeneratorService` — geração com unicidade e validação de janela
- [ ] Tela de visualização de PINs ativos por estadia
- [ ] Automatização: criação de PIN ao confirmar Booking
- [ ] Expiração automática e notificação ao hóspede (e-mail/SMS)

### Fase 3 — Integração com plataformas
- [ ] Refatorar `ICalSyncService` — melhorar parsing e tratamento de erros
- [ ] Webhook (futuro) para Airbnb direto ao invés de polling iCal
- [ ] UI para gerenciar integrações e ver status de sincronização

### Fase 4 — Comunicação confiável com dispositivos
- [ ] Definir protocolo de ACK no WebSocket atual (curto prazo)
- [ ] Avaliar/implementar MQTT
- [ ] Tópicos de comando: `device/{chip_id}/command`
- [ ] Tópicos de status: `device/{chip_id}/status`
- [ ] Tópicos de sync: `device/{chip_id}/access-codes/sync`
- [ ] Dashboard de saúde dos dispositivos (last_seen, uptime, firmware version)

### Fase 5 — Painel Admin (Portatec interno)
- [ ] Decidir: manter FilamentPHP só no admin interno (onde regras de negócio são simples) ou reescrever também
- [ ] Gestão de clientes, billing, suporte

---

## 6. Estrutura de pastas proposta (Laravel)

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/           # API REST para dispositivos e futuros clientes mobile
│   │   └── Web/           # Controllers das páginas Blade
│   └── Middleware/
│       └── ScopePlaceToUser.php
├── Livewire/
│   ├── Dashboard/
│   ├── Places/
│   ├── Devices/
│   ├── AccessCodes/
│   └── Bookings/
├── Services/
│   ├── AccessCode/
│   │   ├── AccessCodeGeneratorService.php
│   │   └── AccessCodeSyncService.php   # já existe, manter
│   ├── Device/
│   │   └── DeviceCommandService.php    # abstrai envio de comandos
│   ├── Booking/
│   │   └── BookingImportService.php
│   └── Tuya/                           # já existe
├── Models/                             # manter os existentes com ajustes
├── Events/                             # manter, refinar
├── Jobs/                               # manter
└── Policies/                           # manter, expandir
```

---

## 7. Protocolo de comunicação dispositivo — proposta

### Comandos servidor → dispositivo

```json
// Abrir/fechar portão
{
  "command_id": "uuid",
  "action": "open" | "close" | "toggle",
  "pin": 2,
  "timestamp": "ISO8601"
}

// Sincronizar PINs
{
  "command_id": "uuid",
  "action": "sync_access_codes",
  "access_codes": [
    { "pin": "1234", "start": "ISO8601", "end": "ISO8601" }
  ]
}

// Pulse/heartbeat (servidor → dispositivo, para verificar se está online)
{
  "action": "ping",
  "timestamp": "ISO8601"
}
```

### Respostas dispositivo → servidor

```json
// ACK de comando
{
  "command_id": "uuid",
  "status": "ok" | "error",
  "message": "...",
  "timestamp_device": 123456
}

// Evento de acesso (alguém usou um PIN)
{
  "event": "access_attempt",
  "pin": "1234",
  "result": "granted" | "denied",
  "timestamp_device": 123456
}

// Heartbeat / pulse
{
  "event": "pulse",
  "millis": 123456,
  "sensors": { "pin": 2, "status": "closed" }
}
```

---

## 8. Questões em aberto

| # | Questão | Prioridade |
|---|---|---|
| 1 | Frontend: Livewire ou Next.js? | Alta |
| 2 | Comunicação: melhorar WebSocket ou migrar para MQTT? | Alta |
| 3 | Multi-tenancy: Global Scopes ou tenancy package? | Alta |
| 4 | Como notificar o hóspede do PIN? (email, SMS, WhatsApp?) | Média |
| 5 | O sistema vai ter planos/billing? Se sim, quando? | Média |
| 6 | FilamentPHP: manter só no painel admin interno? | Baixa |
| 7 | Suporte a ESP32 além do ESP8266? | Baixa |
| 8 | App mobile no futuro? (afeta escolha de frontend agora) | Baixa |

---

## 9. Referências e dependências

- [Laravel Reverb](https://reverb.laravel.com/) — WebSocket server nativo do Laravel
- [php-mqtt/laravel-client](https://github.com/php-mqtt/laravel-client) — cliente MQTT para Laravel
- [HiveMQ Cloud](https://www.hivemq.com/mqtt-cloud-broker/) — broker MQTT grátis (até 100 conexões)
- [stancl/tenancy](https://tenancyforlaravel.com/) — multi-tenancy para Laravel
- [PubNub](https://www.pubnub.com/) — alternativa gerenciada para WebSocket/MQTT
- [Tuya IoT Platform](https://developer.tuya.com/) — integração com dispositivos Tuya

---

*Documento criado em: fevereiro/2026 — atualizar com cada decisão tomada.*
