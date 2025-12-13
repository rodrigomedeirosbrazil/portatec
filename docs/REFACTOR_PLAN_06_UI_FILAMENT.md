# PLANO DE REFATORAÇÃO — UI E FILAMENT

Este documento detalha as mudanças na interface do usuário e nos recursos do Filament.

---

## 1. REMOÇÃO DA PLACEPAGE

### 1.1 Contexto

A página `PlacePage` (`app/Filament/App/Pages/PlacePage.php`) e sua view (`resources/views/filament/pages/place.blade.php`) foram criadas para permitir o acionamento de dispositivos de um Place. Esta funcionalidade será migrada para um Resource Filament mais adequado.

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
// REMOVER ou ATUALIZAR esta action:
Action::make('view')
    ->label(__('app.view'))
    ->url(fn ($record): string => route('place', $record))
    ->openUrlInNewTab(),
```

### 1.4 Passos de Remoção

1. **Criar o novo Resource** (ver seção 2)
2. **Atualizar PlaceResource** para usar o novo Resource em vez da rota
3. **Remover registros no AppServiceProvider**
4. **Remover rota em routes/web.php**
5. **Deletar arquivos PlacePage.php e place.blade.php**
6. **Verificar e remover outras referências** (grep por "PlacePage", "place.blade.php", "route('place'")

---

## 2. CRIAR RESOURCE FILAMENT PARA ACIONAR DISPOSITIVOS

### 2.1 Estrutura Proposta

Criar um Resource Filament chamado `PlaceDeviceControlResource` ou adicionar uma página customizada ao `PlaceResource` existente.

**Opção Recomendada**: Criar uma página customizada `ManagePlaceDevices` dentro do `PlaceResource`.

### 2.2 Implementação: Página Customizada no PlaceResource

#### 2.2.1 Criar Página de Gerenciamento

**Arquivo**: `app/Filament/App/Resources/PlaceResource/Pages/ManagePlaceDevices.php`

**Funcionalidades**:
- Exibir todos os dispositivos do Place através de `PlaceDeviceFunction`
- Permitir acionar dispositivos Button (push button / pulse)
- Exibir status de dispositivos Sensor (somente leitura)
- Mostrar status online/offline dos dispositivos
- Exibir loading state durante envio de comandos
- Escutar eventos WebSocket para atualizações em tempo real

**Estrutura**:
```php
<?php

namespace App\Filament\App\Resources\PlaceResource\Pages;

use App\Events\DevicePulseEvent;
use App\Models\CommandLog;
use App\Models\Place;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Livewire\Attributes\On;

class ManagePlaceDevices extends Page
{
    public Place $record;

    public array $loadingDevices = [];

    protected static string $view = 'filament.app.resources.place-resource.pages.manage-place-devices';

    public function mount(int | string $record): void
    {
        $this->record = Place::findOrFail($record);

        // Verificar permissões
        if (!auth()->user()->hasRole('super_admin')
            && !$this->record->hasAccessToPlace(auth()->user())) {
            abort(403);
        }
    }

    public function getListeners(): array
    {
        return [
            'echo-private:Place.Device.Status.'.$this->record->id.',PlaceDeviceStatusEvent' => 'refreshDeviceFunctionStatus',
            'echo-private:Place.Device.Command.Ack.'.$this->record->id.',PlaceDeviceCommandAckEvent' => 'showDeviceCommandAck',
            'removeLoading' => 'removeLoading',
        ];
    }

    #[On('pushButton')]
    public function pushButton($deviceFunctionId): void
    {
        // Implementação similar à PlacePage::pushButton
        // Envia um pulse event para o dispositivo
    }

    // Outros métodos...
}
```

#### 2.2.2 Criar View Blade

**Arquivo**: `resources/views/filament/app/resources/place-resource/pages/manage-place-devices.blade.php`

**Estrutura**:
- Grid de cards para cada `PlaceDeviceFunction`
- Botões de ação baseados no tipo do dispositivo
- Indicadores de status online/offline
- Loading states
- Scripts para Livewire events

**Conteúdo baseado em** `place.blade.php`, mas adaptado para o contexto do Resource.

#### 2.2.3 Registrar Página no PlaceResource

**Arquivo**: `app/Filament/App/Resources/PlaceResource.php`

**Adicionar**:
```php
use App\Filament\App\Resources\PlaceResource\Pages\ManagePlaceDevices;

