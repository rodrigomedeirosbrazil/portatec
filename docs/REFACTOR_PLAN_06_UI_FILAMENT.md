# PLANO DE REFATORAÇÃO — UI E FILAMENT

Este documento detalha as mudanças na interface do usuário e nos recursos do Filament.

---

## 1. REMOÇÃO DA PLACEPAGE

### 1.1 Contexto

A página `PlacePage` (`app/Filament/App/Pages/PlacePage.php`) e sua view (`resources/views/filament/pages/place.blade.php`) foram criadas para permitir o acionamento de dispositivos de um Place. Esta funcionalidade será migrada para uma view Livewire simples, acessível através de botões no PlaceResource.

### 1.2 Funcionalidades Atuais da PlacePage

A `PlacePage` atualmente permite:
- Visualizar todos os dispositivos de um Place através de `PlaceDeviceFunction`
- Acionar dispositivos do tipo **Button** (push button / pulse)
- Visualizar status de dispositivos do tipo **Sensor** (open/closed)
- Receber atualizações em tempo real via WebSocket (Echo)
- Exibir estado de loading durante o envio de comandos
- Registrar comandos no `CommandLog`

### 1.3 Arquivos a Remover

#### 1.3.1 Arquivos Principais
- `app/Filament/App/Pages/PlacePage.php`
- `resources/views/filament/pages/place.blade.php`

#### 1.3.2 Referências a Remover/Atualizar

**AppServiceProvider.php**
```php
// REMOVER estas linhas:
use App\Filament\App\Pages\PlacePage;
Livewire::component('app.filament.app.pages.place-page', PlacePage::class);
Livewire::component('place-page', PlacePage::class);
```

**routes/web.php**
```php
// REMOVER esta rota:
Route::get('/place/{id}/{token?}', App\Filament\App\Pages\PlacePage::class)
    ->name('place');
```

**PlaceResource.php**
```php
// REMOVER esta action antiga (se existir):
Action::make('view')
    ->label(__('app.view'))
    ->url(fn ($record): string => route('place', $record))
    ->openUrlInNewTab(),
```

### 1.4 Passos de Remoção

1. **Criar componente Livewire PlaceDeviceControl** (ver seção 2)
2. **Criar view blade e rota** para o componente
3. **Adicionar actions no PlaceResource** (lista e view) que redirecionam para a nova view
4. **Remover registros no AppServiceProvider**
5. **Remover rota antiga em routes/web.php**
6. **Deletar arquivos PlacePage.php e place.blade.php**
7. **Verificar e remover outras referências** (grep por "PlacePage", "place.blade.php", "route('place'")

---

## 2. CRIAR VIEW PARA ACIONAR DISPOSITIVOS

### 2.1 Estrutura Proposta

Criar uma view Livewire simples para acionar dispositivos do Place. Esta view será acessível através de botões na lista de Places e na view de um Place específico.

### 2.2 Implementação: View Livewire

#### 2.2.1 Criar Componente Livewire

**Arquivo**: `app/Livewire/PlaceDeviceControl.php`

**Funcionalidades**:
- Exibir todos os `DeviceFunction` do Place através de `PlaceDeviceFunction`
- Permitir acionar dispositivos Button (push button / pulse)
- Exibir status de dispositivos Sensor (somente leitura)
- Mostrar status online/offline dos dispositivos
- Exibir loading state durante envio de comandos
- Escutar eventos WebSocket para atualizações em tempo real

