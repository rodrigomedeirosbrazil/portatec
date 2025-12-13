# PLANO DE REFATORAÇÃO — PORTATEC

Este documento detalha as mudanças necessárias na codebase atual para alcançar o estado desejado descrito em `NEW_PORTATEC_PLAN.md`.

---

## 1. ANÁLISE DO ESTADO ATUAL

### 1.1 Modelos Existentes

**Users**
- ✅ Existe e está funcional
- ✅ Relacionamento com Places via `PlaceUser`
- ❌ Falta relacionamento com Platforms

**Places**
- ✅ Existe e está funcional
- ✅ Relacionamento com Users via `PlaceUser`
- ❌ Falta relacionamento direto com Devices
- ❌ Falta relacionamento com Bookings
- ❌ Falta relacionamento direto com AccessCodes (atualmente via AccessPin)

**Devices**
- ✅ Existe e está funcional
- ✅ Tem `chip_id` para identificação
- ✅ Tem `last_sync` para rastreamento
- ❌ Falta relacionamento direto com Place
- ❌ Falta campo para tipo de integração (Portatec/Tuya)
- ❌ Falta campo para tipo funcional (Pulse/Sensor)
- ❌ Falta campo para PIN padrão (fallback)
- ❌ Não há sincronização de AccessCodes

**AccessPin**
- ✅ Existe e está funcional
- ✅ Tem relacionamento com Place
- ✅ Tem PIN de 6 dígitos
- ✅ Tem datas de início e fim
- ❌ Falta relacionamento com Booking (opcional)
- ❌ Nome deveria ser AccessCode (conforme plano)
- ❌ Não há sincronização automática com dispositivos

**DeviceFunction**
- ✅ Existe e representa funções do dispositivo
- ✅ Tem tipo (Switch/Sensor/Button)
- ⚠️ Relacionamento com Place é indireto via `PlaceDeviceFunction`
- ❌ Não está alinhado com o conceito de tipos funcionais (Pulse/Sensor) do plano

### 1.2 Modelos Faltantes

- ❌ **Booking**: Não existe
- ❌ **Platform**: Não existe

### 1.3 Funcionalidades Faltantes

- ❌ Sincronização de AccessCodes com dispositivos
- ❌ Validação offline de PIN nos dispositivos
- ❌ Cache de eventos nos dispositivos
- ❌ Integração com iCal para importação de bookings
- ❌ Criação automática de AccessCode a partir de Booking
- ❌ Normalização de eventos entre Portatec e Tuya
- ❌ Webhook para receber eventos da Tuya
- ❌ Sistema de sincronização bidirecional (servidor ↔ dispositivo)

### 1.4 Comunicação

**WebSocket (Reverb)**
- ✅ Existe e está configurado
- ✅ Canais para dispositivos (`device-sync.{chipId}`)
- ⚠️ Falta sincronização de AccessCodes
- ⚠️ Falta envio de eventos pendentes do dispositivo

**Tuya**
- ✅ Existe `TuyaService` básico
- ❌ Falta webhook para receber eventos
- ❌ Falta normalização de eventos Tuya → Portatec

---

## 2. MUDANÇAS NECESSÁRIAS

### 2.1 Modelos e Banco de Dados

#### 2.1.1 Renomear AccessPin para AccessCode

**Justificativa**: O plano usa o termo "AccessCode" consistentemente.

**Ações**:
1. Criar migration para renomear tabela `access_pins` → `access_codes`
2. Renomear model `AccessPin` → `AccessCode`
3. Atualizar todas as referências no código
4. Atualizar observers, events, policies, resources Filament

**Arquivos afetados**:
- `app/Models/AccessPin.php` → `app/Models/AccessCode.php`
- `app/Observers/AccessPinObserver.php` → `app/Observers/AccessCodeObserver.php`
- `app/Events/AccessPinEvent.php` → `app/Events/AccessCodeEvent.php`
- `app/Policies/AccessPinPolicy.php` → `app/Policies/AccessCodePolicy.php`
- `app/Filament/App/Resources/AccessPins/AccessPinResource.php`
- Todas as migrations que referenciam `access_pins`
- Todas as foreign keys

