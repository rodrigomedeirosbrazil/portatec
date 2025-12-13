# PLANO DE REFATORAÇÃO — MODELOS E BANCO DE DADOS

Este documento detalha as mudanças nos modelos e no banco de dados.

---

## 1. RENOMEAR ACCESSPIN PARA ACCESSCODE

### 1.1 Justificativa

O plano usa o termo "AccessCode" consistentemente. O nome atual "AccessPin" deve ser renomeado para manter consistência.

### 1.2 Ações Detalhadas

#### 1.2.1 Migration para Renomear Tabela

**Arquivo**: `database/migrations/XXXX_XX_XX_XXXXXX_rename_access_pins_to_access_codes.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('access_pins', 'access_codes');

        // Renomear foreign keys
        Schema::table('access_codes', function (Blueprint $table) {
            $table->renameColumn('access_pin_id', 'access_code_id'); // se existir
        });
    }

    public function down(): void
    {
        Schema::rename('access_codes', 'access_pins');
    }
};
```

#### 1.2.2 Renomear Model

**Arquivo**: `app/Models/AccessPin.php` → `app/Models/AccessCode.php`

**Mudanças**:
- Renomear classe
- Atualizar `$table = 'access_codes'`
- Atualizar relacionamentos
- Atualizar namespace em todos os imports

#### 1.2.3 Atualizar Observer

**Arquivo**: `app/Observers/AccessPinObserver.php` → `app/Observers/AccessCodeObserver.php`

#### 1.2.4 Atualizar Event

**Arquivo**: `app/Events/AccessPinEvent.php` → `app/Events/AccessCodeEvent.php`

#### 1.2.5 Atualizar Policy

**Arquivo**: `app/Policies/AccessPinPolicy.php` → `app/Policies/AccessCodePolicy.php`

#### 1.2.6 Atualizar Resource Filament

**Arquivo**: `app/Filament/App/Resources/AccessPins/AccessPinResource.php` → `app/Filament/App/Resources/AccessCodes/AccessCodeResource.php`

#### 1.2.7 Buscar e Atualizar Todas as Referências

**Comandos**:
```bash
# Buscar referências
grep -r "AccessPin" app/
grep -r "access_pin" app/
grep -r "accessPin" app/
```

**Arquivos comuns a verificar**:
- Migrations
- Seeders
- Factories
- Tests
- Controllers
- Services
- Providers (AppServiceProvider)

---

## 2. ADICIONAR RELACIONAMENTO BOOKING EM ACCESSCODE

### 2.1 Migration

**Arquivo**: `database/migrations/XXXX_XX_XX_XXXXXX_add_booking_id_to_access_codes.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_codes', function (Blueprint $table) {
            $table->foreignId('booking_id')
                ->nullable()
                ->after('place_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('access_codes', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropColumn('booking_id');
        });
    }
};
```

### 2.2 Atualizar Model AccessCode

```php
public function booking(): BelongsTo
{
    return $this->belongsTo(Booking::class);
}
```

---

## 3. CRIAR MODELO BOOKING

### 3.1 Migration

**Arquivo**: `database/migrations/XXXX_XX_XX_XXXXXX_create_bookings_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id')->nullable(); // Para iCal UID
            $table->string('guest_name');
            $table->timestamp('check_in');
            $table->timestamp('check_out');
            $table->enum('status', ['confirmed', 'cancelled'])->default('confirmed');
            $table->timestamps();

            // Índices
            $table->index(['place_id', 'check_in', 'check_out']);
            $table->index(['platform_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
```

### 3.2 Model

**Arquivo**: `app/Models/Booking.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    protected $fillable = [
        'place_id',
        'platform_id',
        'external_id',
        'guest_name',
        'check_in',
        'check_out',
        'status',
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function accessCode(): HasOne
    {
        return $this->hasOne(AccessCode::class);
    }
}
```

### 3.3 Observer

**Arquivo**: `app/Observers/BookingObserver.php`

Ver documento `REFACTOR_PLAN_04_BOOKINGS.md` para detalhes.

---

## 4. CRIAR MODELO PLATFORM

### 4.1 Migration

**Arquivo**: `database/migrations/XXXX_XX_XX_XXXXXX_create_platforms_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['airbnb', 'booking_com', 'other'])->default('other');
            $table->string('ical_url')->nullable();
            $table->integer('refresh_rate')->nullable(); // em minutos
            $table->timestamp('last_sync')->nullable();
            $table->json('credentials')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platforms');
    }
};
```

### 4.2 Model

**Arquivo**: `app/Models/Platform.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'ical_url',
        'refresh_rate',
        'last_sync',
        'credentials',
    ];

    protected $casts = [
        'last_sync' => 'datetime',
        'credentials' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
```

---

## 5. ATUALIZAR MODELO DEVICE

### 5.1 Migration

