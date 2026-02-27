# Portatec — Execução do Refactor por PR

> Baseado no plano em `docs/new-big-refactor-plan.md` (v0.6)
> Objetivo: transformar o plano em sequência executável de PRs pequenos, revisáveis e com baixo risco.

---

## Regras de execução

- Cada PR deve ser deployável e não quebrar o ambiente.
- Não misturar migração estrutural com refactor funcional grande no mesmo PR.
- Sempre incluir critérios de aceite e testes mínimos por PR.
- Fluxo do cliente: autorização por `place_users` + policies/scopes (sem Shield/Spatie).
- Painel admin `/admin` permanece com Filament.

---

## Checklist de progresso (status real)

> Última atualização: **2026-02-27**
> Fonte de verdade para continuidade em nova janela de contexto.

### PRs do plano

- [x] PR-01 — Fundação de dependências e painéis (**concluído com ressalvas**)
- [x] PR-02 — Reset de banco + migrations definitivas
- [x] PR-03 — Modelos, relações e policies por ownership
- [x] PR-04 — Base Livewire do cliente (Dashboard + Places)
- [x] PR-05 — Bookings e AccessCodes
- [x] PR-06 — MQTT: publicação, subscriber e operação (**sem telas Livewire de Devices**)
- [x] PR-07 — iCal + robustez de sync
- [x] PR-08 — Limpeza final do legado

### Pendências abertas (próximos passos)

- [x] Remover resíduos de Shield/Spatie ainda no repositório:
  - `config/filament-shield.php`
  - `config/permission.php`
  - `app/Filament/Resources/Roles/**`
- [x] Implementar autenticação do cliente desacoplada do Filament (Breeze/Livewire):
  - login/registro/reset próprios para `/app`
  - parar de depender de `/admin/login` como entrada do cliente
  - observação: implementado com controllers/views customizados; sem scaffolding Breeze
- [x] Remover impersonate do painel admin em Filament:
  - remover ação `Impersonate` do `UserResource`
  - remover dependências/configuração de `filament-impersonate`/`laravel-impersonate` do admin
  - observação: `composer.lock` regenerado em 2026-02-27 via Sail (`./vendor/bin/sail composer update`) removendo resíduos de `filament-shield`, `spatie/laravel-permission`, `filament-impersonate` e `laravel-impersonate`
- [x] Implementar telas Livewire de dispositivos:
  - `Devices\\Index`
  - `Devices\\Show`
  - `Devices\\Control`
  - observação: listagem/detalhes/controle básico por ações MQTT (`toggle`/`push_button`) com filtro por ownership via `place_users`
- [x] Adicionar visualização de `AccessEvent` no painel admin `/admin`
- [x] Fechar cobertura de testes mínimos do plano:
  - isolamento por `place_users`
  - booking -> access code
  - sync access code (mock transport)
  - importação iCal básica
  - geração de PIN
  - mapeamento payload MQTT
  - observação: cobertura adicionada em
    - `tests/Feature/PlaceUsersIsolationTest.php`
    - `tests/Feature/BookingAccessCodeFlowTest.php`
    - `tests/Unit/AccessCodeSyncServiceTest.php`
    - `tests/Unit/ICalSyncServiceTest.php`
    - `tests/Unit/AccessCodeGeneratorServiceTest.php`
    - `tests/Unit/DeviceCommandServicePayloadMappingTest.php`
- [x] Definir estratégia de impersonate para o app cliente (Livewire):
  - fluxo explícito de "entrar como cliente" fora do Filament
  - trilha de auditoria mínima (quem assumiu, quem foi assumido, quando iniciou/finalizou)
  - observação: implementado fluxo de início/fim por rotas dedicadas com registro em `impersonation_sessions` (`started_at`/`ended_at`, IP e user agent), ação explícita no `UserResource` e teste em `tests/Feature/ClientImpersonationFlowTest.php`
  - atualização 2026-02-27: endurecimento do fluxo para bloquear impersonação de `super_admin` e validar integridade da sessão no encerramento (session id + impersonator + impersonated), com cobertura adicional em `tests/Feature/ClientImpersonationFlowTest.php`
- [ ] Atualizar este checklist a cada entrega (marcar itens e registrar pendências novas)