#### 2.1.2 Adicionar relacionamento Booking em AccessCode

**Migration**:
```php
Schema::table('access_codes', function (Blueprint $table) {
    $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
});
```

**Model AccessCode**:
- Adicionar relacionamento `belongsTo(Booking::class)`

#### 2.1.3 Criar modelo Booking

**Migration**:
```php
Schema::create('bookings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('place_id')->constrained()->cascadeOnDelete();
    $table->foreignId('platform_id')->nullable()->constrained()->nullOnDelete();
    $table->string('guest_name');
    $table->timestamp('check_in');
    $table->timestamp('check_out');
    $table->timestamps();
});
```

**Model Booking**:
- Relacionamentos: `belongsTo(Place::class)`, `belongsTo(Platform::class)`, `hasOne(AccessCode::class)`
- Observer para criar AccessCode automaticamente quando booking é criado

#### 2.1.4 Criar modelo Platform

**Migration**:
```php
Schema::create('platforms', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name'); // 'airbnb', 'booking.com', etc.
    $table->string('ical_url')->nullable();
    $table->json('credentials')->nullable(); // Para armazenar credenciais específicas
    $table->timestamp('last_sync')->nullable();
    $table->timestamps();
});
```

**Model Platform**:
- Relacionamentos: `belongsTo(User::class)`, `hasMany(Booking::class)`
- Métodos para sincronização iCal

#### 2.1.5 Atualizar modelo Device

**Migrations necessárias**:
```php
Schema::table('devices', function (Blueprint $table) {
    $table->foreignId('place_id')->constrained()->cascadeOnDelete();
    $table->string('integration_type')->default('portatec'); // 'portatec' ou 'tuya'
    $table->string('functional_type')->nullable(); // 'pulse' ou 'sensor'
    $table->string('default_pin', 6)->nullable(); // PIN padrão para manutenção
    $table->string('tuya_device_id')->nullable(); // Para dispositivos Tuya
});
```

**Model Device**:
- Adicionar relacionamento `belongsTo(Place::class)`
- Adicionar enum para `integration_type` (Portatec/Tuya)
- Adicionar enum para `functional_type` (Pulse/Sensor)
- Métodos para sincronização de AccessCodes
- Método para obter AccessCodes válidos do Place

**Criar Enums**:
- `DeviceIntegrationTypeEnum`: Portatec, Tuya
- `DeviceFunctionalTypeEnum`: Pulse, Sensor

#### 2.1.6 Atualizar modelo Place

**Model Place**:
- Adicionar relacionamento `hasMany(Device::class)`
- Adicionar relacionamento `hasMany(AccessCode::class)`
- Adicionar relacionamento `hasMany(Booking::class)`
- Método para obter todos os AccessCodes válidos

#### 2.1.7 Atualizar modelo User

**Model User**:
- Adicionar relacionamento `hasMany(Platform::class)`

#### 2.1.8 Atualizar PlaceRoleEnum

**Enum**:
- Adicionar caso `Owner`
- Adicionar caso `Viewer` (futuro)
- Manter `Admin` e `Host` (ou mapear para novos valores)

---

### 2.2 Sincronização de AccessCodes

#### 2.2.1 Criar serviço de sincronização

**Arquivo**: `app/Services/AccessCodeSyncService.php`

**Responsabilidades**:
- Enviar AccessCodes válidos para dispositivos via WebSocket
- Gerenciar sincronização incremental (apenas mudanças)
- Lidar com dispositivos offline (fila de sincronização)
- Validar AccessCodes expirados e removê-los dos dispositivos

