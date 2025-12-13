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
            $table->timestamps();

            // Índices
            $table->index(['place_id', 'check_in', 'check_out']);
            $table->index(['integration_id']);
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
        'integration_id',
        'guest_name',
        'check_in',
        'check_out',
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
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

## 9. ATUALIZAR PLACEROLEENUM

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

## 10. CHECKLIST DE IMPLEMENTAÇÃO

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
- [ ] Criar migration (sem external_id, com integration_id)
- [ ] Criar model Booking
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
- [ ] Adicionar relacionamento integrations() (remover platforms() se existir)

### PlaceRoleEnum
- [ ] Adicionar caso Owner
- [ ] Adicionar caso Viewer
- [ ] Verificar mapeamento com valores antigos
- [ ] Atualizar policies que usam o enum

---

## 11. ORDEM DE EXECUÇÃO DAS MIGRATIONS

1. Renomear `access_pins` → `access_codes`
2. Adicionar `booking_id` em `access_codes`
3. Criar tabela `platforms` (id, name, slug)
4. Criar tabela `integrations` (platform_id, user_id, soft deletes - sem external_id)
5. Criar tabela `place_integration` (place_id, integration_id, external_id)
6. Criar tabela `bookings` (place_id, integration_id, guest_name nullable, check_in, check_out - sem status)
7. Adicionar campos em `devices` (place_id, integration_type, etc.)

**Importante**: Executar migrations em ordem e testar cada uma antes de prosseguir.
