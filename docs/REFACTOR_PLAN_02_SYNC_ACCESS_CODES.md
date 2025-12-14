# PLANO DE REFATORAÇÃO — SINCRONIZAÇÃO DE ACCESSCODES

Este documento detalha a implementação da sincronização de AccessCodes com dispositivos.

---

## 1. CRIAR SERVIÇO DE SINCRONIZAÇÃO

### 1.1 Arquivo Principal

**Arquivo**: `app/Services/AccessCodeSyncService.php`

### 1.2 Responsabilidades

- Enviar AccessCodes válidos para dispositivos via WebSocket
- Gerenciar sincronização incremental (apenas mudanças)
- Lidar com dispositivos offline (fila de sincronização)
- Validar AccessCodes expirados e removê-los dos dispositivos

### 1.3 Métodos Principais

```php
public function syncAccessCodesToDevice(Device $device): void
{
    // Sincroniza todos os AccessCodes válidos do Place
}

public function syncNewAccessCode(AccessCode $accessCode): void
{
    // Envia novo AccessCode para todos os dispositivos do Place
}

public function syncUpdatedAccessCode(AccessCode $accessCode): void
{
    // Atualiza AccessCode nos dispositivos
}

public function syncDeletedAccessCode(AccessCode $accessCode): void
{
    // Remove AccessCode dos dispositivos
}

public function getValidAccessCodesForPlace(Place $place): Collection
{
    // Retorna AccessCodes válidos (não expirados)
}
```

---

## 2. CRIAR EVENTO WEBSOCKET

### 2.1 Arquivo

**Arquivo**: `app/Events/DeviceAccessCodeSyncEvent.php`

### 2.2 Estrutura

```php
class DeviceAccessCodeSyncEvent implements ShouldBroadcast
{
    public function __construct(
        public Device $device,
        public string $action, // 'sync', 'create', 'update', 'delete'
        public ?AccessCode $accessCode = null,
        public ?Collection $accessCodes = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("device-sync.{$this->device->external_device_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'server-access-codes-sync';
    }
}
```

### 2.3 Payload

```json
{
  "event": "server-access-codes-sync",
  "data": {
    "action": "sync|create|update|delete",
    "access_codes": [...], // para action 'sync'
    "access_code": {...}   // para create/update/delete
  }
}
```

---

## 3. INTEGRAR COM OBSERVER

### 3.1 AccessCodeObserver

**Arquivo**: `app/Observers/AccessCodeObserver.php`

```php
<?php

namespace App\Observers;

use App\Models\AccessCode;
use App\Services\AccessCodeSyncService;

class AccessCodeObserver
{
    public function __construct(
        private AccessCodeSyncService $syncService
    ) {}

    public function created(AccessCode $accessCode): void
    {
        $this->syncService->syncNewAccessCode($accessCode);
    }

    public function updated(AccessCode $accessCode): void
    {
        $this->syncService->syncUpdatedAccessCode($accessCode);
    }

    public function deleted(AccessCode $accessCode): void
    {
        $this->syncService->syncDeletedAccessCode($accessCode);
    }
}
```

---

## 4. CRIAR COMANDO DE SINCRONIZAÇÃO

### 4.1 Arquivo

**Arquivo**: `app/Console/Commands/SyncAccessCodesCommand.php`

### 4.2 Uso

```bash
php artisan access-codes:sync
php artisan access-codes:sync --place=1
php artisan access-codes:sync --device=1
```

### 4.3 Agendamento

**Arquivo**: `app/Console/Kernel.php`

```php
$schedule->command('access-codes:sync')
    ->daily()
    ->at('02:00');
```

---

## 5. CHECKLIST

- [ ] Criar AccessCodeSyncService
- [ ] Criar DeviceAccessCodeSyncEvent
- [ ] Atualizar AccessCodeObserver (usar injeção de dependência)
- [ ] Criar comando SyncAccessCodesCommand
- [ ] Agendar comando
- [ ] Testar sincronização completa
- [ ] Testar dispositivos offline
- [ ] Testar AccessCodes expirados