**Métodos principais**:
- `syncAccessCodesToDevice(Device $device)`: Sincroniza todos os AccessCodes válidos
- `syncNewAccessCode(AccessCode $accessCode)`: Envia novo AccessCode para dispositivos do Place
- `syncUpdatedAccessCode(AccessCode $accessCode)`: Atualiza AccessCode nos dispositivos
- `syncDeletedAccessCode(AccessCode $accessCode)`: Remove AccessCode dos dispositivos
- `getValidAccessCodesForPlace(Place $place)`: Retorna AccessCodes válidos (não expirados)

#### 2.2.2 Integrar sincronização com eventos

**AccessCodeObserver**:
- Ao criar: chamar `AccessCodeSyncService::syncNewAccessCode()`
- Ao atualizar: chamar `AccessCodeSyncService::syncUpdatedAccessCode()`
- Ao deletar: chamar `AccessCodeSyncService::syncDeletedAccessCode()`

**DeviceCreatedEvent**:
- Ao criar dispositivo: sincronizar todos os AccessCodes válidos do Place

#### 2.2.3 Criar evento WebSocket para sincronização

**Arquivo**: `app/Events/DeviceAccessCodeSyncEvent.php`

**Canal**: `device-sync.{chipId}`

**Payload**:
```json
{
  "event": "server-access-codes-sync",
  "data": {
    "action": "sync|create|update|delete",
    "access_codes": [...],
    "access_code": {...} // para create/update/delete
  }
}
```

#### 2.2.4 Criar comando para sincronização manual

**Arquivo**: `app/Console/Commands/SyncAccessCodesCommand.php`

**Uso**: Para sincronização manual ou agendada (ex: diária para garantir consistência)

---

### 2.3 Sistema de Eventos de Acesso

#### 2.3.1 Criar modelo AccessEvent

**Migration**:
```php
Schema::create('access_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('device_id')->constrained()->cascadeOnDelete();
    $table->foreignId('access_code_id')->nullable()->constrained()->nullOnDelete();
    $table->string('pin', 6);
    $table->enum('result', ['success', 'failed', 'expired', 'invalid']);
    $table->timestamp('device_timestamp')->nullable(); // Timestamp do dispositivo
    $table->timestamp('server_timestamp')->useCurrent(); // Timestamp do servidor
    $table->json('metadata')->nullable(); // Dados adicionais
    $table->timestamps();
});
```

**Model AccessEvent**:
- Relacionamentos: `belongsTo(Device::class)`, `belongsTo(AccessCode::class)`
- Escopo para eventos pendentes (não sincronizados)

#### 2.3.2 Criar evento WebSocket para receber eventos do dispositivo

**Listener**: Atualizar `BroadcastMessageListener`

**Novo evento**: `client-access-event`

**Payload esperado**:
```json
{
  "event": "client-access-event",
  "data": {
    "chip-id": "...",
    "pin": "123456",
    "result": "success|failed|expired|invalid",
    "timestamp": 1234567890
  }
}
```

**Ação**: Criar registro em `AccessEvent`

#### 2.3.3 Criar fila para eventos pendentes

Quando dispositivo está offline, eventos devem ser armazenados localmente e enviados quando online.

**No dispositivo (firmware)**: Implementar cache local de eventos
**No servidor**: Receber eventos pendentes quando dispositivo reconecta

---

### 2.4 Integração com Bookings

#### 2.4.1 Criar observer para Booking

**Arquivo**: `app/Observers/BookingObserver.php`

**Ações**:
- `created`: Criar AccessCode automaticamente com:
  - PIN gerado automaticamente (6 dígitos)
  - `start` = `check_in`
  - `end` = `check_out`
  - `booking_id` = booking.id
  - `place_id` = booking.place_id

- `updated`: Atualizar AccessCode se check_in/check_out mudarem
- `deleted`: Deletar AccessCode associado

#### 2.4.2 Criar serviço de importação iCal

**Arquivo**: `app/Services/ICalSyncService.php`

**Responsabilidades**:
- Baixar e parsear arquivo iCal
- Criar/atualizar Bookings
- Associar com Platform