**Estrutura**:
```php
<?php

namespace App\Livewire;

use App\Events\DevicePulseEvent;
use App\Models\CommandLog;
use App\Models\Place;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use Livewire\Component;

class PlaceDeviceControl extends Component
{
    public Place $place;

    public array $loadingDevices = [];

    public function mount(int $placeId): void
    {
        $this->place = Place::findOrFail($placeId);

        // Verificar permissões
        if (!auth()->user()->hasRole('super_admin')
            && !$this->place->hasAccessToPlace(auth()->user())) {
            abort(403);
        }

        // Carregar relacionamentos
        $this->place->load('placeDeviceFunctions.deviceFunction.device');
    }

    public function getListeners(): array
    {
        return [
            'echo-private:Place.Device.Status.'.$this->place->id.',PlaceDeviceStatusEvent' => 'refreshDeviceFunctionStatus',
            'echo-private:Place.Device.Command.Ack.'.$this->place->id.',PlaceDeviceCommandAckEvent' => 'showDeviceCommandAck',
            'removeLoading' => 'removeLoading',
        ];
    }

    #[On('pushButton')]
    public function pushButton($deviceFunctionId): void
    {
        $this->loadingDevices[$deviceFunctionId] = true;

        try {
            $placeDeviceFunction = $this->place->placeDeviceFunctions
                ->firstWhere('device_function_id', $deviceFunctionId);

            $deviceFunction = $placeDeviceFunction->deviceFunction;

            if (!$deviceFunction) {
                Notification::make()
                    ->title('Device not found.')
                    ->danger()
                    ->send();
                return;
            }

            broadcast(new DevicePulseEvent(
                $deviceFunction->device->external_device_id,
                ['pin' => $deviceFunction->pin]
            ));

            // Log the command
            CommandLog::create([
                'user_id' => auth()->id(),
                'place_id' => $this->place->id,
                'device_function_id' => $deviceFunction->id,
                'command_type' => 'push_button',
                'device_function_type' => $deviceFunction->type->value ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            Notification::make()
                ->title(__('app.command_sent'))
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error sending command. '.$e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->dispatch('remove-loading', deviceFunctionId: $deviceFunctionId);
        }
    }

    #[On('removeLoading')]
    public function removeLoading($deviceFunctionId): void
    {
        unset($this->loadingDevices[$deviceFunctionId]);
    }

    public function refreshDeviceFunctionStatus(): void
    {
        $this->place->refresh();
        $this->place->load('placeDeviceFunctions.deviceFunction.device');
    }

    public function showDeviceCommandAck(): void
    {
        Notification::make()
            ->title(__('app.command_ack'))
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.place-device-control');
    }
}
```

#### 2.2.2 Criar View Blade

**Arquivo**: `resources/views/livewire/place-device-control.blade.php`

**Estrutura**:
- Grid de cards para cada `PlaceDeviceFunction`
- Botões de ação baseados no tipo do dispositivo (Button/Sensor)
- Indicadores de status online/offline
- Loading states
- Scripts para Livewire events e WebSocket

**Conteúdo baseado em** `place.blade.php`, adaptado para Livewire component.

#### 2.2.3 Criar Rota

**Arquivo**: `routes/web.php`

```php
use App\Livewire\PlaceDeviceControl;

Route::get('/places/{place}/devices', PlaceDeviceControl::class)
    ->middleware(['auth'])
    ->name('places.devices');
```

**Nota**: A rota usa o middleware `auth` do Filament. O componente Livewire também verifica permissões internamente.

### 2.3 Funcionalidades a Implementar

#### 2.3.1 Acionamento de Dispositivos

**Button (Pulse)**:
- Botão que envia um pulse event
- Não altera estado, apenas envia comando
- Mostra loading durante envio

**Sensor (Read-only)**:
- Apenas exibe status (open/closed)
- Não permite acionamento

#### 2.3.2 WebSocket Integration

**Eventos a Escutar**:
- `PlaceDeviceStatusEvent`: Atualiza status dos dispositivos
- `PlaceDeviceCommandAckEvent`: Confirma recebimento de comando

**Canais**:
- `private:Place.Device.Status.{placeId}`
- `private:Place.Device.Command.Ack.{placeId}`

#### 2.3.3 Command Logging

Registrar todos os comandos enviados em `CommandLog`:
- `user_id`: Usuário que enviou
- `place_id`: Place do dispositivo
- `device_function_id`: Função acionada
- `command_type`: 'push_button' (único tipo de comando)
- `device_function_type`: Tipo da função
- `ip_address`: IP do usuário
- `user_agent`: User agent do navegador

#### 2.3.4 Estados e Feedback

- **Loading State**: Mostrar spinner durante envio de comando
- **Offline Indicator**: Badge vermelho quando dispositivo está offline
- **Status Indicator**: Mostrar estado atual (Open/Closed para Sensors)
- **Notifications**: Filament notifications para sucesso/erro

### 2.4 Dependências e Imports

**Eventos**:
- `App\Events\DevicePulseEvent`

**Models**:
- `App\Models\Place`
- `App\Models\CommandLog`
- `App\Models\PlaceDeviceFunction`
- `App\Models\DeviceFunction` (um Device pode ter múltiplas DeviceFunctions - Button e Sensor)
- `App\Models\Device`

**Enums**:
- `App\Enums\DeviceTypeEnum`

