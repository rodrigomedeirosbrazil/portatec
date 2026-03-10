# Airbnb iCal Sync Implementation Plan

## Context
- PortaTec persiste `check_in`/`check_out` em UTC, mas a aplicação continua operando em UTC-3 (BRT), o que for exibido precisa estar em UTC-3.
- Já existem parser/serviço/job básicos, mas faltam regras específicas do Airbnb, scheduler diário e retenção de histórico.

## Goals
1. Sincronizar apenas eventos "Reserved" do Airbnb, ignorando "Not available", mantendo `guest_name` legível (ex.: `Airbnb HMNH8885JB`) e convertendo `VALUE=DATE` para 14h/11h UTC.
2. Garantir que mudanças de reserva gerem soft delete do booking antigo e criação de um novo, preservando histórico.
3. Agendar processo diário às 6h BRT, disparando um job por iCal para evitar bloqueios.
4. Validar que o formulário de integração exige URL export `.ics` (não `/hosting/reservations/details/...`).
5. Tratar falhas de download/parse sem apagar bookings existentes.

## Step-by-step Implementation
1. Parser (Airbnb)
   - [x] Remover dependência de `ICalHelper` inexistente.
   - [x] Detectar Airbnb por `PRODID`/URL e extrair código do `/hosting/reservations/details/<codigo>`.
   - [x] Importar somente `SUMMARY: Reserved` com URL de detalhes e ignorar `Airbnb (Not available)`.
   - [x] Definir `guest_name` como `Airbnb <codigo>` quando possível, com fallback legível.
   - [x] Converter `VALUE=DATE` para `check_in` 14:00 UTC e `check_out` 11:00 UTC (sistema em UTC-3).
2. Sincronização e histórico
   - [x] Manter `createOrUpdateBooking` com soft delete e recriação quando check-in/out ou hóspede mudarem.
   - [x] Se download/parse falhar, abortar sync sem remover bookings existentes.
3. Integrações e interface
   - [ ] Validar no Livewire `Integrations\Create` que Airbnb só aceita URL `.ics`.
   - [ ] Rejeitar explicitamente `/hosting/reservations/details/...` e mostrar orientação.
   - [ ] Exibir nota de timezone: banco em UTC e operação atual em UTC-3.
4. Scheduler e jobs
   - [ ] Rodar `bookings:sync` diariamente às 6h BRT (09h UTC).
   - [ ] Para cada integração com `external_id` válido, enfileirar `SyncIntegrationBookingsJob` separado.
   - [ ] Ajustar `bookings:sync` com `--now` para execução imediata sem fila.

## Testing and Validation
- [ ] Fixture `listing-1119719631343107812.ics` cobrindo 5 bookings válidos e 3 bloqueios ignorados.
- [ ] Teste unitário do parser validando extração do código, conversão de horários e rejeição de feeds inválidos/HTML.
- [ ] Teste de `ICalSyncService` com parser real + HTTP fake garantindo soft delete + recriação e PIN via observer.
- [ ] Teste Livewire `Integrations\Create` para validação `.ics` e mensagem de timezone.
- [ ] Cobertura de schedule garantindo execução diária 6h BRT e job por integração.

## Checklist
- [x] Parser ajustado para Airbnb (detecção, filtros, `guest_name`, horários UTC).
- [x] SyncService preserva histórico via soft delete + recriação.
- [x] Falhas de download/parse não removem dados existentes.
- [ ] Integração Livewire requer `.ics` e orienta sobre URL de detalhes.
- [ ] Scheduler diário 6h BRT enfileira job por iCal.
- [ ] `bookings:sync --now` disponível para execuções imediatas.
- [ ] Testes cobrindo parser, sync service, Livewire e scheduler.