**Dependências**:
- Biblioteca para parsear iCal (ex: `kigkonsult/icalcreator` ou similar)

#### 2.4.3 Criar comando para sincronização iCal

**Arquivo**: `app/Console/Commands/SyncICalBookingsCommand.php`

**Uso**: Agendado (ex: a cada hora) para sincronizar bookings de todas as Platforms

**Agendamento**: Adicionar em `app/Console/Kernel.php`

---

### 2.5 Melhorias na Comunicação Tuya

#### 2.5.1 Criar webhook para eventos Tuya

**Rota**: `POST /api/webhooks/tuya`

**Controller**: `app/Http/Controllers/Webhook/TuyaWebhookController.php`

**Responsabilidades**:
- Receber eventos da Tuya
- Normalizar dados para formato Portatec
- Criar eventos unificados (ex: `DeviceStatusEvent`, `AccessEvent`)

#### 2.5.2 Normalizar eventos Tuya

**Arquivo**: `app/Services/Tuya/EventNormalizer.php`

**Responsabilidades**:
- Converter eventos Tuya para formato interno
- Criar `AccessEvent` quando houver tentativa de acesso
- Criar `DeviceStatusEvent` quando houver mudança de status

#### 2.5.3 Atualizar TuyaService

**Melhorias**:
- Método para enviar comandos de abertura
- Método para obter status do dispositivo
- Tratamento de erros melhorado

---

### 2.6 Interface Unificada de Eventos

#### 2.6.1 Criar interface para eventos de dispositivo

**Arquivo**: `app/Contracts/DeviceEventInterface.php`

**Implementações**:
- `PortatecDeviceEvent`
- `TuyaDeviceEvent`

**Normalização**: Ambos convertem para eventos internos unificados

#### 2.6.2 Atualizar listeners

**BroadcastMessageListener**:
- Normalizar eventos de diferentes fontes
- Criar eventos unificados

---

### 2.7 Autenticação e Autorização

#### 2.7.1 Atualizar PlaceRoleEnum

Adicionar casos:
- `Owner`
- `Viewer` (preparação para futuro)

#### 2.7.2 Atualizar Policies

**PlacePolicy**:
- Verificar roles atualizados
- Owner tem acesso total
- Admin/Co-host tem acesso de gerenciamento
- Viewer tem acesso somente leitura (futuro)

---

### 2.8 Melhorias na UI (Filament)

#### 2.8.1 Criar Resource para Booking

**Arquivo**: `app/Filament/App/Resources/BookingResource.php`

**Funcionalidades**:
- Listar bookings
- Criar booking manual
- Visualizar AccessCode associado
- Sincronizar com Platform

#### 2.8.2 Criar Resource para Platform

**Arquivo**: `app/Filament/App/Resources/PlatformResource.php`

**Funcionalidades**:
- Listar platforms
- Criar/editar platform
- Configurar iCal URL
- Sincronizar bookings manualmente

#### 2.8.3 Atualizar AccessCodeResource

- Renomear de AccessPinResource
- Adicionar coluna para Booking (se existir)
- Mostrar status (válido/expirado)

#### 2.8.4 Atualizar DeviceResource

- Adicionar campos: integration_type, functional_type, default_pin
- Mostrar relacionamento direto com Place
- Mostrar AccessCodes sincronizados

#### 2.8.5 Criar Resource para AccessEvent

**Arquivo**: `app/Filament/App/Resources/AccessEventResource.php`

**Funcionalidades**:
- Listar eventos de acesso
- Filtrar por Place, Device, resultado
- Visualizar detalhes do evento

---

### 2.9 Testes

#### 2.9.1 Testes unitários

- `AccessCodeSyncServiceTest`
- `ICalSyncServiceTest`
- `BookingObserverTest`
- `TuyaEventNormalizerTest`

#### 2.9.2 Testes de integração

