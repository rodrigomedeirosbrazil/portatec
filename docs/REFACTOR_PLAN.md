# PLANO DE REFATORAÇÃO — PORTATEC

Este documento é o índice principal do plano de refatoração. Os detalhes de cada seção estão em documentos separados para facilitar a navegação e manutenção.

## 📋 Visão Geral

Este plano detalha as mudanças necessárias na codebase atual para alcançar o estado desejado descrito em `MIGRATION_PLAN.md`.

## 📚 Documentos Detalhados

### 1. [Modelos e Banco de Dados](./REFACTOR_PLAN_01_MODELS_AND_DATABASE.md)
- Renomear AccessPin → AccessCode
- Criar modelos Booking e Platform
- Atualizar relacionamentos em todos os modelos
- Criar migrations necessárias
- Atualizar enums

### 2. [Sincronização de AccessCodes](./REFACTOR_PLAN_02_SYNC_ACCESS_CODES.md)
- Criar AccessCodeSyncService
- Criar eventos WebSocket para sincronização
- Integrar com AccessCodeObserver
- Criar comando de sincronização manual

### 3. [Sistema de Eventos de Acesso](./REFACTOR_PLAN_03_ACCESS_EVENTS.md)
- Criar modelo AccessEvent
- Atualizar BroadcastMessageListener
- Criar interface unificada de eventos
- Implementar cache de eventos no dispositivo

### 4. [Integração com Bookings](./REFACTOR_PLAN_04_BOOKINGS.md)
- Criar BookingObserver
- Implementar criação automática de AccessCode
- Criar ICalSyncService
- Criar comando de sincronização iCal

### 5. [Melhorias na Comunicação Tuya](./REFACTOR_PLAN_05_TUYA.md)
- Criar webhook controller
- Criar EventNormalizer
- Atualizar TuyaService
- Normalizar eventos Tuya → Portatec

### 6. [UI e Filament](./REFACTOR_PLAN_06_UI_FILAMENT.md)
- **Remover PlacePage e place.blade.php**
- **Criar Resource Filament para acionar dispositivos**
- Criar Resources Filament (Booking, Platform, AccessEvent)
- Atualizar Resources existentes
- Atualizar Policies

### 7. [Testes](./REFACTOR_PLAN_07_TESTS.md)
- Testes unitários
- Testes de integração
- Testes E2E

---

## 🎯 Ordem de Implementação Recomendada

### Fase 1: Fundação (Modelos e Banco de Dados)
1. Renomear AccessPin → AccessCode
2. Criar modelos Booking e Platform
3. Atualizar relacionamentos em todos os modelos
4. Criar migrations necessárias
5. Atualizar enums (DeviceBrandEnum, PlaceRoleEnum)
6. Renomear chip_id → external_device_id em devices

### Fase 2: Sincronização de AccessCodes
1. Criar AccessCodeSyncService
2. Criar eventos WebSocket para sincronização
3. Integrar com AccessCodeObserver
4. Criar comando de sincronização manual
5. Testar sincronização

### Fase 3: Sistema de Eventos
1. Criar modelo AccessEvent
2. Atualizar BroadcastMessageListener para receber eventos de acesso
3. Criar interface unificada de eventos
4. Implementar cache de eventos no dispositivo (firmware - fora do escopo deste plano)

### Fase 4: Integração com Bookings
1. Criar BookingObserver
2. Implementar criação automática de AccessCode
3. Criar ICalSyncService
4. Criar comando de sincronização iCal
5. Agendar sincronização

### Fase 5: Melhorias Tuya
1. Criar webhook controller
2. Criar EventNormalizer
3. Atualizar TuyaService
4. Testar integração completa

### Fase 6: UI e Políticas
1. **Remover PlacePage e place.blade.php**
2. **Criar Resource Filament para acionar dispositivos**
3. Criar Resources Filament (Booking, Platform, AccessEvent)
4. Atualizar Resources existentes
5. Atualizar Policies
6. Testar permissões

### Fase 7: Testes e Documentação
1. Escrever testes unitários
2. Escrever testes de integração
3. Documentar APIs
4. Atualizar README

---

## ⚠️ Considerações Importantes

### Compatibilidade com Firmware
As mudanças no servidor devem ser compatíveis com o firmware dos dispositivos. É necessário:
- Documentar protocolo WebSocket
- Versionar API de sincronização
- Manter compatibilidade retroativa quando possível

### Performance
- Sincronização de AccessCodes pode ser custosa com muitos dispositivos
- Considerar filas para sincronização assíncrona
- Cache de AccessCodes válidos no servidor

### Segurança
- Validar PINs no servidor também (além da validação local)
- Criptografar comunicação WebSocket (WSS)
- Validar webhooks Tuya (assinatura)

### Migração de Dados
- Criar script de migração para renomear AccessPin → AccessCode
- Migrar dados existentes para novos modelos
- Testar migração em ambiente de staging

---

## 📝 Checklist de Implementação

Consulte os documentos individuais para checklists detalhados de cada seção.

---

## 📌 Notas Finais

Este plano é abrangente e cobre todas as mudanças necessárias para alcançar o estado desejado. A implementação deve ser feita de forma incremental, testando cada fase antes de prosseguir.

Algumas funcionalidades (como cache de eventos no dispositivo) dependem de mudanças no firmware, que estão fora do escopo deste plano de refatoração do backend.

Mantenha este documento e os documentos relacionados atualizados conforme a implementação progride e novos requisitos surgem.