**Livewire**:
- `Livewire\Component`
- `Livewire\Attributes\On`

**Filament**:
- `Filament\Notifications\Notification`

### 2.5 Permissões

A view deve verificar:
- Usuário é super_admin OU
- Usuário tem acesso ao Place (via `PlaceUser`) com role Admin ou Host

**Nota**: Viewers (futuro) não devem ter acesso a esta view.

---

## 3. ATUALIZAR PLACERESOURCE

### 3.1 Adicionar Action "Control Devices" na Lista

Adicionar action na tabela de Places que redireciona para a view de controle de dispositivos:

**Arquivo**: `app/Filament/App/Resources/PlaceResource.php`

```php
->recordActions([
    Action::make('control-devices')
        ->label(__('app.control_devices'))
        ->icon('heroicon-o-cog-6-tooth')
        ->url(fn (Place $record): string => route('places.devices', $record))
        ->openUrlInNewTab()
        ->visible(fn (Place $record): bool =>
            auth()->user()->hasRole('super_admin') ||
            $record->placeUsers()
                ->where('user_id', auth()->user()->id)
                ->whereIn('role', [PlaceRoleEnum::Admin, PlaceRoleEnum::Host])
                ->exists()
        ),
    EditAction::make()
        ->visible(fn (Place $record): bool =>
            auth()->user()->hasRole('super_admin') ||
            $record->placeUsers()
                ->where('user_id', auth()->user()->id)
                ->where('role', PlaceRoleEnum::Admin)
                ->exists()
        ),
])
```

### 3.2 Adicionar Action "Control Devices" na View do Place

Adicionar action na página de visualização/edição do Place:

**Arquivo**: `app/Filament/App/Resources/PlaceResource/Pages/EditPlace.php` (ou ViewPlace se existir)

```php
protected function getHeaderActions(): array
{
    return [
        Action::make('control-devices')
            ->label(__('app.control_devices'))
            ->icon('heroicon-o-cog-6-tooth')
            ->url(fn (): string => route('places.devices', $this->record))
            ->openUrlInNewTab()
            ->visible(fn (): bool =>
                auth()->user()->hasRole('super_admin') ||
                $this->record->placeUsers()
                    ->where('user_id', auth()->user()->id)
                    ->whereIn('role', [PlaceRoleEnum::Admin, PlaceRoleEnum::Host])
                    ->exists()
            ),
        // ... outras actions
    ];
}
```

---

## 4. CRIAR RESOURCE PARA BOOKING

### 4.1 Arquivo Principal

**Arquivo**: `app/Filament/App/Resources/BookingResource.php`

### 4.2 Funcionalidades

- Listar bookings
- Criar booking manual
- Editar booking
- Visualizar AccessCode associado
- Visualizar Integration associada
- Filtrar por Place, Integration

### 4.3 Estrutura

```php
<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\BookingResource\Pages;
use App\Models\Booking;
use Filament\Resources\Resource;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    // Form, Table, Pages...
}
```

### 4.4 Páginas

- `ListBookings`: Lista com filtros
- `CreateBooking`: Criar booking manual
- `EditBooking`: Editar booking existente

### 4.5 Relacionamentos a Exibir

- Place (com link para PlaceResource)
- Integration (com link para IntegrationResource)
- AccessCode (se existir, com link para AccessCodeResource)

---

## 5. CRIAR RESOURCE PARA PLATFORM

### 5.1 Arquivo Principal

**Arquivo**: `app/Filament/App/Resources/PlatformResource.php`

### 5.2 Funcionalidades

- Listar platforms (somente leitura, são entidades do sistema)
- Visualizar detalhes da platform
- Mostrar integrações relacionadas

### 5.3 Estrutura

```php
<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\PlatformResource\Pages;
use App\Models\Platform;
use Filament\Resources\Resource;

class PlatformResource extends Resource
{
    protected static ?string $model = Platform::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    // Table, Pages (somente visualização)
}
```

**Nota**: Platforms são entidades do sistema, não editáveis por usuários. Usuários criam Integrations que referenciam Platforms.

---

## 5.1 CRIAR RESOURCE PARA INTEGRATION

### 5.1.1 Arquivo Principal

**Arquivo**: `app/Filament/App/Resources/IntegrationResource.php`

### 5.1.2 Funcionalidades

- Listar integrations do usuário
- Criar/editar integration
- Gerenciar relacionamentos com Places
- Configurar external_id por Place (URL do iCal ou ID da API)
- Sincronizar bookings manualmente (action)
- Mostrar última sincronização
- Filtrar por platform

