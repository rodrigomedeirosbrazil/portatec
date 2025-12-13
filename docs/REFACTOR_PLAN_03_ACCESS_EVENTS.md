# PLANO DE REFATORAÇÃO — SISTEMA DE EVENTOS DE ACESSO

Este documento detalha a implementação do sistema de eventos de acesso.

---

## 1. CRIAR MODELO ACCESSEVENT

### 1.1 Migration

**Arquivo**: `database/migrations/XXXX_XX_XX_XXXXXX_create_access_events_table.php`

```php
Schema::create('access_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('device_id')->constrained()->cascadeOnDelete();
    $table->foreignId('access_code_id')->nullable()->constrained()->nullOnDelete();
    $table->string('pin', 6);
    $table->enum('result', ['success', 'failed', 'expired', 'invalid']);
    $table->timestamp('device_timestamp')->nullable();
    $table->timestamp('server_timestamp')->useCurrent();
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->index(['device_id', 'created_at']);
    $table->index(['access_code_id']);
});
```

### 1.2 Model

**Arquivo**: `app/Models/AccessEvent.php`

```php
class AccessEvent extends Model
{
    protected $fillable = [
        'device_id',
        'access_code_id',
        'pin',
        'result',
        'device_timestamp',
        'server_timestamp',
        'metadata',
    ];

    protected $casts = [
        'device_timestamp' => 'datetime',
        'server_timestamp' => 'datetime',
        'metadata' => 'array',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function accessCode(): BelongsTo
    {
        return $this->belongsTo(AccessCode::class);
    }
}
```

---

## 2. ATUALIZAR BROADCASTMESSAGELISTENER

### 2.1 Novo Evento WebSocket

**Evento**: `client-access-event`

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

### 2.2 Implementação

**Arquivo**: `app/Listeners/BroadcastMessageListener.php`

Adicionar handler para `client-access-event`:
```php
if ($event === 'client-access-event') {
    $device = Device::where('chip_id', $data['chip-id'])->first();

    if ($device) {
        $accessCode = AccessCode::where('pin', $data['pin'])
            ->where('place_id', $device->place_id)
            ->first();

        AccessEvent::create([
            'device_id' => $device->id,
            'access_code_id' => $accessCode?->id,
            'pin' => $data['pin'],
            'result' => $data['result'],
            'device_timestamp' => isset($data['timestamp'])
                ? Carbon::createFromTimestamp($data['timestamp'])
                : null,
        ]);
    }
}
```

---

## 3. INTERFACE UNIFICADA DE EVENTOS

### 3.1 Contract

**Arquivo**: `app/Contracts/DeviceEventInterface.php`

```php
interface DeviceEventInterface
{
    public function normalize(): array;
    public function getDevice(): Device;
    public function getTimestamp(): Carbon;
}
```

### 3.2 Implementações

- `PortatecDeviceEvent`
- `TuyaDeviceEvent`

---

## 4. CHECKLIST

- [ ] Criar migration access_events
- [ ] Criar model AccessEvent
- [ ] Atualizar BroadcastMessageListener
- [ ] Criar DeviceEventInterface
- [ ] Implementar PortatecDeviceEvent
- [ ] Implementar TuyaDeviceEvent
- [ ] Testar recebimento de eventos
- [ ] Testar criação de AccessEvent
