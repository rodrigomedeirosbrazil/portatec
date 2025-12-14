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
            $table->foreignId('integration_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->datetime('check_in');
            $table->datetime('check_out');
            $table->string('external_id')->nullable(); // ID do evento no iCal para rastreamento
            $table->string('deletion_reason')->nullable(); // Motivo da remoção (soft delete)
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['place_id', 'check_in', 'check_out']);
            $table->index(['integration_id']);
            $table->index(['external_id', 'integration_id']);
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
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\BookingDeletionReasonEnum;

class Booking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'place_id',
        'integration_id',
        'guest_name',
        'check_in',
        'check_out',
        'external_id',
        'deletion_reason',
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'deletion_reason' => BookingDeletionReasonEnum::class,
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
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
            $table->string('name');
            $table->string('slug')->unique();
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
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class);
    }
}
```

---

## 5. CRIAR MODELO INTEGRATION

### 5.1 Migration

**Arquivo**: `database/migrations/XXXX_XX_XX_XXXXXX_create_integrations_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['platform_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
```

### 5.2 Model

**Arquivo**: `app/Models/Integration.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Integration extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'platform_id',
        'user_id',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function places(): BelongsToMany
    {
        return $this->belongsToMany(Place::class, 'place_integration')
            ->withPivot('external_id')
            ->withTimestamps();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
```

---

## 5.3 CRIAR TABELA DE RELACIONAMENTO PLACE_INTEGRATION

### 5.3.1 Migration

**Arquivo**: `database/migrations/XXXX_XX_XX_XXXXXX_create_place_integration_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_integration', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('external_id'); // URL completa do iCal ou ID da API
            $table->timestamps();

            // Índices
            $table->unique(['place_id', 'integration_id']);
            $table->index(['external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_integration');
    }
};
```

### 5.3.2 Nota sobre o Campo `external_id`

**Importante**: O campo `external_id` na tabela `place_integration` armazena:
- **Para iCal**: A URL completa do iCal quando a sincronização for feita via iCal. Cada iCal recebe um calendário de um lugar/place/propriedade específico.
- **Para APIs**: O ID específico da plataforma quando houver integração por API (ex: Airbnb API, Booking.com API)

Esta estrutura permite que:
- Uma Integration possa estar associada a múltiplos Places (cada um com seu próprio `external_id`)
- Um Place possa ter múltiplas Integrations (ex: Airbnb e Booking.com ao mesmo tempo)
- Cada relacionamento Place-Integration tenha seu próprio identificador externo (URL do iCal ou ID da API)

---

## 5. ATUALIZAR MODELO DEVICE

### 5.1 Nota Importante sobre Device e DeviceFunction

**Estrutura Atual**:
- `Device`: Representa um dispositivo físico
- `DeviceFunction`: Representa uma função do dispositivo (Button ou Sensor)
- Um `Device` pode ter **múltiplas** `DeviceFunction` (ex: um dispositivo pode ter Button E Sensor ao mesmo tempo)
- O tipo funcional (Button/Sensor) é definido em `DeviceFunction.type`, não no `Device`
- `external_device_id`: Campo único usado para identificar dispositivos externamente, seja Portatec ou Tuya (não há campo separado `tuya_device_id`)

### 5.2 Migration para Renomear chip_id

**Arquivo**: `database/migrations/XXXX_XX_XX_XXXXXX_rename_chip_id_to_external_device_id_in_devices_table.php`

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
            $table->renameColumn('chip_id', 'external_device_id');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->renameColumn('external_device_id', 'chip_id');
        });
    }
};
```

### 5.3 Migration para Adicionar Novos Campos

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

            $table->string('brand')
                ->default('portatec')
                ->after('place_id');

            $table->string('default_pin', 6)
                ->nullable()
                ->after('brand');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['place_id']);
            $table->dropColumn([
                'place_id',
                'brand',
                'default_pin',
            ]);
        });
    }
};
```

**Nota**: Não adicionamos `functional_type` no Device porque:
- O tipo funcional (Button/Sensor) é definido em `DeviceFunction.type`
- Um Device pode ter múltiplas funções (Button E Sensor)
- Cada `DeviceFunction` tem seu próprio `type` e `pin`

### 5.4 Criar Enum

**Arquivo**: `app/Enums/DeviceBrandEnum.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum DeviceBrandEnum: string
{
    case Portatec = 'portatec';
    case Tuya = 'tuya';
}
```

**Nota**: Não precisamos de `DeviceFunctionalTypeEnum` porque o tipo funcional já existe em `DeviceTypeEnum` (Button, Sensor) usado em `DeviceFunction`.

### 5.5 Atualizar Model Device

```php
use App\Enums\DeviceBrandEnum;

protected $fillable = [
    'name',
    'external_device_id', // ID externo do dispositivo (Portatec ou Tuya)
    'place_id',
    'brand',
    'default_pin',
    'last_sync',
];

protected $casts = [
    'brand' => DeviceBrandEnum::class,
    'last_sync' => 'datetime',
];

public function place(): BelongsTo
{
    return $this->belongsTo(Place::class);
}

