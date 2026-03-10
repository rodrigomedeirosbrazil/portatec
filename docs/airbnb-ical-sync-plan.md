# Airbnb iCal Sync Implementation Plan

## Context
- PortaTec já persiste `check_in`/`check_out` em UTC, mas o front-end/reporting continua operando como UTC-3 (BRT) temporariamente.
- Há um parser/serviço/job básicos, mas falta lidar com regras específicas do Airbnb, agendamento diário e retenção de histórico ao alterar reservas.

## Goals
1. Sincronizar apenas eventos "Reserved" do Airbnb, ignorando "Not available", mantendo `guest_name` legível (ex.: `Airbnb HMNH8885JB`) e convertendo `VALUE=DATE` para 14h/11h UTC (UTC-3 para o usuário, UTC no banco).
2. Garantir bookings atualizados via soft delete quando mudarem e que cada reserva gere PIN/AccessCode automático.
3. Agendar processo diário às 6h BRT, disparando um job por iCal para evitar bloqueios.
4. Validar que o formulário de integração exige URL export `.ics` e não blocos de detalhes da reserva.

## Step-by-step Implementation
1. Parser
   - [ ] Remover dependência de `ICalHelper` inexistente.
   - [ ] Detectar Airbnb pelo `PRODID`/URL e extrair código da reserva do `/hosting/reservations/details/<codigo>` para montar `guest_name = Airbnb <codigo>`.
   - [ ] Ignorar eventos `SUMMARY: Airbnb (Not available)` e aceitar apenas `Reserved` com URL de detalhes.
   - [ ] Converter eventos `VALUE=DATE` para `check_in` 14:00 UTC e `check_out` 11:00 UTC.
2. Sincronização e bookings
   - [ ] Manter fluxo de `createOrUpdateBooking`, aplicando soft delete (já feito) antes de recriar bookings modificados.
   - [ ] Garantir que falhas de download/parse abortem sync sem afetar bookings existentes.
3. Integrações e interface
   - [ ] Validar no Livewire `Integrations\Create` que Airbnb só aceita URL `.ics` e exibir ajuda explicando a diferença.
   - [ ] Atualizar o texto de feedback para lembrar que o sistema está operando em UTC-3 temporariamente.
4. Scheduler e jobs
   - [ ] Agendar `bookings:sync` via scheduler para rodar diariamente às 6h BRT (09h UTC) e enfileirar `SyncIntegrationBookingsJob` por integração válida.
   - [ ] Manter o `SyncIntegrationBookingsJob` idempotente e responsável por chamar `syncPlaceIntegration` por place.
   - [ ] Ajustar `bookings:sync` para aceitar `--now` (opcional) para execuções imediatas sem enfileirar.

## Testing and Validation
- [ ] Fixture `listing-1119719631343107812.ics` cobrindo 5 bookings válidos e 3 bloqueios ignorados, validando `guest_name` e horários UTC.
- [ ] Teste unitário do parser verificando extração de código, conversão de horários e rejeição de feeds inválidos.
- [ ] Teste de `ICalSyncService` com parser real + HTTP fake garantindo bookings/soft delete e PIN via observer.
- [ ] Teste Livewire `Integrations\Create` para garantir validação `.ics` e mensagem de timezone (UTC storage + UTC-3 apresentação).
- [ ] Cobertura de schedule (unitário ou teste de comando agendado) garantindo disparo diário às 6h BRT e job por iCal.

## Checklist
- [ ] Parser ajustado para Airbnb (detecção, guest_name, horário UTC).
- [ ] SyncService trata atualizações via soft delete e mantém dados em UTC.
- [ ] Integração Livewire requer `.ics` e explica timezone UTC-3.
- [ ] Scheduler diário às 6h BRT disparando job por iCal.
- [ ] `bookings:sync` oferece `--now` e enfileira jobs por integração.
- [ ] Testes adicionados para parser, sync service, integração e schedule.