**Arquivo**: `database/migrations/XXXX_XX_XX_XXXXXX_add_fields_to_devices_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('place_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();

            $table->string('integration_type')
                ->default('portatec')
                ->after('place_id');

            $table->string('functional_type')
                ->nullable()
                ->after('integration_type');

            $table->string('default_pin', 6)
                ->nullable()
                ->after('functional_type');

            $table->string('tuya_device_id')
                ->nullable()
                ->after('default_pin');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['place_id']);
            $table->dropColumn([
                'place_id',
                'integration_type',
                'functional_type',
                'default_pin',
                'tuya_device_id',
            ]);
        });
    }
};
```

### 5.2 Criar Enums

**Arquivo**: `app/Enums/DeviceIntegrationTypeEnum.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum DeviceIntegrationTypeEnum: string
{
    case Portatec = 'portatec';
    case Tuya = 'tuya';
}
```

**Arquivo**: `app/Enums/DeviceFunctionalTypeEnum.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum DeviceFunctionalTypeEnum: string
{
    case Pulse = 'pulse';
    case Sensor = 'sensor';
}
```

### 5.3 Atualizar Model Device

```php
use App\Enums\DeviceIntegrationTypeEnum;
use App\Enums\DeviceFunctionalTypeEnum;

protected $fillable = [
    // ... campos existentes
    'place_id',
    'integration_type',
    'functional_type',
    'default_pin',
    'tuya_device_id',
];

protected $casts = [
    'integration_type' => DeviceIntegrationTypeEnum::class,
    'functional_type' => DeviceFunctionalTypeEnum::class,
];

public function place(): BelongsTo
{
    return $this->belongsTo(Place::class);
}
```

---

## 6. ATUALIZAR MODELO PLACE

### 6.1 Adicionar Relacionamentos

```php
public function devices(): HasMany
{
    return $this->hasMany(Device::class);
}

public function accessCodes(): HasMany
{
    return $this->hasMany(AccessCode::class);
}

public function bookings(): HasMany
{
    return $this->hasMany(Booking::class);
}

public function getValidAccessCodes()
{
    return $this->accessCodes()
        ->where('start', '<=', now())
        ->where('end', '>=', now())
        ->get();
}
```

---

## 7. ATUALIZAR MODELO USER

### 7.1 Adicionar Relacionamento

```php
public function platforms(): HasMany
{
    return $this->hasMany(Platform::class);
}
```

---

## 8. ATUALIZAR PLACEROLEENUM

### 8.1 Adicionar Casos

**Arquivo**: `app/Enums/PlaceRoleEnum.php`

```php
enum PlaceRoleEnum: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Host = 'host';
    case Viewer = 'viewer'; // Futuro
}
```

**Nota**: Verificar se há mapeamento necessário com valores antigos.

---

## 9. CHECKLIST DE IMPLEMENTAÇÃO

### AccessPin → AccessCode
- [ ] Criar migration para renomear tabela
- [ ] Renomear model AccessPin → AccessCode
- [ ] Renomear observer
- [ ] Renomear event
- [ ] Renomear policy
- [ ] Renomear resource Filament
- [ ] Buscar e atualizar todas as referências
- [ ] Atualizar AppServiceProvider
- [ ] Testar migração

### Booking
- [ ] Criar migration
- [ ] Criar model Booking
- [ ] Criar observer BookingObserver
- [ ] Adicionar relacionamentos
- [ ] Criar factory (opcional)
- [ ] Criar seeder (opcional)

### Platform
- [ ] Criar migration
- [ ] Criar model Platform
- [ ] Adicionar relacionamentos
- [ ] Criar factory (opcional)
- [ ] Criar seeder (opcional)

### Device
- [ ] Criar migration para novos campos
- [ ] Criar DeviceIntegrationTypeEnum
- [ ] Criar DeviceFunctionalTypeEnum
- [ ] Atualizar model Device
- [ ] Adicionar relacionamento place()
- [ ] Atualizar fillable e casts

### Place
- [ ] Adicionar relacionamento devices()
- [ ] Adicionar relacionamento accessCodes()
- [ ] Adicionar relacionamento bookings()
- [ ] Adicionar método getValidAccessCodes()

### User
- [ ] Adicionar relacionamento platforms()

### PlaceRoleEnum
- [ ] Adicionar caso Owner
- [ ] Adicionar caso Viewer
- [ ] Verificar mapeamento com valores antigos
- [ ] Atualizar policies que usam o enum

---

## 10. ORDEM DE EXECUÇÃO DAS MIGRATIONS

1. Renomear `access_pins` → `access_codes`
2. Adicionar `booking_id` em `access_codes`
3. Criar tabela `platforms`
4. Criar tabela `bookings`
5. Adicionar campos em `devices` (place_id, integration_type, etc.)

**Importante**: Executar migrations em ordem e testar cada uma antes de prosseguir.