public function deviceFunctions(): HasMany
{
    return $this->hasMany(DeviceFunction::class);
}

// Método helper para obter funções por tipo
public function getFunctionByType(DeviceTypeEnum $type): ?DeviceFunction
{
    return $this->deviceFunctions()->where('type', $type)->first();
}
```

### 5.6 Atualizar DeviceFunction

**Nota**: `DeviceFunction` já existe e está correta. Apenas garantir que:
- `type` use `DeviceTypeEnum` (Button, Sensor)
- Um Device pode ter múltiplas DeviceFunctions
- Cada DeviceFunction tem seu próprio `pin` e `status`

---

## 7. ATUALIZAR MODELO PLACE

### 7.1 Adicionar Relacionamentos

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

public function integrations(): BelongsToMany
{
    return $this->belongsToMany(Integration::class, 'place_integration')
        ->withPivot('external_id')
        ->withTimestamps();
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

## 8. ATUALIZAR MODELO USER

### 8.1 Adicionar Relacionamento

```php
public function integrations(): HasMany
{
    return $this->hasMany(Integration::class);
}
```

---

## 9. CRIAR ENUM BOOKINGDELETIONREASONENUM

### 9.1 Arquivo

**Arquivo**: `app/Enums/BookingDeletionReasonEnum.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum BookingDeletionReasonEnum: string
{
    case ChangeDate = 'change_date';
    case Canceled = 'canceled';
    case CanceledByUser = 'canceled_by_user';
    case ChangeGuest = 'change_guest';
    case Other = 'other';
}
```

**Uso**: Este enum é usado no campo `deletion_reason` da tabela `bookings` para rastrear o motivo da remoção (soft delete) de um booking durante a sincronização.

---

## 10. ATUALIZAR PLACEROLEENUM

### 10.1 Adicionar Casos

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

## 11. CHECKLIST DE IMPLEMENTAÇÃO

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
- [ ] Criar migration (integration_id, external_id, deletion_reason, soft deletes)
- [ ] Criar model Booking com SoftDeletes
- [ ] Criar enum BookingDeletionReasonEnum
- [ ] Criar observer BookingObserver
- [ ] Adicionar relacionamento com Integration (não Platform)
- [ ] Criar factory (opcional)
- [ ] Criar seeder (opcional)

### Platform
- [ ] Criar migration (apenas id, name, slug)
- [ ] Criar model Platform
- [ ] Adicionar relacionamento com Integration
- [ ] Criar factory (opcional)
- [ ] Criar seeder (opcional)

### Integration
- [ ] Criar migration (platform_id, user_id, soft deletes - sem external_id)
- [ ] Criar model Integration
- [ ] Adicionar relacionamentos (Platform, User, Places, Bookings)
- [ ] Criar factory (opcional)
- [ ] Criar seeder (opcional)

### Place Integration (Tabela Pivot)
- [ ] Criar migration place_integration (place_id, integration_id, external_id)
- [ ] Adicionar relacionamento many-to-many em Place
- [ ] Adicionar relacionamento many-to-many em Integration

### Device
- [ ] Criar migration para renomear chip_id → external_device_id
- [ ] Criar migration para novos campos (place_id, brand, default_pin)
- [ ] Criar DeviceBrandEnum
- [ ] Atualizar model Device
- [ ] Adicionar relacionamento place()
- [ ] Adicionar relacionamento deviceFunctions()
- [ ] Atualizar fillable e casts
- [ ] Atualizar todas as referências a chip_id para external_device_id
- [ ] Remover referências a tuya_device_id (usar external_device_id para todos)

### DeviceFunction
- [ ] Verificar que DeviceFunction.type usa DeviceTypeEnum (Button, Sensor)
- [ ] Garantir que um Device pode ter múltiplas DeviceFunctions
- [ ] Atualizar documentação se necessário

### Place
- [ ] Adicionar relacionamento devices()
- [ ] Adicionar relacionamento accessCodes()
- [ ] Adicionar relacionamento bookings()
- [ ] Adicionar método getValidAccessCodes()

### User
- [ ] Adicionar relacionamento integrations() (remover platforms() se existir)

### PlaceRoleEnum
- [ ] Adicionar caso Owner
- [ ] Adicionar caso Viewer
- [ ] Verificar mapeamento com valores antigos
- [ ] Atualizar policies que usam o enum

---

## 12. ORDEM DE EXECUÇÃO DAS MIGRATIONS

1. Renomear `access_pins` → `access_codes`
2. Adicionar `booking_id` em `access_codes`
3. Criar tabela `platforms` (id, name, slug)
4. Criar tabela `integrations` (platform_id, user_id, soft deletes - sem external_id)
5. Criar tabela `place_integration` (place_id, integration_id, external_id)
6. Criar tabela `bookings` (place_id, integration_id, guest_name nullable, check_in, check_out, external_id, deletion_reason, soft deletes)
7. Renomear `chip_id` → `external_device_id` em `devices`
8. Adicionar campos em `devices` (place_id, brand, default_pin - sem tuya_device_id, sem functional_type)

**Importante**: Executar migrations em ordem e testar cada uma antes de prosseguir.
