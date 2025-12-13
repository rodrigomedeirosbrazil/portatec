# PLANO DE REFATORAÇÃO — TESTES

Este documento detalha os testes necessários para a refatoração.

---

## 1. TESTES UNITÁRIOS

### 1.1 AccessCodeSyncServiceTest

**Arquivo**: `tests/Unit/Services/AccessCodeSyncServiceTest.php`

**Cenários**:
- Sincronizar AccessCodes para dispositivo
- Enviar novo AccessCode
- Atualizar AccessCode existente
- Deletar AccessCode
- Obter AccessCodes válidos

### 1.2 ICalSyncServiceTest

**Arquivo**: `tests/Unit/Services/ICalSyncServiceTest.php`

**Cenários**:
- Parsear arquivo iCal
- Criar bookings a partir de eventos
- Atualizar bookings existentes
- Tratar eventos duplicados

### 1.3 BookingObserverTest

**Arquivo**: `tests/Unit/Observers/BookingObserverTest.php`

**Cenários**:
- Criar AccessCode ao criar booking
- Atualizar AccessCode ao atualizar booking
- Deletar AccessCode ao deletar booking

### 1.4 TuyaEventNormalizerTest

**Arquivo**: `tests/Unit/Services/Tuya/EventNormalizerTest.php`

**Cenários**:
- Normalizar evento de acesso
- Normalizar evento de status
- Tratar eventos inválidos

---

## 2. TESTES DE INTEGRAÇÃO

### 2.1 Sincronização de AccessCodes via WebSocket

**Arquivo**: `tests/Feature/Sync/AccessCodeSyncTest.php`

**Cenários**:
- Dispositivo recebe AccessCodes ao conectar
- Dispositivo recebe novo AccessCode em tempo real
- Dispositivo recebe atualização de AccessCode
- Dispositivo recebe remoção de AccessCode

### 2.2 Criação Automática de AccessCode a partir de Booking

**Arquivo**: `tests/Feature/Bookings/BookingAccessCodeTest.php`

**Cenários**:
- AccessCode criado ao criar booking confirmado
- AccessCode não criado para booking cancelado
- AccessCode atualizado ao atualizar check_in/check_out
- AccessCode deletado ao deletar booking

### 2.3 Importação de Bookings via iCal

**Arquivo**: `tests/Feature/Bookings/ICalSyncTest.php`

**Cenários**:
- Importar bookings de arquivo iCal
- Atualizar bookings existentes
- Tratar eventos duplicados
- Tratar erros de download

### 2.4 Webhook Tuya

**Arquivo**: `tests/Feature/Tuya/TuyaWebhookTest.php`

**Cenários**:
- Receber evento de acesso
- Receber evento de status
- Validar assinatura
- Tratar eventos inválidos

---

## 3. TESTES E2E

### 3.1 Fluxo Completo de Booking

1. Criar Platform com iCal URL
2. Sincronizar bookings
3. Verificar AccessCode criado
4. Verificar sincronização com dispositivo
5. Testar acesso com PIN

### 3.2 Fluxo de Acionamento de Dispositivo

1. Acessar página de gerenciamento de dispositivos
2. Acionar dispositivo Button
3. Verificar comando enviado
4. Verificar log criado
5. Verificar atualização em tempo real

---

## 4. CHECKLIST

### Unitários
- [ ] AccessCodeSyncServiceTest
- [ ] ICalSyncServiceTest
- [ ] BookingObserverTest
- [ ] TuyaEventNormalizerTest

### Integração
- [ ] Sincronização AccessCodes WebSocket
- [ ] Criação AccessCode a partir de Booking
- [ ] Importação iCal
- [ ] Webhook Tuya

### E2E
- [ ] Fluxo completo de Booking
- [ ] Fluxo de acionamento de dispositivo