### 5.1.3 Estrutura

```php
<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\IntegrationResource\Pages;
use App\Models\Integration;
use Filament\Resources\Resource;

class IntegrationResource extends Resource
{
    protected static ?string $model = Integration::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    // Form, Table, Pages...
}
```

### 5.1.4 Action de Sincronização

Adicionar action customizada para sincronizar bookings:
```php
Action::make('sync')
    ->label(__('app.sync_bookings'))
    ->icon('heroicon-o-arrow-path')
    ->action(function (Integration $record) {
        // Chamar ICalSyncService::syncIntegration($record)
        // Mostrar notificação de sucesso/erro
    })
    ->requiresConfirmation()
```

---

## 6. ATUALIZAR ACCESSCODERESOURCE

### 6.1 Renomear de AccessPinResource

- Renomear diretório: `AccessPins` → `AccessCodes`
- Renomear arquivo: `AccessPinResource.php` → `AccessCodeResource.php`
- Atualizar namespace

### 6.2 Adicionar Coluna Booking

Na tabela, adicionar coluna:
```php
TextColumn::make('booking.guest_name')
    ->label(__('app.booking'))
    ->sortable()
    ->searchable()
    ->url(fn ($record) => $record->booking
        ? BookingResource::getUrl('edit', ['record' => $record->booking])
        : null
    ),
```

### 6.3 Mostrar Status (Válido/Expirado)

Adicionar coluna ou badge:
```php
TextColumn::make('status')
    ->label(__('app.status'))
    ->badge()
    ->color(fn ($record) => $record->isValid() ? 'success' : 'danger')
    ->formatStateUsing(fn ($record) => $record->isValid()
        ? __('app.valid')
        : __('app.expired')
    ),
```

**Nota**: Adicionar método `isValid()` no model `AccessCode`:
```php
public function isValid(): bool
{
    $now = now();
    return $now->gte($this->start) && $now->lte($this->end);
}
```

---

## 7. ATUALIZAR DEVICERESOURCE

### 7.1 Adicionar Campos no Form

- `external_device_id`: TextInput (renomeado de chip_id)
- `brand`: Select com enum (Portatec/Tuya)
- `default_pin`: TextInput (6 caracteres, nullable)
- `place_id`: Select com relacionamento
- **DeviceFunctions**: Repeater ou relacionamento para gerenciar múltiplas funções (Button/Sensor)

### 7.2 Adicionar Colunas na Tabela

- `external_device_id`: Text (renomeado de chip_id)
- `brand`: Badge
- `place.name`: Link para PlaceResource
- `device_functions_count`: Contador de funções (Button/Sensor)
- `is_online`: Badge (verde/vermelho) - baseado em last_sync

### 7.3 Mostrar AccessCodes Sincronizados

Adicionar relação ou widget mostrando AccessCodes válidos do Place.

---

## 8. CRIAR RESOURCE PARA ACCESSEVENT

### 8.1 Arquivo Principal

**Arquivo**: `app/Filament/App/Resources/AccessEventResource.php`

### 8.2 Funcionalidades

- Listar eventos de acesso
- Filtrar por Place, Device, resultado (success/failed/expired/invalid)
- Filtrar por data
- Visualizar detalhes do evento
- Exportar eventos

### 8.3 Estrutura

```php
<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\AccessEventResource\Pages;
use App\Models\AccessEvent;
use Filament\Resources\Resource;

class AccessEventResource extends Resource
{
    protected static ?string $model = AccessEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    // Form, Table, Pages...
}
```

### 8.4 Filtros

- Place (Select)
- Device (Select, filtrado por Place)
- Result (Select: success, failed, expired, invalid)
- Date range (DateFilter)

### 8.5 Colunas

- Timestamp (device_timestamp ou server_timestamp)
- Device (com link)
- PIN usado
- Result (badge colorido)
- AccessCode (se associado)
- Place (via Device)

---

## 9. ATUALIZAR POLICIES

### 9.1 PlacePolicy

Verificar e atualizar métodos considerando novos roles:
- `Owner`: Acesso total
- `Admin`: Gerenciamento completo
- `Host`: Gerenciamento (similar a Admin)
- `Viewer`: Somente leitura (futuro)

### 9.2 BookingPolicy