public static function getPages(): array
{
    return [
        'index' => ListPlaces::route('/'),
        'create' => CreatePlace::route('/create'),
        'edit' => EditPlace::route('/{record}/edit'),
        'manage-devices' => ManagePlaceDevices::route('/{record}/manage-devices'), // NOVO
    ];
}
```

**Atualizar action na tabela**:
```php
->recordActions([
    Action::make('manage-devices')
        ->label(__('app.manage_devices'))
        ->icon('heroicon-o-cog-6-tooth')
        ->url(fn (Place $record): string => static::getUrl('manage-devices', ['record' => $record]))
        ->visible(fn (Place $record): bool =>
            auth()->user()->hasRole('super_admin') ||
            $record->placeUsers()
                ->where('user_id', auth()->user()->id)
                ->whereIn('role', [PlaceRoleEnum::Admin, PlaceRoleEnum::Host])
                ->exists()
        ),
    // ... outras actions
])
```

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
- `App\Models\DeviceFunction`

**Enums**:
- `App\Enums\DeviceTypeEnum`

**Filament**:
- `Filament\Resources\Pages\Page`
- `Filament\Notifications\Notification`
- `Filament\Actions\Action`

**Livewire**:
- `Livewire\Attributes\On`

### 2.5 Permissões

A página deve verificar:
- Usuário é super_admin OU
- Usuário tem acesso ao Place (via `PlaceUser`) com role Admin ou Host

**Nota**: Viewers (futuro) não devem ter acesso a esta página.

---

## 3. ATUALIZAR PLACERESOURCE

### 3.1 Remover Action "View"

Remover a action que redireciona para a rota antiga:
```php
Action::make('view')
    ->label(__('app.view'))
    ->url(fn ($record): string => route('place', $record))
    ->openUrlInNewTab(),
```

### 3.2 Adicionar Action "Manage Devices"

Adicionar nova action que leva à página de gerenciamento:
```php
Action::make('manage-devices')
    ->label(__('app.manage_devices'))
    ->icon('heroicon-o-cog-6-tooth')
    ->url(fn (Place $record): string => static::getUrl('manage-devices', ['record' => $record]))
    ->visible(fn (Place $record): bool =>
        auth()->user()->hasRole('super_admin') ||
        $record->placeUsers()
            ->where('user_id', auth()->user()->id)
            ->whereIn('role', [PlaceRoleEnum::Admin, PlaceRoleEnum::Host])
            ->exists()
    ),
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

- `integration_type`: Select com enum (Portatec/Tuya)
- `functional_type`: Select com enum (Pulse/Sensor)
- `default_pin`: TextInput (6 caracteres, nullable)
- `place_id`: Select com relacionamento

### 7.2 Adicionar Colunas na Tabela

- `integration_type`: Badge
- `functional_type`: Badge
- `place.name`: Link para PlaceResource
- `is_online`: Badge (verde/vermelho)

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

### 9.4 AccessEventPolicy

Criar policy para AccessEvent:
- Usuários podem ver eventos de Places que têm acesso
- Somente leitura (não permite criar/editar/deletar)

---

## 10. CHECKLIST DE IMPLEMENTAÇÃO

### Remoção PlacePage
- [ ] Criar novo Resource/Página para acionar dispositivos
- [ ] Atualizar PlaceResource para usar nova página
- [ ] Remover registros no AppServiceProvider
- [ ] Remover rota em routes/web.php
- [ ] Deletar PlacePage.php
- [ ] Deletar place.blade.php
- [ ] Verificar e remover outras referências

### Novo Resource para Acionar Dispositivos
- [ ] Criar ManagePlaceDevices.php
- [ ] Criar view blade
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
- [ ] Adicionar campos no form (integration_type, functional_type, default_pin, place_id)
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