---

## PR-01 — Fundação de dependências e painéis

### Objetivo

Separar oficialmente os escopos: cliente em Livewire, admin em Filament.

### Mudanças

- Remover `bezhansalleh/filament-shield` e `spatie/laravel-permission`.
- Ajustar `User` para remover `HasPanelShield`, `HasRoles`, `FilamentUser`.
- Limpar `AppServiceProvider` de boot/configuração do Shield.
- Manter `filament/filament` e painel admin.
- Garantir rotas iniciais para fluxo Breeze/Livewire do cliente.

### Arquivos-alvo

- `composer.json`
- `app/Models/User.php`
- `app/Providers/AppServiceProvider.php`
- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Providers/Filament/AppPanelProvider.php` (remover/descontinuar)
- `routes/web.php`

### Critérios de aceite

- App sobe sem Shield/Spatie.
- `/admin` continua acessível.
- Fluxo de login do cliente não depende de Filament.

---

## PR-02 — Reset de banco + migrations definitivas

### Objetivo

Recriar schema limpo conforme fase 1 do plano.

### Mudanças

- Remover migrations legadas.
- Criar migrations definitivas na ordem do plano.
- Confirmar campos:
  - `devices.default_pin` (sem criptografia).
  - `bookings.source`.
  - `place_users.label`.
  - `access_codes` sem `is_default_pin`.
- Criar seeder de desenvolvimento mínimo.

### Arquivos-alvo

- `database/migrations/*`
- `database/seeders/DatabaseSeeder.php`
- `database/seeders/UserSeeder.php` (ou novo seeder dedicado)

### Critérios de aceite

- `php artisan migrate:fresh --seed` executa sem erro.
- Schema final corresponde ao plano v0.6.

---

## PR-03 — Modelos, relações e policies por ownership

### Objetivo

Consolidar domínio e autorização do cliente por relacionamento (`place_users`).

### Mudanças

- Ajustar modelos e relações (`Place`, `PlaceUser`, `Device`, `DeviceFunction`, `PlaceDeviceFunction`, `Booking`, `AccessCode`, `Integration`, `CommandLog`, `AccessEvent`).
- Reescrever policies para checagem por vínculo (`place_users`) e ownership.
- Ajustar `AuthServiceProvider` com mapeamentos corretos.
- Definir fronteira explícita:
  - `CommandLog` para comandos enviados ao dispositivo.
  - `AccessEvent` para uso/tentativa de PIN vinda do dispositivo.

### Arquivos-alvo

- `app/Models/*.php`
- `app/Policies/*.php`
- `app/Providers/AuthServiceProvider.php`

### Critérios de aceite

- Usuário só acessa dados dos próprios places (ou compartilhados).
- Sem dependência de permission keys do Shield.

---

## PR-04 — Base Livewire do cliente (Dashboard + Places)

### Objetivo

Entregar navegação inicial do cliente em Livewire.

### Mudanças

- Instalar/configurar Breeze Livewire.
- Criar layout base do cliente.
- Implementar:
  - `Dashboard`
  - `Places\Index`
  - `Places\Show`
  - `Places\Create`
  - `Places\Edit`
- Auto vínculo do criador no `PlaceUser` como `admin`.

### Arquivos-alvo

- `routes/web.php`
- `app/Livewire/**`
- `resources/views/**`
- `app/Models/Place.php`
- `app/Models/PlaceUser.php`

### Critérios de aceite

- Cliente logado vê apenas seus places.
- CRUD básico de place funciona.

---

## PR-05 — Bookings e AccessCodes (regra atual simplificada)

### Objetivo

Implementar fluxo completo de booking manual + AccessCode.

### Mudanças

- Criar `AccessCodeGeneratorService`.
- Manter regra simplificada: sem validação de sobreposição temporal nesta fase.
- Implementar Livewire:
  - `Bookings\Index`
  - `Bookings\Create`
  - `Bookings\Show`
  - `AccessCodes\Index`
  - `AccessCodes\Create`
  - `AccessCodes\Edit`
- Garantir criação automática de AccessCode ao criar booking.

### Arquivos-alvo

- `app/Services/AccessCode/**`
- `app/Observers/BookingObserver.php`
- `app/Observers/AccessCodeObserver.php`
- `app/Livewire/Bookings/**`
- `app/Livewire/AccessCodes/**`
- `resources/views/livewire/**`

### Critérios de aceite

- Booking manual gera AccessCode.
- AccessCode sem booking (colaborador) funciona.
- PIN de AccessCode aplicado como PIN do place.

---

## PR-06 — MQTT: publicação, subscriber e operação

### Objetivo

Trocar comunicação legada de dispositivo por MQTT + feedback ao browser.

### Mudanças

- Subir Mosquitto no `docker-compose`.
- Reintroduzir Mosquitto no `docker-compose-prod.yml` com config/auth persistidos.
- Criar `DeviceCommandService` com publish + `CommandLog`.
- Criar comando `mqtt:subscribe` (long-running) para `ack`, `pulse`, `event`.
- Publicar feedback ao frontend via Reverb.
- Registrar processo dedicado no Supervisor para `mqtt:subscribe`.
- Migrar `AccessCodeSyncService` para MQTT.
- Incluir `devices.default_pin` no payload de sync, separado dos AccessCodes.
- Remover gravação de telemetria de sensor em `CommandLog`.

### Arquivos-alvo

- `docker-compose.yml`
- `docker-compose-prod.yml`
- `docker/8.4/supervisord.conf`
- `docker/prod/supervisord.conf`
- `config/mqtt-client.php`
- `app/Console/Commands/MqttSubscribeCommand.php` (novo)
- `app/Services/Device/DeviceCommandService.php` (novo)
- `app/Services/AccessCodeSyncService.php`
- `app/Events/**`
- `app/Listeners/**`
- `.github/workflows/deploy.yml`

### Critérios de aceite

- Comando de controle chega ao dispositivo via MQTT.
- ACK atualiza UI.
- Heartbeat atualiza `last_sync`.
- Subscriber estável em execução contínua.
- `CommandLog` contém apenas comandos (não telemetria).
- `AccessEvent` permanece como fonte de eventos de PIN.

---

## PR-07 — iCal + robustez de sync

### Objetivo

Estabilizar importação de bookings com observabilidade.

### Mudanças

- Refatorar `ICalSyncService` com tratamento de erro/log padronizado.
- Ajustar job de sync para resiliência.
- Criar telas Livewire:
  - `Integrations\Index`
  - `Integrations\Create`

### Arquivos-alvo

- `app/Services/ICalSyncService.php`
- `app/Jobs/SyncIntegrationBookingsJob.php`
- `app/Console/Commands/SyncBookingsCommand.php`
- `app/Livewire/Integrations/**`
- `resources/views/livewire/integrations/**`

### Critérios de aceite

- Integrações ativas sincronizam sem derrubar job por erro isolado.
- Logs suficientes para diagnóstico.

---

## PR-08 — Limpeza final do legado

### Objetivo

Remover restos do painel cliente antigo e da comunicação WebSocket legada de dispositivo.

### Mudanças

- Remover app panel do Filament de vez.
- Remover listeners/events/rotas legadas de dispositivo via WebSocket.
- Manter apenas o admin em Filament (`/admin`).

### Arquivos-alvo

- `app/Filament/App/**` (remover)
- `app/Providers/Filament/AppPanelProvider.php` (remover)
- `app/Listeners/BroadcastMessageListener.php` (ajustar/remover legado)
- `app/Events/*` (legado de dispositivo)
- `routes/channels.php`

### Critérios de aceite

- Cliente usa somente Livewire.
- Admin continua funcional em `/admin`.
- Sem código morto relevante de transporte antigo.

---

## Testes mínimos por PR

- Feature: autenticação cliente.
- Feature: isolamento por place (`place_users`).
- Feature: criação booking -> criação access code.
- Feature: sync access code para dispositivo (mock transport).
- Feature: importação iCal básica.
- Unit: geração de PIN.
- Unit: mapeamento de payload MQTT (command/ack/sync).

---

## Ordem sugerida de commits em cada PR

1. `chore`: ajustes de infraestrutura/config.
2. `refactor`: domínio/policies/services.
3. `feat`: telas/fluxos.
4. `test`: cobertura mínima.
5. `docs`: atualização de plano/checklist.