- Sincronização de AccessCodes via WebSocket
- Criação automática de AccessCode a partir de Booking
- Importação de bookings via iCal
- Webhook Tuya

---

## 3. ORDEM DE IMPLEMENTAÇÃO RECOMENDADA

### Fase 1: Fundação (Modelos e Banco de Dados)
1. Renomear AccessPin → AccessCode
2. Criar modelos Booking e Platform
3. Atualizar relacionamentos em todos os modelos
4. Criar migrations necessárias
5. Atualizar enums (DeviceIntegrationTypeEnum, DeviceFunctionalTypeEnum, PlaceRoleEnum)

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
1. Criar Resources Filament (Booking, Platform, AccessEvent)
2. Atualizar Resources existentes
3. Atualizar Policies
4. Testar permissões

### Fase 7: Testes e Documentação
1. Escrever testes unitários
2. Escrever testes de integração
3. Documentar APIs
4. Atualizar README

---

## 4. CONSIDERAÇÕES IMPORTANTES

### 4.1 Compatibilidade com Firmware

As mudanças no servidor devem ser compatíveis com o firmware dos dispositivos. É necessário:
- Documentar protocolo WebSocket
- Versionar API de sincronização
- Manter compatibilidade retroativa quando possível

### 4.2 Performance

- Sincronização de AccessCodes pode ser custosa com muitos dispositivos
- Considerar filas para sincronização assíncrona
- Cache de AccessCodes válidos no servidor

### 4.3 Segurança

- Validar PINs no servidor também (além da validação local)
- Criptografar comunicação WebSocket (WSS)
- Validar webhooks Tuya (assinatura)

### 4.4 Migração de Dados

- Criar script de migração para renomear AccessPin → AccessCode
- Migrar dados existentes para novos modelos
- Testar migração em ambiente de staging

---

## 5. CHECKLIST DE IMPLEMENTAÇÃO

### Modelos e Banco de Dados
- [ ] Renomear AccessPin → AccessCode
- [ ] Criar modelo Booking
- [ ] Criar modelo Platform
- [ ] Atualizar modelo Device (place_id, integration_type, functional_type, default_pin)
- [ ] Atualizar modelo Place (relacionamentos)
- [ ] Atualizar modelo User (relacionamento com Platform)
- [ ] Criar modelo AccessEvent
- [ ] Atualizar PlaceRoleEnum

### Sincronização
- [ ] Criar AccessCodeSyncService
- [ ] Criar eventos WebSocket para sincronização
- [ ] Integrar com observers
- [ ] Criar comando de sincronização manual
- [ ] Testar sincronização

### Eventos
- [ ] Criar AccessEvent model
- [ ] Atualizar BroadcastMessageListener
- [ ] Criar interface unificada de eventos
- [ ] Implementar normalização Tuya

### Bookings
- [ ] Criar BookingObserver
- [ ] Implementar criação automática de AccessCode
- [ ] Criar ICalSyncService
- [ ] Criar comando de sincronização iCal
- [ ] Agendar sincronização

### Tuya
- [ ] Criar webhook controller
- [ ] Criar EventNormalizer
- [ ] Atualizar TuyaService

### UI
- [ ] Criar BookingResource
- [ ] Criar PlatformResource
- [ ] Criar AccessEventResource
- [ ] Atualizar AccessCodeResource
- [ ] Atualizar DeviceResource
- [ ] Atualizar Policies

### Testes
- [ ] Testes unitários
- [ ] Testes de integração
- [ ] Testes E2E

---

## 6. NOTAS FINAIS

Este plano é abrangente e cobre todas as mudanças necessárias para alcançar o estado desejado. A implementação deve ser feita de forma incremental, testando cada fase antes de prosseguir.

Algumas funcionalidades (como cache de eventos no dispositivo) dependem de mudanças no firmware, que estão fora do escopo deste plano de refatoração do backend.

Mantenha este documento atualizado conforme a implementação progride e novos requisitos surgem.