Criar policy para Booking:
- Usuários podem ver bookings de Places que têm acesso
- Apenas Admin/Owner podem criar/editar/deletar

### 9.3 PlatformPolicy

Criar policy para Platform:
- Todos os usuários podem ver platforms (são entidades do sistema)
- Apenas super_admin pode criar/editar/deletar platforms

### 9.4 IntegrationPolicy

Criar policy para Integration:
- Usuários podem ver apenas suas próprias integrations
- Usuários podem criar/editar/deletar suas próprias integrations

### 9.5 AccessEventPolicy

Criar policy para AccessEvent:
- Usuários podem ver eventos de Places que têm acesso
- Somente leitura (não permite criar/editar/deletar)

---

## 10. CHECKLIST DE IMPLEMENTAÇÃO

### Remoção PlacePage
- [ ] Criar componente Livewire PlaceDeviceControl
- [ ] Criar view blade para o componente
- [ ] Criar rota places.devices
- [ ] Adicionar actions no PlaceResource (lista e view)
- [ ] Remover registros no AppServiceProvider
- [ ] Remover rota antiga em routes/web.php
- [ ] Deletar PlacePage.php
- [ ] Deletar place.blade.php
- [ ] Verificar e remover outras referências

### View para Acionar Dispositivos
- [ ] Criar componente Livewire PlaceDeviceControl.php
- [ ] Criar view blade livewire/place-device-control.blade.php
- [ ] Criar rota places.devices (com middleware auth)
- [ ] Adicionar action "Control Devices" na lista de Places
- [ ] Adicionar action "Control Devices" na view do Place
- [ ] Implementar pushButton (pulse)
- [ ] Implementar exibição de status para Sensors
- [ ] Implementar listeners WebSocket
- [ ] Implementar loading states
- [ ] Implementar command logging
- [ ] Adicionar permissões
- [ ] Testar funcionalidades

### Booking Resource
- [ ] Criar BookingResource.php
- [ ] Criar ListBookings.php
- [ ] Criar CreateBooking.php
- [ ] Criar EditBooking.php
- [ ] Implementar form
- [ ] Implementar table
- [ ] Adicionar filtros
- [ ] Adicionar relacionamentos
- [ ] Criar BookingPolicy

### Platform Resource
- [ ] Criar PlatformResource.php
- [ ] Criar ListPlatforms.php (somente visualização)
- [ ] Implementar table
- [ ] Criar PlatformPolicy

### Integration Resource
- [ ] Criar IntegrationResource.php
- [ ] Criar ListIntegrations.php
- [ ] Criar CreateIntegration.php
- [ ] Criar EditIntegration.php
- [ ] Implementar form (platform_id)
- [ ] Implementar gerenciamento de Places relacionados (many-to-many)
- [ ] Implementar form para external_id por Place (pivot table)
- [ ] Implementar table
- [ ] Adicionar action de sincronização
- [ ] Criar IntegrationPolicy

### AccessCode Resource
- [ ] Renomear AccessPinResource → AccessCodeResource
- [ ] Atualizar namespace e referências
- [ ] Adicionar coluna Booking
- [ ] Adicionar coluna Status
- [ ] Adicionar método isValid() no model

### Device Resource
- [ ] Renomear chip_id para external_device_id
- [ ] Adicionar campos no form (external_device_id, brand, default_pin, place_id)
- [ ] Adicionar gerenciamento de DeviceFunctions (múltiplas funções por Device)
- [ ] Adicionar colunas na tabela
- [ ] Mostrar AccessCodes sincronizados

### AccessEvent Resource
- [ ] Criar AccessEventResource.php
- [ ] Criar ListAccessEvents.php
- [ ] Implementar table
- [ ] Adicionar filtros
- [ ] Criar AccessEventPolicy

### Policies
- [ ] Atualizar PlacePolicy
- [ ] Criar BookingPolicy
- [ ] Criar PlatformPolicy
- [ ] Criar IntegrationPolicy
- [ ] Criar AccessEventPolicy

---

## 11. NOTAS IMPORTANTES

### Compatibilidade
- Manter funcionalidades existentes durante a migração
- Testar WebSocket events após mudanças
- Verificar permissões em todos os recursos

### UX
- Manter feedback visual (loading, notifications)
- Garantir que a nova interface seja intuitiva
- Manter consistência com outros Resources Filament

### Performance
- Considerar lazy loading para listas grandes
- Otimizar queries com eager loading
- Cache quando apropriado
