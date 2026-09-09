# Permissão de dispositivo — plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Trocar a autorização de dispositivo, hoje derivada do vínculo com o local, por uma permissão explícita usuário → dispositivo, com admin, concessão e transferência.

**Architecture:** A tabela `device_user` (que já existe) ganha um papel `admin`/`user` e passa a ser a fonte da verdade sobre quem manda em cada dispositivo. `DevicePolicy` é reescrita sobre ela; `PlacePolicy::update()` vira exclusiva do admin do local; anexar/desanexar deixa de ser efeito colateral da edição do dispositivo e vira ação própria. A unicidade de PIN muda de "por local" para "sem sobreposição de janela no mesmo dispositivo", e o evento de acesso passa a ser resolvido por PIN + instante. Clonar local e `place_device_functions` são removidos.

**Tech Stack:** Laravel 11, PHP 8.4, Inertia + React 19, Tailwind 4, PHPUnit 11, SQLite em teste. Todo comando roda via `./vendor/bin/sail`.

**Spec:** [`docs/specs/2026-09-08-permissao-de-dispositivo-design.md`](../specs/2026-09-08-permissao-de-dispositivo-design.md)

---

## Como este plano é executado

As tarefas estão agrupadas em fases. **Dentro de uma fase, as trilhas rodam em paralelo**; entre fases, é sequencial. Cada trilha é sequencial internamente.

```
FASE 0 — Fundação
  ├── Trilha M: Task 0.1 (migração + models)
  └── Trilha T: Task 0.2 (traduções)

FASE 1 — Núcleo (3 trilhas em paralelo)
  ├── Trilha PIN:      Task 1.1 → Task 1.2
  ├── Trilha DEVICE:   Task 1.3
  └── Trilha PLACE:    Task 1.4 → Task 1.5

FASE 2 — Ações (2 trilhas em paralelo)
  ├── Trilha ATTACH:   Task 2.1
  └── Trilha GRANT:    Task 2.2

FASE 3 — Cascata e visibilidade (2 trilhas em paralelo)
  ├── Trilha CASCATA:  Task 3.1
  └── Trilha VIEW:     Task 3.2

FASE 4 — Limpeza final
  └── Task 4.1
```

### Arquivos compartilhados — leia antes de editar

Três arquivos são tocados por mais de uma trilha e são a única fonte real de conflito:

| Arquivo | Quem escreve | Regra |
|---|---|---|
| `resources/lang/pt_BR/app.php` | **só a Task 0.2** | Todas as chaves entram de uma vez na fundação. Nenhuma outra tarefa adiciona chave — elas só consomem. É o padrão que o repo já usa (ver o comentário no topo de `tests/Unit/TranslationKeysTest.php`). |
| `routes/web.php` | Task 1.5 (remove) e Task 2.2 (adiciona) | Estão em fases diferentes, então não colidem. Ainda assim, releia o arquivo antes de editar. |
| `tests/Feature/Places/PlacesTest.php` | Task 1.4 e Task 1.5 | Ambas na Trilha PLACE, sequenciais. Nunca coloque uma delas em outra trilha. |

### Antes de qualquer tarefa

```bash
./vendor/bin/sail up -d
```

Ao fim de **toda** tarefa, antes do commit:

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test
```

Nunca marque uma tarefa como pronta sem ver a saída verde. É a regra 10.7 do `AGENTS.md`.

---

# FASE 0 — Fundação

Bloqueia todas as outras fases. As duas tarefas são independentes entre si e podem rodar em paralelo.

---

## Trilha M

### Task 0.1: Papel em `device_user`

**Files:**
- Create: `app/Enums/DeviceRoleEnum.php`
- Create: `database/migrations/2026_09_08_000001_add_role_to_device_user_table.php`
- Modify: `app/Models/DeviceUser.php`
- Modify: `app/Models/Device.php` (adicionar métodos após `deviceUsers()`, linha 122)
- Test: `tests/Feature/Devices/DeviceAdminBackfillTest.php`

O enum vive em `app/Enums` com o trait `Valuable`, como `PlaceRoleEnum`. O `label()` usa chaves de tradução que a Task 0.2 cadastra — as duas tarefas rodam em paralelo e se encontram no `sail test` do fim.

- [ ] **Step 1: Criar o enum**

`app/Enums/DeviceRoleEnum.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\Valuable;

enum DeviceRoleEnum: string
{
    use Valuable;

    case Admin = 'admin';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('app.device_roles.admin'),
            self::User => __('app.device_roles.user'),
        };
    }

    public static function toArray(): array
    {
        return [
            self::Admin->value => __('app.device_roles.admin'),
            self::User->value => __('app.device_roles.user'),
        ];
    }
}
```

- [ ] **Step 2: Escrever o teste de backfill (vai falhar)**

`tests/Feature/Devices/DeviceAdminBackfillTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Devices;

use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * O backfill roda dentro da migração, então estes testes reproduzem a regra
 * chamando os mesmos passos sobre dados criados depois do `migrate`. O que
 * está sob teste é a REGRA de resolução do admin, não o `artisan migrate`.
 */
class DeviceAdminBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_with_existing_link_keeps_oldest_user_as_admin(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $device = Device::withoutEvents(fn (): Device => Device::create([
            'name' => 'Portão',
            'brand' => 'tuya',
        ]));

        $device->deviceUsers()->create(['user_id' => $first->id]);
        $device->deviceUsers()->create(['user_id' => $second->id]);

        $this->runBackfill();

        $this->assertSame('admin', DB::table('device_user')
            ->where('device_id', $device->id)->where('user_id', $first->id)->value('role'));
        $this->assertSame('user', DB::table('device_user')
            ->where('device_id', $device->id)->where('user_id', $second->id)->value('role'));
    }

    public function test_device_without_link_takes_oldest_place_admin(): void
    {
        $admin = User::factory()->create();
        $host = User::factory()->create();
        $place = Place::create(['name' => 'Condomínio']);

        PlaceUser::create(['place_id' => $place->id, 'user_id' => $host->id, 'role' => 'host']);
        PlaceUser::create(['place_id' => $place->id, 'user_id' => $admin->id, 'role' => 'admin']);

        $device = Device::withoutEvents(fn (): Device => Device::create([
            'name' => 'Portão',
            'brand' => 'portatec',
            'place_id' => $place->id,
        ]));

        $this->runBackfill();

        $this->assertSame('admin', DB::table('device_user')
            ->where('device_id', $device->id)->where('user_id', $admin->id)->value('role'));
    }

    public function test_device_without_place_stays_without_admin(): void
    {
        $device = Device::withoutEvents(fn (): Device => Device::create([
            'name' => 'Órfão',
            'brand' => 'portatec',
        ]));

        $this->runBackfill();

        $this->assertSame(0, DB::table('device_user')->where('device_id', $device->id)->count());
    }

    private function runBackfill(): void
    {
        require_once database_path('migrations/2026_09_08_000001_add_role_to_device_user_table.php');

        $migration = include database_path('migrations/2026_09_08_000001_add_role_to_device_user_table.php');

        (fn () => $this->backfill())->call($migration);
    }
}
```

- [ ] **Step 3: Rodar e ver falhar**

```bash
./vendor/bin/sail test --filter=DeviceAdminBackfillTest
```

Esperado: FAIL — o arquivo de migração ainda não existe.

- [ ] **Step 4: Criar a migração**

`database/migrations/2026_09_08_000001_add_role_to_device_user_table.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\DeviceRoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_user', function (Blueprint $table): void {
            $table->enum('role', DeviceRoleEnum::values())
                ->default(DeviceRoleEnum::User->value)
                ->after('user_id');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('device_user', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }

    /**
     * Regra do spec §4: (1) quem já está vinculado, o mais antigo vira admin;
     * (2) senão, o admin mais antigo do local primário; (3) senão, sem admin.
     * Dispositivo sem admin é estado inerte — ninguém edita, concede ou anexa.
     */
    private function backfill(): void
    {
        $oldestPerDevice = DB::table('device_user')
            ->selectRaw('MIN(id) as id')
            ->groupBy('device_id')
            ->pluck('id');

        DB::table('device_user')
            ->whereIn('id', $oldestPerDevice)
            ->update(['role' => DeviceRoleEnum::Admin->value]);

        $devicesWithoutLink = DB::table('devices')
            ->whereNotExists(function ($query): void {
                $query->selectRaw(1)
                    ->from('device_user')
                    ->whereColumn('device_user.device_id', 'devices.id');
            })
            ->get(['id', 'place_id']);

        foreach ($devicesWithoutLink as $device) {
            $placeId = $device->place_id ?? DB::table('device_place')
                ->where('device_id', $device->id)
                ->orderBy('id')
                ->value('place_id');

            if ($placeId === null) {
                continue;
            }

            $userId = DB::table('place_users')
                ->where('place_id', $placeId)
                ->where('role', 'admin')
                ->orderBy('id')
                ->value('user_id');

            if ($userId === null) {
                continue;
            }

            DB::table('device_user')->insert([
                'device_id' => $device->id,
                'user_id' => $userId,
                'role' => DeviceRoleEnum::Admin->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
```

- [ ] **Step 5: Rodar e ver passar**

```bash
./vendor/bin/sail test --filter=DeviceAdminBackfillTest
```

Esperado: PASS, 3 testes.

- [ ] **Step 6: Atualizar `DeviceUser`**

`app/Models/DeviceUser.php` — substituir o corpo por:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeviceRoleEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceUser extends Model
{
    protected $table = 'device_user';

    protected $fillable = [
        'device_id',
        'user_id',
        'role',
    ];

    protected $casts = [
        'role' => DeviceRoleEnum::class,
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 7: Adicionar os métodos de consulta em `Device`**

Em `app/Models/Device.php`, logo depois de `deviceUsers()` (linha 122), adicionar. `use App\Enums\DeviceRoleEnum;` no topo:

```php
    /** O dono do dispositivo. `null` em dispositivo órfão — ver spec §4. */
    public function adminUser(): ?User
    {
        return $this->deviceUsers()
            ->where('role', DeviceRoleEnum::Admin)
            ->first()?->user;
    }

    public function isAdministeredBy(User $user): bool
    {
        return $this->deviceUsers()
            ->where('user_id', $user->id)
            ->where('role', DeviceRoleEnum::Admin)
            ->exists();
    }

    /** Admin conta como quem pode usar: quem manda também usa. */
    public function isUsableBy(User $user): bool
    {
        return $this->deviceUsers()
            ->where('user_id', $user->id)
            ->exists();
    }
```

- [ ] **Step 8: Rodar a suíte inteira**

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test
```

Esperado: verde. Nada ainda consome o papel novo, então nenhum teste existente deve mudar de comportamento.

- [ ] **Step 9: Commit**

```bash
git add app/Enums/DeviceRoleEnum.php database/migrations/2026_09_08_000001_add_role_to_device_user_table.php app/Models/DeviceUser.php app/Models/Device.php tests/Feature/Devices/DeviceAdminBackfillTest.php
git commit -m "feat: adiciona papel de admin e concessao em device_user"
```

---

## Trilha T

### Task 0.2: Chaves de tradução

**Files:**
- Modify: `resources/lang/pt_BR/app.php`
- Modify: `tests/Unit/TranslationKeysTest.php`

Todas as chaves da feature entram aqui de uma vez, para que as tarefas seguintes só consumam. Nenhuma outra tarefa deste plano edita o arquivo de tradução.

- [ ] **Step 1: Escrever o teste de contrato (vai falhar)**

Em `tests/Unit/TranslationKeysTest.php`, adicionar o método:

```php
    public function test_device_permission_keys_exist(): void
    {
        $keys = [
            'device_roles.admin',
            'device_roles.user',
            'device_permissions_title',
            'device_permissions_heading',
            'device_permissions_empty',
            'device_permission_grant',
            'device_permission_granted',
            'device_permission_revoke',
            'device_permission_revoked',
            'device_permission_revoke_confirm',
            'device_admin_heading',
            'device_admin_none',
            'device_transfer',
            'device_transfer_confirm',
            'device_transferred',
            'device_codes_heading',
            'device_codes_empty',
            'device_codes_origin_place',
            'device_codes_window',
            'user_email_label',
            'user_email_placeholder',
            'user_email_not_found',
            'access_code_pin_conflict',
        ];

        foreach ($keys as $key) {
            $this->assertNotSame(
                "app.{$key}",
                trans("app.{$key}"),
                "A chave de tradução [app.{$key}] não existe."
            );
        }
    }
```

- [ ] **Step 2: Rodar e ver falhar**

```bash
./vendor/bin/sail test --filter=TranslationKeysTest
```

Esperado: FAIL na primeira chave, `app.device_roles.admin`.

- [ ] **Step 3: Cadastrar as chaves**

Em `resources/lang/pt_BR/app.php`, adicionar ao array (mantenha junto do bloco `place_roles`, que fica na linha 144):

```php
    'device_roles' => [
        'admin' => 'Administrador',
        'user' => 'Uso',
    ],

    'device_permissions_title' => 'Permissões do dispositivo',
    'device_permissions_heading' => 'Quem pode usar',
    'device_permissions_empty' => 'Ninguém além do administrador tem acesso a este dispositivo.',
    'device_permission_grant' => 'Conceder acesso',
    'device_permission_granted' => 'Acesso concedido.',
    'device_permission_revoke' => 'Revogar',
    'device_permission_revoked' => 'Acesso revogado. O dispositivo saiu dos locais dessa pessoa e os códigos de acesso originados neles foram apagados do equipamento.',
    'device_permission_revoke_confirm' => 'Revogar o acesso de :name? Hóspedes com estadia em andamento perdem o acesso a este dispositivo imediatamente.',
    'device_admin_heading' => 'Administrador do dispositivo',
    'device_admin_none' => 'Este dispositivo está sem administrador. Fale com o suporte.',
    'device_transfer' => 'Transferir administração',
    'device_transfer_confirm' => 'Transferir a administração deste dispositivo para :name (:email)? Você continua podendo acioná-lo, mas perde a configuração e não desfaz essa ação sozinho.',
    'device_transferred' => 'Administração transferida para :name.',
    'device_codes_heading' => 'Códigos gravados neste dispositivo',
    'device_codes_empty' => 'Nenhum código de acesso ativo neste dispositivo.',
    'device_codes_origin_place' => 'Local de origem',
    'device_codes_window' => 'Validade',
    'user_email_label' => 'E-mail da pessoa',
    'user_email_placeholder' => 'nome@exemplo.com',
    'user_email_not_found' => 'Nenhuma conta encontrada com esse e-mail.',
    'access_code_pin_conflict' => 'Este PIN já está em uso em um dos dispositivos deste local, num período que se sobrepõe.',
```

- [ ] **Step 4: Rodar e ver passar**

```bash
./vendor/bin/sail test --filter=TranslationKeysTest
```

Esperado: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/lang/pt_BR/app.php tests/Unit/TranslationKeysTest.php
git commit -m "feat: cadastra chaves de traducao da permissao de dispositivo"
```

---

# FASE 1 — Núcleo

Três trilhas em paralelo. Depende da Fase 0 inteira.

---

## Trilha PIN

### Task 1.1: Unicidade de PIN por dispositivo e janela

**Files:**
- Create: `app/Services/AccessCode/AccessCodeConflictChecker.php`
- Create: `app/Rules/AccessCodePinAvailableRule.php`
- Modify: `app/Services/AccessCode/AccessCodeGeneratorService.php`
- Modify: `app/Http/Requests/StoreAccessCodeRequest.php`
- Modify: `app/Http/Requests/UpdateAccessCodeRequest.php`
- Modify: `app/Http/Controllers/App/AccessCodeController.php:167` (`update()`)
- Test: `tests/Unit/AccessCodeConflictCheckerTest.php`
- Test: `tests/Unit/AccessCodeGeneratorServiceTest.php`

A regra do spec §7: o PIN não pode coincidir com outro PIN igual, de qualquer local, que caia num dispositivo em comum, com janela sobreposta. `end = null` vale infinito.

- [ ] **Step 1: Escrever o teste do checker (vai falhar)**

`tests/Unit/AccessCodeConflictCheckerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\AccessCode;
use App\Models\Device;
use App\Models\Place;
use App\Services\AccessCode\AccessCodeConflictChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AccessCodeConflictCheckerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);
    }

    public function test_detects_conflict_across_places_sharing_a_device(): void
    {
        [$gate, $apto1, $apto2] = $this->sharedGate();

        AccessCode::withoutEvents(fn () => AccessCode::create([
            'place_id' => $apto1->id,
            'pin' => '123456',
            'start' => Carbon::parse('2026-10-01 12:00'),
            'end' => Carbon::parse('2026-10-05 12:00'),
        ]));

        $checker = app(AccessCodeConflictChecker::class);

        $this->assertTrue($checker->conflicts(
            $apto2->id,
            '123456',
            Carbon::parse('2026-10-04 12:00'),
            Carbon::parse('2026-10-08 12:00'),
        ));

        $this->assertTrue($gate->places()->where('places.id', $apto2->id)->exists());
    }

    public function test_allows_same_pin_when_windows_do_not_overlap(): void
    {
        [, $apto1, $apto2] = $this->sharedGate();

        AccessCode::withoutEvents(fn () => AccessCode::create([
            'place_id' => $apto1->id,
            'pin' => '123456',
            'start' => Carbon::parse('2026-10-01 12:00'),
            'end' => Carbon::parse('2026-10-05 12:00'),
        ]));

        $checker = app(AccessCodeConflictChecker::class);

        $this->assertFalse($checker->conflicts(
            $apto2->id,
            '123456',
            Carbon::parse('2026-10-05 12:01'),
            Carbon::parse('2026-10-08 12:00'),
        ));
    }

    public function test_open_ended_code_blocks_the_pin_forever(): void
    {
        [, $apto1, $apto2] = $this->sharedGate();

        AccessCode::withoutEvents(fn () => AccessCode::create([
            'place_id' => $apto1->id,
            'pin' => '123456',
            'start' => Carbon::parse('2026-01-01 00:00'),
            'end' => null,
        ]));

        $checker = app(AccessCodeConflictChecker::class);

        $this->assertTrue($checker->conflicts(
            $apto2->id,
            '123456',
            Carbon::parse('2030-01-01 00:00'),
            null,
        ));
    }

    public function test_ignores_places_that_share_no_device(): void
    {
        [, $apto1] = $this->sharedGate();
        $unrelated = Place::create(['name' => 'Outro condomínio']);

        AccessCode::withoutEvents(fn () => AccessCode::create([
            'place_id' => $apto1->id,
            'pin' => '123456',
            'start' => Carbon::parse('2026-10-01 12:00'),
            'end' => Carbon::parse('2026-10-05 12:00'),
        ]));

        $checker = app(AccessCodeConflictChecker::class);

        $this->assertFalse($checker->conflicts(
            $unrelated->id,
            '123456',
            Carbon::parse('2026-10-02 12:00'),
            Carbon::parse('2026-10-03 12:00'),
        ));
    }

    public function test_ignores_the_code_being_edited(): void
    {
        [, $apto1] = $this->sharedGate();

        $code = AccessCode::withoutEvents(fn () => AccessCode::create([
            'place_id' => $apto1->id,
            'pin' => '123456',
            'start' => Carbon::parse('2026-10-01 12:00'),
            'end' => Carbon::parse('2026-10-05 12:00'),
        ]));

        $checker = app(AccessCodeConflictChecker::class);

        $this->assertFalse($checker->conflicts(
            $apto1->id,
            '123456',
            Carbon::parse('2026-10-01 12:00'),
            Carbon::parse('2026-10-06 12:00'),
            $code->id,
        ));
    }

    /** @return array{0: Device, 1: Place, 2: Place} */
    private function sharedGate(): array
    {
        $apto1 = Place::create(['name' => 'Apto 1']);
        $apto2 = Place::create(['name' => 'Apto 2']);

        $gate = Device::withoutEvents(fn (): Device => Device::create([
            'name' => 'Garagem A',
            'brand' => 'portatec',
            'external_device_id' => 'chip-a',
        ]));

        $gate->places()->attach([$apto1->id, $apto2->id]);

        return [$gate, $apto1, $apto2];
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

```bash
./vendor/bin/sail test --filter=AccessCodeConflictCheckerTest
```

Esperado: FAIL — `Target class [App\Services\AccessCode\AccessCodeConflictChecker] does not exist`.

- [ ] **Step 3: Implementar o checker**

`app/Services/AccessCode/AccessCodeConflictChecker.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\AccessCode;

use App\Models\AccessCode;
use App\Models\Device;
use App\Models\Place;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Spec §7. A unicidade do PIN não é do local, é do EQUIPAMENTO: dois locais
 * que compartilham o mesmo portão gravam os PINs deles na mesma fechadura, e
 * lá dentro dois códigos iguais e simultâneos são indistinguíveis.
 *
 * `end = null` é código permanente e vale infinito — ele ocupa aquele PIN
 * naquele dispositivo para sempre.
 */
class AccessCodeConflictChecker
{
    public function conflicts(
        int $placeId,
        string $pin,
        CarbonInterface $start,
        ?CarbonInterface $end,
        ?int $ignoreAccessCodeId = null,
    ): bool {
        $placeIds = $this->placesSharingDevicesWith($placeId);

        if ($placeIds === []) {
            return false;
        }

        return AccessCode::query()
            ->whereIn('place_id', $placeIds)
            ->where('pin', $pin)
            ->when($ignoreAccessCodeId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreAccessCodeId))
            ->where(function (Builder $query) use ($end): void {
                if ($end === null) {
                    return;
                }

                $query->where('start', '<=', $end);
            })
            ->where(function (Builder $query) use ($start): void {
                $query->whereNull('end')->orWhere('end', '>=', $start);
            })
            ->exists();
    }

    /**
     * Locais que dividem ao menos um dispositivo com o local dado — incluindo
     * ele próprio. Só dispositivos que de fato recebem PIN entram na conta.
     *
     * @return array<int, int>
     */
    private function placesSharingDevicesWith(int $placeId): array
    {
        $place = Place::query()->with('devices.places')->find($placeId);

        if ($place === null) {
            return [];
        }

        return $place->devices
            ->filter(fn (Device $device): bool => $device->supportsPlaceAccessCodes())
            ->flatMap(fn (Device $device) => $device->places->pluck('id'))
            ->push($placeId)
            ->unique()
            ->values()
            ->all();
    }
}
```

- [ ] **Step 4: Rodar e ver passar**

```bash
./vendor/bin/sail test --filter=AccessCodeConflictCheckerTest
```

Esperado: PASS, 5 testes.

- [ ] **Step 5: Escrever o teste do gerador (vai falhar)**

Em `tests/Unit/AccessCodeGeneratorServiceTest.php`, adicionar:

```php
    public function test_generated_pin_avoids_conflict_on_a_shared_device(): void
    {
        $apto1 = \App\Models\Place::create(['name' => 'Apto 1']);
        $apto2 = \App\Models\Place::create(['name' => 'Apto 2']);

        $gate = \App\Models\Device::withoutEvents(fn () => \App\Models\Device::create([
            'name' => 'Garagem A',
            'brand' => 'portatec',
            'external_device_id' => 'chip-a',
        ]));
        $gate->places()->attach([$apto1->id, $apto2->id]);

        \App\Models\AccessCode::withoutEvents(fn () => \App\Models\AccessCode::create([
            'place_id' => $apto1->id,
            'pin' => '123456',
            'start' => now()->subDay(),
            'end' => now()->addDays(5),
        ]));

        $generator = app(\App\Services\AccessCode\AccessCodeGeneratorService::class);

        for ($i = 0; $i < 20; $i++) {
            $pin = $generator->generatePin($apto2->id, now(), now()->addDay());
            $this->assertNotSame('123456', $pin);
        }
    }
```

- [ ] **Step 6: Rodar e ver falhar**

```bash
./vendor/bin/sail test --filter=AccessCodeGeneratorServiceTest
```

Esperado: FAIL — `generatePin()` hoje aceita só `int $placeId`.

- [ ] **Step 7: Reescrever o gerador**

`app/Services/AccessCode/AccessCodeGeneratorService.php` — substituir o corpo por:

```php
<?php

declare(strict_types=1);

namespace App\Services\AccessCode;

use App\Models\AccessCode;
use App\Models\Booking;
use Carbon\CarbonInterface;

class AccessCodeGeneratorService
{
    public function __construct(
        private AccessCodeConflictChecker $conflictChecker
    ) {}

    public function createForBooking(Booking $booking): AccessCode
    {
        return AccessCode::create([
            'place_id' => $booking->place_id,
            'booking_id' => $booking->id,
            'user_id' => $booking->integration?->user_id,
            'pin' => $this->generatePin($booking->place_id, $booking->check_in, $booking->check_out),
            'start' => $booking->check_in,
            'end' => $booking->check_out,
        ]);
    }

    public function createStandalone(
        int $placeId,
        ?int $userId,
        CarbonInterface $start,
        ?CarbonInterface $end,
        ?string $pin = null
    ): AccessCode {
        return AccessCode::create([
            'place_id' => $placeId,
            'user_id' => $userId,
            'booking_id' => null,
            'pin' => $pin ?: $this->generatePin($placeId, $start, $end),
            'start' => $start,
            'end' => $end,
        ]);
    }

    /**
     * Spec §7: sorteia até achar um PIN que não colida, na janela pedida, em
     * nenhum dispositivo alcançado por este local. O laço é seguro — são
     * 10^6 combinações contra um punhado de códigos ativos por equipamento.
     */
    public function generatePin(int $placeId, CarbonInterface $start, ?CarbonInterface $end): string
    {
        do {
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while ($this->conflictChecker->conflicts($placeId, $pin, $start, $end));

        return $pin;
    }
}
```

- [ ] **Step 8: Atualizar a chamada antiga e rodar**

`tests/Unit/AccessCodeGeneratorServiceTest.php:34` chama `generatePin($placeId)` com um argumento só e passa a quebrar. Trocar por:

```php
        $pin = $service->generatePin($placeId, now(), now()->addDay());
```

```bash
./vendor/bin/sail test --filter=AccessCodeGeneratorServiceTest
```

Esperado: PASS.

- [ ] **Step 9: Escrever o teste do PIN manual (vai falhar)**

Em `tests/Feature/AccessCodes/AccessCodesTest.php`, adicionar:

```php
    public function test_manual_pin_conflicting_on_a_shared_device_is_rejected(): void
    {
        $user = \App\Models\User::factory()->create();

        $apto1 = \App\Models\Place::create(['name' => 'Apto 1']);
        $apto2 = \App\Models\Place::create(['name' => 'Apto 2']);

        \App\Models\PlaceUser::create(['place_id' => $apto2->id, 'user_id' => $user->id, 'role' => 'admin']);

        $gate = \App\Models\Device::withoutEvents(fn () => \App\Models\Device::create([
            'name' => 'Garagem A',
            'brand' => 'portatec',
            'external_device_id' => 'chip-a',
        ]));
        $gate->places()->attach([$apto1->id, $apto2->id]);

        \App\Models\AccessCode::withoutEvents(fn () => \App\Models\AccessCode::create([
            'place_id' => $apto1->id,
            'pin' => '123456',
            'start' => now()->subDay(),
            'end' => now()->addDays(5),
        ]));

        $this->actingAs($user)
            ->post('/app/access-codes', [
                'placeId' => $apto2->id,
                'pin' => '123456',
                'start' => now()->toDateTimeString(),
                'end' => now()->addDay()->toDateTimeString(),
            ])
            ->assertSessionHasErrors('pin');
    }
```

- [ ] **Step 10: Rodar e ver falhar**

```bash
./vendor/bin/sail test --filter=test_manual_pin_conflicting_on_a_shared_device_is_rejected
```

Esperado: FAIL — hoje o PIN manual não passa por checagem nenhuma.

- [ ] **Step 11: Criar a regra de validação**

`app/Rules/AccessCodePinAvailableRule.php`:

```php
<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\AccessCode\AccessCodeConflictChecker;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Spec §7. Aplica-se só quando o PIN foi digitado à mão — o gerado já sai sem
 * conflito por construção.
 */
class AccessCodePinAvailableRule implements ValidationRule
{
    public function __construct(
        private int $placeId,
        private CarbonInterface $start,
        private ?CarbonInterface $end,
        private ?int $ignoreAccessCodeId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $conflicts = app(AccessCodeConflictChecker::class)->conflicts(
            $this->placeId,
            $value,
            $this->start,
            $this->end,
            $this->ignoreAccessCodeId,
        );

        if ($conflicts) {
            $fail(trans('app.access_code_pin_conflict'));
        }
    }
}
```

- [ ] **Step 12: Aplicar a regra nos dois FormRequests**

`app/Http/Requests/StoreAccessCodeRequest.php` — trocar `rules()` por:

```php
    public function rules(): array
    {
        return [
            'placeId' => ['required', 'integer', 'exists:places,id'],
            'pin' => [
                'nullable',
                'string',
                'max:6',
                new AccessCodePinAvailableRule(
                    (int) $this->input('placeId'),
                    Carbon::parse((string) $this->input('start')),
                    $this->filled('end') ? Carbon::parse((string) $this->input('end')) : null,
                ),
            ],
            'start' => ['required', 'date'],
            'end' => ['nullable', 'date', 'after:start'],
        ];
    }
```

com `use App\Rules\AccessCodePinAvailableRule;` e `use Illuminate\Support\Carbon;` no topo.

`app/Http/Requests/UpdateAccessCodeRequest.php` — trocar `rules()` por:

```php
    public function rules(): array
    {
        /** @var \App\Models\AccessCode $accessCode */
        $accessCode = $this->route('accessCode');

        return [
            'pin' => [
                'required',
                'string',
                'max:6',
                new AccessCodePinAvailableRule(
                    (int) $accessCode->place_id,
                    Carbon::parse((string) $this->input('start')),
                    $this->filled('end') ? Carbon::parse((string) $this->input('end')) : null,
                    (int) $accessCode->id,
                ),
            ],
            'start' => ['required', 'date'],
            'end' => ['nullable', 'date', 'after:start'],
        ];
    }
```

com os mesmos dois `use`.

- [ ] **Step 13: Rodar e ver passar**

```bash
./vendor/bin/sail test --filter=AccessCodesTest
```

Esperado: PASS.

- [ ] **Step 14: Suíte inteira e commit**

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test
git add app/Services/AccessCode app/Rules/AccessCodePinAvailableRule.php app/Http/Requests/StoreAccessCodeRequest.php app/Http/Requests/UpdateAccessCodeRequest.php tests/Unit/AccessCodeConflictCheckerTest.php tests/Unit/AccessCodeGeneratorServiceTest.php tests/Feature/AccessCodes/AccessCodesTest.php
git commit -m "feat: unicidade de PIN por dispositivo com janela sobreposta"
```

---

### Task 1.2: Resolução do evento de acesso por PIN e instante

**Files:**
- Modify: `app/Services/Device/DeviceCommandService.php:196-245` (`handleAccessEvent()`)
- Test: `tests/Unit/AccessEventResolutionTest.php`

Hoje o método resolve o local com `$device->places()->value('places.id')` — o primeiro que o banco devolver — e casa por `place_id + pin`. Num portão compartilhado por 16 unidades isso atribui o acesso ao morador errado.

- [ ] **Step 1: Escrever o teste (vai falhar)**

`tests/Unit/AccessEventResolutionTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\AccessCode;
use App\Models\AccessEvent;
use App\Models\Device;
use App\Models\Place;
use App\Services\Device\DeviceCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AccessEventResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);
    }

    public function test_links_the_code_whose_window_contains_the_event(): void
    {
        [$gate, $apto1, $apto2] = $this->sharedGate();

        $old = $this->code($apto1, '111111', '2026-10-01 00:00', '2026-10-05 00:00');
        $current = $this->code($apto2, '111111', '2026-10-10 00:00', '2026-10-15 00:00');

        $this->handle($gate, '111111', Carbon::parse('2026-10-12 09:00'));

        $event = AccessEvent::query()->latest('id')->first();

        $this->assertSame($current->id, $event->access_code_id);
        $this->assertNotSame($old->id, $event->access_code_id);
    }

    public function test_ambiguous_collision_links_nothing_and_records_candidates(): void
    {
        [$gate, $apto1, $apto2] = $this->sharedGate();

        $a = $this->code($apto1, '222222', '2026-10-01 00:00', '2026-10-20 00:00');
        $b = $this->code($apto2, '222222', '2026-10-05 00:00', '2026-10-25 00:00');

        $this->handle($gate, '222222', Carbon::parse('2026-10-10 09:00'));

        $event = AccessEvent::query()->latest('id')->first();

        $this->assertNull($event->access_code_id);
        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id],
            $event->metadata['candidate_access_code_ids']
        );
    }

    public function test_unknown_pin_links_nothing(): void
    {
        [$gate] = $this->sharedGate();

        $this->handle($gate, '999999', Carbon::parse('2026-10-10 09:00'));

        $event = AccessEvent::query()->latest('id')->first();

        $this->assertNull($event->access_code_id);
        $this->assertSame('999999', $event->pin);
    }

    /** @return array{0: Device, 1: Place, 2: Place} */
    private function sharedGate(): array
    {
        $apto1 = Place::create(['name' => 'Apto 1']);
        $apto2 = Place::create(['name' => 'Apto 2']);

        $gate = Device::withoutEvents(fn (): Device => Device::create([
            'name' => 'Pedestres 1',
            'brand' => 'portatec',
            'external_device_id' => 'chip-ped-1',
        ]));

        $gate->places()->attach([$apto1->id, $apto2->id]);

        return [$gate, $apto1, $apto2];
    }

    private function code(Place $place, string $pin, string $start, string $end): AccessCode
    {
        return AccessCode::withoutEvents(fn (): AccessCode => AccessCode::create([
            'place_id' => $place->id,
            'pin' => $pin,
            'start' => Carbon::parse($start),
            'end' => Carbon::parse($end),
        ]));
    }

    private function handle(Device $device, string $pin, Carbon $at): void
    {
        app(DeviceCommandService::class)->handleAccessEvent($device->external_device_id, [
            'pin' => $pin,
            'result' => 'success',
            'timestamp_device' => $at->timestamp,
        ]);
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

```bash
./vendor/bin/sail test --filter=AccessEventResolutionTest
```

Esperado: FAIL — hoje casa pelo primeiro local, ignorando o instante.

- [ ] **Step 3: Reescrever `handleAccessEvent()`**

Em `app/Services/Device/DeviceCommandService.php`, substituir o trecho que vai de `$pin = (string) data_get(...)` até o `AccessEvent::create([...])` por:

```php
        $pin = (string) data_get($payload, 'pin', data_get($payload, 'default_pin', ''));
        $result = $this->normalizeAccessResult(data_get($payload, 'result', 'invalid'));

        $deviceTimestamp = data_get($payload, 'timestamp_device')
            ? Carbon::createFromTimestamp((int) data_get($payload, 'timestamp_device'))
            : null;

        // Spec §7: num dispositivo compartilhado, `pin` sozinho não identifica
        // ninguém — o mesmo código pode ter sido usado por unidades diferentes
        // em épocas diferentes. Quem desempata é o instante do evento.
        $candidateIds = $this->resolveAccessCodeCandidates($device, $pin, $deviceTimestamp ?? now());

        $metadata = $payload;
        if (count($candidateIds) > 1) {
            $metadata['candidate_access_code_ids'] = $candidateIds;
        }

        try {
            $accessEvent = AccessEvent::create([
                'device_id' => $device->id,
                'access_code_id' => count($candidateIds) === 1 ? $candidateIds[0] : null,
                'pin' => $pin,
                'result' => $result,
                'device_timestamp' => $deviceTimestamp,
                'metadata' => $metadata,
            ]);
```

e adicionar o método privado logo abaixo de `handleAccessEvent()`:

```php
    /**
     * Códigos com este PIN, em qualquer local do dispositivo, cuja janela
     * contém o instante do evento. Mais de um candidato é a colisão herdada
     * de §7: o sistema não escolhe um dono, registra os dois.
     *
     * @return array<int, int>
     */
    private function resolveAccessCodeCandidates(Device $device, string $pin, CarbonInterface $at): array
    {
        if ($pin === '') {
            return [];
        }

        $placeIds = $device->places()->pluck('places.id');

        if ($device->place_id !== null) {
            $placeIds = $placeIds->push($device->place_id);
        }

        $placeIds = $placeIds->unique()->values();

        if ($placeIds->isEmpty()) {
            return [];
        }

        return AccessCode::query()
            ->whereIn('place_id', $placeIds)
            ->where('pin', $pin)
            ->where('start', '<=', $at)
            ->where(fn ($query) => $query->whereNull('end')->orWhere('end', '>=', $at))
            ->pluck('id')
            ->all();
    }
```

Adicionar `use Carbon\CarbonInterface;` no topo do arquivo, se ainda não houver.

- [ ] **Step 4: Rodar e ver passar**

```bash
./vendor/bin/sail test --filter=AccessEventResolutionTest
```

Esperado: PASS, 3 testes.

- [ ] **Step 5: Suíte inteira e commit**

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test
git add app/Services/Device/DeviceCommandService.php tests/Unit/AccessEventResolutionTest.php
git commit -m "fix: resolve evento de acesso por PIN e instante em dispositivo compartilhado"
```

---

## Trilha DEVICE

### Task 1.3: Reescrever a `DevicePolicy`

**Files:**
- Modify: `app/Policies/DevicePolicy.php`
- Modify: `app/Http/Controllers/App/DeviceController.php` (`store()` grava o admin; `edit()` passa a usar `can('update')`)
- Modify: `app/Http/Controllers/App/TuyaConnectController.php`
- Test: `tests/Feature/Devices/DevicePermissionTest.php`

Some o ramo que hoje concede acesso a qualquer dispositivo Tuya sem local para qualquer usuário que tenha uma integração Tuya própria — era brecha entre contas.

> **Correção aplicada depois da primeira execução (commit `8484d08`):** este plano não mandava mexer no `DeviceController::edit()`, que continuava com a checagem antiga por vínculo com o local. Como a tela de edição mostra pinos, funções e `external_device_id`, ela era uma porta de leitura da configuração para quem só tinha concessão de uso ou era membro do local. Passou a usar `can('update', $device)`, com regressão em `DevicePermissionTest::test_edit_screen_is_closed_to_grantee_and_place_member()`. Toda tela que exibe configuração precisa da mesma habilidade do `update()` — vale checar isso nas fases seguintes.

- [ ] **Step 1: Escrever o teste (vai falhar)**

`tests/Feature/Devices/DevicePermissionTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Devices;

use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevicePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);
    }

    public function test_device_admin_can_update_and_view(): void
    {
        $admin = User::factory()->create();
        $device = $this->deviceOwnedBy($admin);

        $this->assertTrue($admin->can('update', $device));
        $this->assertTrue($admin->can('view', $device));
    }

    public function test_grantee_can_view_but_not_update(): void
    {
        $admin = User::factory()->create();
        $grantee = User::factory()->create();
        $device = $this->deviceOwnedBy($admin);

        $device->deviceUsers()->create(['user_id' => $grantee->id, 'role' => 'user']);

        $this->assertTrue($grantee->can('view', $device));
        $this->assertFalse($grantee->can('update', $device));
    }

    public function test_place_member_can_view_but_not_update(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $device = $this->deviceOwnedBy($admin);

        $place = Place::create(['name' => 'Apto 1']);
        PlaceUser::create(['place_id' => $place->id, 'user_id' => $member->id, 'role' => 'host']);
        $device->places()->attach($place->id);

        $this->assertTrue($member->can('view', $device));
        $this->assertFalse($member->can('update', $device));
    }

    public function test_stranger_can_do_nothing(): void
    {
        $admin = User::factory()->create();
        $stranger = User::factory()->create();
        $device = $this->deviceOwnedBy($admin);

        $this->assertFalse($stranger->can('view', $device));
        $this->assertFalse($stranger->can('update', $device));
        $this->assertFalse($stranger->can('attach', $device));
    }

    public function test_tuya_device_without_place_is_not_public_to_other_tuya_users(): void
    {
        $owner = User::factory()->create();
        $otherTuyaUser = User::factory()->create();

        $device = Device::withoutEvents(fn (): Device => Device::create([
            'name' => 'Tuya solto',
            'brand' => 'tuya',
        ]));
        $device->deviceUsers()->create(['user_id' => $owner->id, 'role' => 'admin']);

        $platform = \App\Models\Platform::firstOrCreate(['slug' => 'tuya'], ['name' => 'Tuya']);
        \App\Models\Integration::create([
            'user_id' => $otherTuyaUser->id,
            'platform_id' => $platform->id,
        ]);

        $this->assertFalse($otherTuyaUser->can('view', $device));
    }

    public function test_grantee_and_admin_can_attach_but_place_member_cannot(): void
    {
        $admin = User::factory()->create();
        $grantee = User::factory()->create();
        $member = User::factory()->create();

        $device = $this->deviceOwnedBy($admin);
        $device->deviceUsers()->create(['user_id' => $grantee->id, 'role' => 'user']);

        $place = Place::create(['name' => 'Apto 1']);
        PlaceUser::create(['place_id' => $place->id, 'user_id' => $member->id, 'role' => 'admin']);
        $device->places()->attach($place->id);

        $this->assertTrue($admin->can('attach', $device));
        $this->assertTrue($grantee->can('attach', $device));
        $this->assertFalse($member->can('attach', $device));
    }

    private function deviceOwnedBy(User $user): Device
    {
        $device = Device::withoutEvents(fn (): Device => Device::create([
            'name' => 'Garagem A',
            'brand' => 'portatec',
            'external_device_id' => 'chip-a',
        ]));

        $device->deviceUsers()->create(['user_id' => $user->id, 'role' => 'admin']);

        return $device;
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

```bash
./vendor/bin/sail test --filter=DevicePermissionTest
```

Esperado: FAIL — a habilidade `attach` não existe e o ramo Tuya ainda concede acesso.

- [ ] **Step 3: Reescrever a policy**

`app/Policies/DevicePolicy.php`:

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Device;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Spec §5. A fonte da verdade é `device_user`:
 *
 * - `admin`  — configura, concede, revoga, transfere. Um por dispositivo.
 * - `user`   — concessão de uso: pode acionar e levar para os locais que administra.
 *
 * Membro de local que contém o dispositivo aciona, mas não configura nem leva
 * para outro lugar. É o que separa "morador usa o portão" de "morador manda no
 * portão".
 */
class DevicePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Device $device): bool
    {
        return $device->isUsableBy($user) || $this->isPlaceMember($user, $device);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Device $device): bool
    {
        return $device->isAdministeredBy($user);
    }

    public function delete(User $user, Device $device): bool
    {
        return $device->isAdministeredBy($user);
    }

    /** Levar o dispositivo para um local. O local de destino é checado à parte. */
    public function attach(User $user, Device $device): bool
    {
        return $device->isUsableBy($user);
    }

    /** Conceder, revogar e transferir. */
    public function managePermissions(User $user, Device $device): bool
    {
        return $device->isAdministeredBy($user);
    }

    public function restore(User $user, Device $device): bool
    {
        return $device->isAdministeredBy($user);
    }

    public function forceDelete(User $user, Device $device): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Device $device): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    private function isPlaceMember(User $user, Device $device): bool
    {
        if ($device->places()->whereHas('placeUsers', fn ($query) => $query->where('user_id', $user->id))->exists()) {
            return true;
        }

        return $device->place_id !== null
            && $user->placeUsers()->where('place_id', $device->place_id)->exists();
    }
}
```

- [ ] **Step 4: Rodar e ver passar**

```bash
./vendor/bin/sail test --filter=DevicePermissionTest
```

Esperado: PASS, 6 testes.

- [ ] **Step 5: Fazer a criação de dispositivo gravar o admin**

Em `app/Http/Controllers/App/DeviceController.php`, no `store()`, logo depois de `$device->places()->sync($placeIds);`, adicionar:

```php
        $device->deviceUsers()->create([
            'user_id' => Auth::id(),
            'role' => \App\Enums\DeviceRoleEnum::Admin->value,
        ]);
```

Em `app/Http/Controllers/App/TuyaConnectController.php:131`, trocar:

```php
            $device->deviceUsers()->firstOrCreate(['user_id' => Auth::id()]);
```

por:

```php
            $device->deviceUsers()->firstOrCreate(
                ['user_id' => Auth::id()],
                ['role' => \App\Enums\DeviceRoleEnum::Admin->value],
            );
```

- [ ] **Step 6: Rodar a suíte de dispositivos**

```bash
./vendor/bin/sail test --filter=Devices
```

Esperado: PASS. Se algum teste de `DevicesTest` quebrar por depender do acesso antigo, ajuste o teste para criar o vínculo `device_user` — o comportamento novo é o correto.

- [ ] **Step 7: Suíte inteira e commit**

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test
git add app/Policies/DevicePolicy.php app/Http/Controllers/App/DeviceController.php app/Http/Controllers/App/TuyaConnectController.php tests/Feature/Devices/DevicePermissionTest.php tests/Feature/Devices/DevicesTest.php
git commit -m "feat: DevicePolicy passa a usar device_user como fonte da verdade"
```

---

## Trilha PLACE

### Task 1.4: `PlacePolicy::update()` exclusiva do admin do local

**Files:**
- Modify: `app/Policies/PlacePolicy.php`
- Modify: `app/Http/Controllers/App/PlaceController.php:96-140` (`show()`, `edit()`, `update()`)
- Test: `tests/Feature/Places/PlacesTest.php`

`host` perde renomear, anexar e desanexar. Mantém tudo o mais — inclusive criar código de acesso.

- [ ] **Step 1: Escrever o teste (vai falhar)**

Em `tests/Feature/Places/PlacesTest.php`, adicionar (e o helper `makePlaceWithHost`):

```php
    private function makePlaceWithHost(User $user, string $name = 'Condomínio'): Place
    {
        $place = Place::create(['name' => $name]);

        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => User::factory()->create()->id,
            'role' => 'admin',
            'label' => 'Síndico',
        ]);

        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $user->id,
            'role' => 'host',
            'label' => $user->name,
        ]);

        return $place;
    }

    public function test_host_cannot_rename_the_place(): void
    {
        $host = User::factory()->create();
        $place = $this->makePlaceWithHost($host);

        $this->actingAs($host)
            ->put("/app/places/{$place->id}", ['name' => 'Novo nome'])
            ->assertForbidden();

        $this->actingAs($host)
            ->get("/app/places/{$place->id}/edit")
            ->assertForbidden();
    }

    public function test_place_admin_can_rename_the_place(): void
    {
        $user = User::factory()->create();
        $place = $this->makePlaceWithAdmin($user);

        $this->actingAs($user)
            ->put("/app/places/{$place->id}", ['name' => 'Novo nome'])
            ->assertRedirect("/app/places/{$place->id}");

        $this->assertSame('Novo nome', $place->fresh()->name);
    }

    public function test_host_still_sees_the_place(): void
    {
        $host = User::factory()->create();
        $place = $this->makePlaceWithHost($host);

        $this->actingAs($host)
            ->get("/app/places/{$place->id}")
            ->assertOk();
    }
```

- [ ] **Step 2: Rodar e ver falhar**

```bash
./vendor/bin/sail test --filter=test_host_cannot_rename_the_place
```

Esperado: FAIL — hoje devolve 302, não 403.

- [ ] **Step 3: Ajustar a policy**

Em `app/Policies/PlacePolicy.php`, trocar `update()` e `delete()` por:

```php
    /**
     * Spec §5: renomear o local, anexar e desanexar dispositivo são a mesma
     * habilidade e são do admin do local. `host` mantém acionar, códigos de
     * acesso, reservas e histórico.
     */
    public function update(User $user, Place $place): bool
    {
        return $this->hasPlaceAdminAccess($user, $place->id);
    }

    public function delete(User $user, Place $place): bool
    {
        return $this->hasPlaceAdminAccess($user, $place->id);
    }
```

- [ ] **Step 4: Fazer `PlaceController` usar a policy**

Em `app/Http/Controllers/App/PlaceController.php`, trocar os três `abort_unless($place->placeUsers()->where('user_id', Auth::id())->exists(), 403)`:

- em `show()` → `abort_unless(Auth::user()?->can('view', $place), 403);`
- em `edit()` → `abort_unless(Auth::user()?->can('update', $place), 403);`
- em `update()` → `abort_unless(Auth::user()?->can('update', $place), 403);`

- [ ] **Step 5: Rodar e ver passar**

```bash
./vendor/bin/sail test --filter=PlacesTest
```

Esperado: PASS. Se algum teste antigo assumia que `host` renomeia, corrija o teste — o comportamento novo é o correto.

- [ ] **Step 6: Esconder o botão de editar para quem não pode**

`resources/js/pages/places/show.tsx` já recebe `abilities.update` do controller. Confirme que o botão "Editar" está condicionado a ele; se não estiver, condicione.

- [ ] **Step 7: Suíte inteira e commit**

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test
git add app/Policies/PlacePolicy.php app/Http/Controllers/App/PlaceController.php resources/js/pages/places/show.tsx tests/Feature/Places/PlacesTest.php
git commit -m "feat: renomear local e gerenciar dispositivos passam a ser do admin do local"
```

---

### Task 1.5: Remover clonar local

**Files:**
- Delete: `app/Http/Controllers/App/PlaceCloneController.php`
- Delete: `app/Services/PlaceCloneService.php`
- Delete: `app/Http/Requests/StorePlaceCloneRequest.php`
- Delete: `resources/js/pages/places/clone.tsx`
- Modify: `routes/web.php` (remover as duas rotas `places.clone` e o import)
- Modify: `resources/js/pages/places/show.tsx` (remover o link de clonar)
- Modify: `app/Policies/PlacePolicy.php` (o método `replicate()` some)
- Modify: `tests/Feature/Places/PlacesTest.php` (remover os testes de clone)
- Modify: `app/Http/Controllers/App/PlaceController.php` (tirar `'replicate'` do array `abilities`)

Os arquivos gerados pelo Wayfinder (`resources/js/actions/**`, `resources/js/routes/**`) são regenerados, não editados à mão.

- [ ] **Step 1: Remover as rotas**

Em `routes/web.php`, apagar as duas linhas de `places.clone` e `places.clone.store`, mais o `use App\Http\Controllers\App\PlaceCloneController;`.

- [ ] **Step 2: Apagar os arquivos PHP e a tela**

```bash
rm app/Http/Controllers/App/PlaceCloneController.php \
   app/Services/PlaceCloneService.php \
   app/Http/Requests/StorePlaceCloneRequest.php \
   resources/js/pages/places/clone.tsx
```

- [ ] **Step 3: Limpar as referências**

- `app/Policies/PlacePolicy.php`: apagar o método `replicate()`.
- `app/Http/Controllers/App/PlaceController.php`, em `show()`: remover a linha `'replicate' => Auth::user()?->can('replicate', $place) ?? false,`.
- `resources/js/pages/places/show.tsx`: remover o link para a tela de clonar e qualquer uso de `abilities.replicate`.
- `tests/Feature/Places/PlacesTest.php`: apagar os testes de clone e o `use App\Models\PlaceDeviceFunction;` se ficar órfão.

- [ ] **Step 4: Confirmar que não sobrou referência**

```bash
grep -rn "clone\|Clone" app routes resources/js/pages tests || echo "limpo"
```

Esperado: nenhuma linha de `PlaceClone`. Ocorrências em `resources/js/actions` e `resources/js/routes` somem no próximo build.

- [ ] **Step 5: Regenerar as rotas do front**

```bash
./vendor/bin/sail npm run build
```

Esperado: build sem erro, e `resources/js/actions/App/Http/Controllers/App/PlaceCloneController.ts` deixa de existir.

- [ ] **Step 6: Suíte inteira e commit**

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test
git add -A
git commit -m "refactor: remove clonar local"
```

---

# FASE 2 — Ações

Duas trilhas em paralelo. Depende da Fase 1 inteira.

---

## Trilha ATTACH

### Task 2.1: Anexar e desanexar como ação própria

**Files:**
- Modify: `app/Http/Controllers/App/PlaceAttachDeviceController.php`
- Modify: `app/Http/Controllers/App/PlaceDeviceController.php`
- Modify: `app/Http/Controllers/App/DeviceController.php` (`update()`, o trecho de `$placeIds`)
- Test: `tests/Feature/Places/PlaceAttachDeviceTest.php`

Fecha a falha mais grave do sistema atual: hoje `create()` lista todos os dispositivos sem local de todas as contas, e `store()` aceita qualquer `deviceId` sem checar dono.

- [ ] **Step 1: Escrever o teste (vai falhar)**

`tests/Feature/Places/PlaceAttachDeviceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Places;

use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceAttachDeviceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);
    }

    public function test_cannot_attach_a_device_from_another_account(): void
    {
        $victim = User::factory()->create();
        $attacker = User::factory()->create();

        $gate = $this->deviceOwnedBy($victim, 'Portão da vítima');
        $place = $this->placeAdministeredBy($attacker);

        $this->actingAs($attacker)
            ->post("/app/places/{$place->id}/devices/attach", ['deviceId' => $gate->id])
            ->assertForbidden();

        $this->assertSame(0, $gate->places()->count());
    }

    public function test_attach_list_does_not_leak_other_accounts_devices(): void
    {
        $victim = User::factory()->create();
        $attacker = User::factory()->create();

        $this->deviceOwnedBy($victim, 'Portão da vítima');
        $place = $this->placeAdministeredBy($attacker);

        $this->actingAs($attacker)
            ->get("/app/places/{$place->id}/devices/attach")
            ->assertOk()
            ->assertDontSee('Portão da vítima');
    }

    public function test_grantee_can_attach_to_own_place(): void
    {
        $owner = User::factory()->create();
        $resident = User::factory()->create();

        $gate = $this->deviceOwnedBy($owner, 'Garagem A');
        $gate->deviceUsers()->create(['user_id' => $resident->id, 'role' => 'user']);

        $place = $this->placeAdministeredBy($resident, 'Apto 1');

        $this->actingAs($resident)
            ->post("/app/places/{$place->id}/devices/attach", ['deviceId' => $gate->id])
            ->assertRedirect("/app/places/{$place->id}");

        $this->assertTrue($gate->places()->where('places.id', $place->id)->exists());
    }

    public function test_host_cannot_attach(): void
    {
        $owner = User::factory()->create();
        $host = User::factory()->create();

        $gate = $this->deviceOwnedBy($owner, 'Garagem A');
        $gate->deviceUsers()->create(['user_id' => $host->id, 'role' => 'user']);

        $place = Place::create(['name' => 'Apto 1']);
        PlaceUser::create(['place_id' => $place->id, 'user_id' => $owner->id, 'role' => 'admin']);
        PlaceUser::create(['place_id' => $place->id, 'user_id' => $host->id, 'role' => 'host']);

        $this->actingAs($host)
            ->post("/app/places/{$place->id}/devices/attach", ['deviceId' => $gate->id])
            ->assertForbidden();
    }

    public function test_editing_a_shared_device_does_not_change_other_places(): void
    {
        $owner = User::factory()->create();

        $gate = $this->deviceOwnedBy($owner, 'Garagem A');
        $condo = $this->placeAdministeredBy($owner, 'Condomínio');
        $apto = Place::create(['name' => 'Apto 2']);

        $gate->places()->attach([$condo->id, $apto->id]);

        $this->actingAs($owner)
            ->put("/app/devices/{$gate->id}", [
                'name' => 'Garagem A renomeada',
                'brand' => 'portatec',
                'external_device_id' => 'chip-a',
                'placeIds' => [$condo->id],
                'deviceFunctions' => [['id' => null, 'type' => 'switch', 'pin' => '2']],
            ])
            ->assertRedirect("/app/devices/{$gate->id}");

        $this->assertTrue(
            $gate->places()->where('places.id', $apto->id)->exists(),
            'Editar o dispositivo não pode desanexá-lo dos locais dos outros.'
        );
    }

    private function deviceOwnedBy(User $user, string $name): Device
    {
        $device = Device::withoutEvents(fn (): Device => Device::create([
            'name' => $name,
            'brand' => 'portatec',
            'external_device_id' => 'chip-'.$user->id,
        ]));

        $device->deviceUsers()->create(['user_id' => $user->id, 'role' => 'admin']);

        return $device;
    }

    private function placeAdministeredBy(User $user, string $name = 'Meu local'): Place
    {
        $place = Place::create(['name' => $name]);

        PlaceUser::create(['place_id' => $place->id, 'user_id' => $user->id, 'role' => 'admin']);

        return $place;
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

```bash
./vendor/bin/sail test --filter=PlaceAttachDeviceTest
```

Esperado: FAIL nos cinco.

- [ ] **Step 3: Reescrever `PlaceAttachDeviceController`**

`app/Http/Controllers/App/PlaceAttachDeviceController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlaceAttachDeviceRequest;
use App\Http\Resources\PlaceResource;
use App\Models\Device;
use App\Models\Place;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PlaceAttachDeviceController extends Controller
{
    /**
     * Spec §5. A lista é "o que EU posso trazer para cá": dispositivos que eu
     * administro mais os que me foram concedidos. A versão anterior tinha um
     * ramo `whereDoesntHave('places')->whereNull('place_id')` sem escopo
     * nenhum, que mostrava os dispositivos sem local de TODAS as contas.
     */
    public function create(Place $place): Response
    {
        $this->authorize('update', $place);

        $devices = Device::query()
            ->withCount('deviceFunctions')
            ->with('places')
            ->whereHas('deviceUsers', fn (Builder $query) => $query->where('user_id', Auth::id()))
            ->whereDoesntHave('places', fn (Builder $query) => $query->where('places.id', $place->id))
            ->where(fn (Builder $query) => $query->whereNull('place_id')->orWhere('place_id', '!=', $place->id))
            ->orderBy('name')
            ->get();

        return Inertia::render('places/attach-device', [
            'place' => new PlaceResource($place),
            'devices' => $devices->map(fn (Device $device): array => [
                'id' => $device->id,
                'name' => $device->name,
                'brand' => $device->brand?->value,
                'device_functions_count' => $device->device_functions_count,
                'place_names' => $device->places->pluck('name')->values(),
                'fallback_place_name' => $device->place?->name,
            ])->values(),
        ]);
    }

    public function store(StorePlaceAttachDeviceRequest $request, Place $place): RedirectResponse
    {
        $this->authorize('update', $place);

        $device = Device::query()->findOrFail($request->validated()['deviceId']);

        // A checagem que faltava: ser admin do local de destino não diz nada
        // sobre o direito de mexer NESTE dispositivo.
        $this->authorize('attach', $device);

        if ($device->places()->where('places.id', $place->id)->exists() || $device->place_id === $place->id) {
            return redirect()
                ->route('app.places.show', ['place' => $place->id])
                ->with('status', __('app.device_already_in_place'));
        }

        $device->places()->syncWithoutDetaching([$place->id]);

        if ($device->place_id === null) {
            $device->update(['place_id' => $place->id]);
        }

        return redirect()
            ->route('app.places.show', ['place' => $place->id])
            ->with('status', __('app.device_attached', ['name' => $device->name]));
    }
}
```

Nota: o loop que criava `PlaceDeviceFunction` sai daqui — a tabela é removida na Task 4.1, e nada mais depende dela ser preenchida no anexo.

- [ ] **Step 4: Fazer `DeviceController::update()` parar de sincronizar locais**

Em `app/Http/Controllers/App/DeviceController.php`, no método `update()`:

- apagar o bloco que monta `$placeIds` e o `abort_unless(count($allowedPlaceIds) === count($placeIds), 403);`
- trocar `'place_id' => $placeIds[0] ?? null,` por `'place_id' => $device->place_id,` dentro do `$device->update([...])` (ou simplesmente remover a chave `place_id` do array)
- apagar as duas últimas linhas antes do `redirect`: `$device->places()->sync($placeIds);` e `$syncService->sync($device, $placeIds);`
- remover o parâmetro `DevicePlaceFunctionSyncService $syncService` da assinatura e o `use` correspondente

O campo `placeIds` continua chegando do formulário; ele passa a ser ignorado. A Task 2.1 não mexe na tela — `resources/js/pages/devices/edit.tsx` deixa de oferecer a escolha de locais numa tarefa de UI posterior, se você quiser; funcionalmente, ignorar já é o comportamento correto.

- [ ] **Step 5: Ajustar `PlaceDeviceController::destroy()`**

O `Gate::authorize('update', $place)` já vira admin-only pela Task 1.4. Remover só o bloco que apaga `PlaceDeviceFunction`, que sai na Task 4.1:

```php
    public function destroy(Request $request, Place $place, Device $device): RedirectResponse
    {
        Gate::authorize('update', $place);

        $device = Device::query()
            ->where('id', $device->id)
            ->where(function ($query) use ($place): void {
                $query->whereHas('places', fn ($query) => $query->where('places.id', $place->id))
                    ->orWhere('place_id', $place->id);
            })
            ->firstOrFail();

        $place->devices()->detach($device->id);

        $device->load('places');
        $device->update(['place_id' => $device->places->first()?->id]);

        return redirect()->route('app.places.show', ['place' => $place->id]);
    }
```

- [ ] **Step 6: Rodar e ver passar**

```bash
./vendor/bin/sail test --filter=PlaceAttachDeviceTest
```

Esperado: PASS, 5 testes.

- [ ] **Step 7: Suíte inteira e commit**

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test
git add app/Http/Controllers/App/PlaceAttachDeviceController.php app/Http/Controllers/App/PlaceDeviceController.php app/Http/Controllers/App/DeviceController.php tests/Feature/Places/PlaceAttachDeviceTest.php
git commit -m "fix: anexar dispositivo passa a exigir permissao sobre o dispositivo"
```

---

## Trilha GRANT

### Task 2.2: Tela de permissões do dispositivo

**Files:**
- Create: `app/Http/Controllers/App/DevicePermissionController.php`
- Create: `app/Http/Controllers/App/DeviceTransferController.php`
- Create: `app/Http/Requests/StoreDevicePermissionRequest.php`
- Create: `app/Http/Requests/StoreDeviceTransferRequest.php`
- Create: `app/Services/Device/DeviceGrantService.php`
- Create: `resources/js/pages/devices/permissions.tsx`
- Modify: `routes/web.php`
- Modify: `resources/js/pages/devices/show.tsx` (link para a nova tela)
- Test: `tests/Feature/Devices/DevicePermissionScreenTest.php`

A busca de pessoa é **por e-mail exato** (spec §6), tanto aqui quanto em `PlaceMemberSearchController` — este último é ajustado na mesma tarefa por ser a mesma decisão.

- [ ] **Step 1: Escrever o teste (vai falhar)**

`tests/Feature/Devices/DevicePermissionScreenTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Devices;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevicePermissionScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);
    }

    public function test_admin_grants_by_exact_email(): void
    {
        $admin = User::factory()->create();
        $resident = User::factory()->create(['email' => 'morador@exemplo.com']);
        $device = $this->deviceOwnedBy($admin);

        $this->actingAs($admin)
            ->post("/app/devices/{$device->id}/permissions", ['email' => 'morador@exemplo.com'])
            ->assertRedirect("/app/devices/{$device->id}/permissions");

        $this->assertTrue($device->deviceUsers()->where('user_id', $resident->id)->where('role', 'user')->exists());
    }

    public function test_partial_email_finds_nobody(): void
    {
        $admin = User::factory()->create();
        User::factory()->create(['email' => 'morador@exemplo.com']);
        $device = $this->deviceOwnedBy($admin);

        $this->actingAs($admin)
            ->post("/app/devices/{$device->id}/permissions", ['email' => 'morador'])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, $device->deviceUsers()->count());
    }

    public function test_grantee_cannot_grant(): void
    {
        $admin = User::factory()->create();
        $resident = User::factory()->create();
        $third = User::factory()->create(['email' => 'terceiro@exemplo.com']);

        $device = $this->deviceOwnedBy($admin);
        $device->deviceUsers()->create(['user_id' => $resident->id, 'role' => 'user']);

        $this->actingAs($resident)
            ->post("/app/devices/{$device->id}/permissions", ['email' => $third->email])
            ->assertForbidden();
    }

    public function test_transfer_swaps_the_roles(): void
    {
        $admin = User::factory()->create();
        $newAdmin = User::factory()->create(['email' => 'novo@exemplo.com']);
        $device = $this->deviceOwnedBy($admin);

        $this->actingAs($admin)
            ->post("/app/devices/{$device->id}/transfer", ['email' => 'novo@exemplo.com'])
            ->assertRedirect("/app/devices/{$device->id}/permissions");

        $this->assertTrue($device->fresh()->isAdministeredBy($newAdmin));
        $this->assertFalse($device->fresh()->isAdministeredBy($admin));
        $this->assertTrue($device->fresh()->isUsableBy($admin));
    }

    private function deviceOwnedBy(User $user): Device
    {
        $device = Device::withoutEvents(fn (): Device => Device::create([
            'name' => 'Garagem A',
            'brand' => 'portatec',
            'external_device_id' => 'chip-a',
        ]));

        $device->deviceUsers()->create(['user_id' => $user->id, 'role' => 'admin']);

        return $device;
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

```bash
./vendor/bin/sail test --filter=DevicePermissionScreenTest
```

Esperado: FAIL — as rotas não existem (404).

- [ ] **Step 3: Criar o serviço**

`app/Services/Device/DeviceGrantService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Device;

use App\Enums\DeviceRoleEnum;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Spec §6. Conceder, revogar e transferir mexem só em `device_user`. A cascata
 * da revogação (desanexar dos locais e apagar os PINs do equipamento) mora na
 * Task 3.1 e é plugada aqui.
 */
class DeviceGrantService
{
    public function grant(Device $device, User $user): void
    {
        $device->deviceUsers()->firstOrCreate(
            ['user_id' => $user->id],
            ['role' => DeviceRoleEnum::User->value],
        );
    }

    /**
     * Troca os papéis: quem recebe vira admin, quem transferiu vira concessão.
     * Mantém o uso e perde a configuração — spec §6.
     */
    public function transfer(Device $device, User $newAdmin): void
    {
        DB::transaction(function () use ($device, $newAdmin): void {
            $device->deviceUsers()
                ->where('role', DeviceRoleEnum::Admin)
                ->update(['role' => DeviceRoleEnum::User->value]);

            $device->deviceUsers()->updateOrCreate(
                ['user_id' => $newAdmin->id],
                ['role' => DeviceRoleEnum::Admin->value],
            );
        });
    }
}
```

- [ ] **Step 4: Criar os FormRequests**

`app/Http/Requests/StoreDevicePermissionRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDevicePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Spec §6: e-mail exato. `exists` numa string completa não permite
     * enumerar — sem o endereço inteiro, nada é encontrado.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'exists:users,email'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.exists' => trans('app.user_email_not_found'),
            'email.email' => trans('app.user_email_not_found'),
        ];
    }
}
```

`app/Http/Requests/StoreDeviceTransferRequest.php`: idêntico, trocando só o nome da classe.

- [ ] **Step 5: Criar os controllers**

`app/Http/Controllers/App/DevicePermissionController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\DeviceRoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDevicePermissionRequest;
use App\Models\Device;
use App\Models\DeviceUser;
use App\Models\User;
use App\Services\Device\DeviceGrantService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DevicePermissionController extends Controller
{
    public function index(Device $device): Response
    {
        $this->authorize('managePermissions', $device);

        $device->load('deviceUsers.user');

        return Inertia::render('devices/permissions', [
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
            ],
            'admin' => $device->deviceUsers
                ->firstWhere('role', DeviceRoleEnum::Admin)
                ?->user
                ?->only(['id', 'name', 'email']),
            'grantees' => $device->deviceUsers
                ->where('role', DeviceRoleEnum::User)
                ->map(fn (DeviceUser $link): array => [
                    'id' => $link->id,
                    'user' => $link->user?->only(['id', 'name', 'email']),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function store(
        StoreDevicePermissionRequest $request,
        Device $device,
        DeviceGrantService $service
    ): RedirectResponse {
        $this->authorize('managePermissions', $device);

        $user = User::query()->where('email', $request->validated()['email'])->firstOrFail();

        $service->grant($device, $user);

        return redirect()
            ->route('app.devices.permissions.index', ['device' => $device->id])
            ->with('status', __('app.device_permission_granted'));
    }

    public function destroy(Device $device, int $deviceUser, DeviceGrantService $service): RedirectResponse
    {
        $this->authorize('managePermissions', $device);

        $link = DeviceUser::query()
            ->where('device_id', $device->id)
            ->where('role', DeviceRoleEnum::User)
            ->findOrFail($deviceUser);

        $service->revoke($device, $link->user);

        return redirect()
            ->route('app.devices.permissions.index', ['device' => $device->id])
            ->with('status', __('app.device_permission_revoked'));
    }
}
```

Nota: `DeviceGrantService::revoke()` é criado na Task 3.1. Até lá, adicione nesta tarefa a versão mínima, que a Task 3.1 estende:

```php
    public function revoke(Device $device, User $user): void
    {
        $device->deviceUsers()
            ->where('user_id', $user->id)
            ->where('role', DeviceRoleEnum::User)
            ->delete();
    }
```

`app/Http/Controllers/App/DeviceTransferController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceTransferRequest;
use App\Models\Device;
use App\Models\User;
use App\Services\Device\DeviceGrantService;
use Illuminate\Http\RedirectResponse;

class DeviceTransferController extends Controller
{
    public function store(
        StoreDeviceTransferRequest $request,
        Device $device,
        DeviceGrantService $service
    ): RedirectResponse {
        $this->authorize('managePermissions', $device);

        $user = User::query()->where('email', $request->validated()['email'])->firstOrFail();

        $service->transfer($device, $user);

        return redirect()
            ->route('app.devices.permissions.index', ['device' => $device->id])
            ->with('status', __('app.device_transferred', ['name' => $user->name]));
    }
}
```

- [ ] **Step 6: Registrar as rotas**

Em `routes/web.php`, dentro do grupo `app.`, logo depois da linha de `devices.commands.store`:

```php
        Route::get('/devices/{device}/permissions', [DevicePermissionController::class, 'index'])->name('devices.permissions.index');
        Route::post('/devices/{device}/permissions', [DevicePermissionController::class, 'store'])->name('devices.permissions.store');
        Route::delete('/devices/{device}/permissions/{deviceUser}', [DevicePermissionController::class, 'destroy'])->name('devices.permissions.destroy');
        Route::post('/devices/{device}/transfer', [DeviceTransferController::class, 'store'])->name('devices.transfer');
```

com os dois `use` no topo, ao lado dos outros imports de `App\Http\Controllers\App\`.

- [ ] **Step 7: Criar a tela**

`resources/js/pages/devices/permissions.tsx`, na íntegra:

```tsx
import { Head, router, useForm } from '@inertiajs/react';
import { useState, type FormEventHandler } from 'react';

import { destroy, index, store } from '@/actions/App/Http/Controllers/App/DevicePermissionController';
import { store as transfer } from '@/actions/App/Http/Controllers/App/DeviceTransferController';
import { show } from '@/actions/App/Http/Controllers/App/DeviceController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FormField } from '@/components/form-field';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import devices from '@/routes/app/devices';

interface PermissionUser {
    id: number;
    name: string;
    email: string;
}

interface Grantee {
    id: number;
    user: PermissionUser | null;
}

interface DevicePermissionsProps {
    device: { id: number; name: string };
    admin: PermissionUser | null;
    grantees: Grantee[];
    [key: string]: unknown;
}

interface EmailForm {
    email: string;
}

export default function DevicePermissions({ device, admin, grantees }: DevicePermissionsProps) {
    const { t } = useTranslations();

    const [granteeToRevoke, setGranteeToRevoke] = useState<Grantee | null>(null);
    const [transferConfirmOpen, setTransferConfirmOpen] = useState(false);

    const grantForm = useForm<EmailForm>({ email: '' });
    const transferForm = useForm<EmailForm>({ email: '' });

    const submitGrant: FormEventHandler = (event) => {
        event.preventDefault();
        grantForm.post(store.url({ device: device.id }), {
            preserveScroll: true,
            onSuccess: () => grantForm.reset('email'),
        });
    };

    function confirmRevoke() {
        if (!granteeToRevoke) {
            return;
        }

        router.delete(destroy.url({ device: device.id, deviceUser: granteeToRevoke.id }), {
            preserveScroll: true,
            onFinish: () => setGranteeToRevoke(null),
        });
    }

    // A transferência não tem volta pela interface, então o e-mail é validado
    // pelo servidor só depois do "confirmar" — o diálogo mostra o que foi
    // digitado, e é o servidor que diz se aquela conta existe.
    function confirmTransfer() {
        transferForm.post(transfer.url({ device: device.id }), {
            preserveScroll: true,
            onSuccess: () => transferForm.reset('email'),
            onFinish: () => setTransferConfirmOpen(false),
        });
    }

    return (
        <AppLayout
            breadcrumbs={[
                { label: t('nav_devices'), href: devices.index.url() },
                { label: device.name, href: devices.show.url({ device: device.id }) },
                { label: t('device_permissions_title') },
            ]}
        >
            <Head title={`${t('device_permissions_title')} – ${device.name}`} />

            <Page>
                <PageHeader
                    title={`${t('device_permissions_title')} – ${device.name}`}
                    backHref={show.url({ device: device.id })}
                />

                <div className="rounded-[10px] border border-border bg-card p-3.5">
                    <h2 className="mt-0 mb-3">{t('device_permissions_heading')}</h2>
                    <ul className="m-0 list-none space-y-0 p-0">
                        {grantees.length === 0 ? (
                            <li className="text-muted-foreground">{t('device_permissions_empty')}</li>
                        ) : (
                            grantees.map((grantee) => (
                                <li
                                    key={grantee.id}
                                    className="flex items-center justify-between gap-2 border-b border-border py-2 last:border-b-0"
                                >
                                    <div>
                                        <strong>{grantee.user?.name}</strong>{' '}
                                        <span className="text-muted-foreground">({grantee.user?.email})</span>
                                    </div>
                                    <Button type="button" variant="outline" size="sm" onClick={() => setGranteeToRevoke(grantee)}>
                                        {t('device_permission_revoke')}
                                    </Button>
                                </li>
                            ))
                        )}
                    </ul>
                </div>

                <div className="rounded-[10px] border border-border bg-card p-3.5">
                    <h2 className="mt-0 mb-3">{t('device_permission_grant')}</h2>
                    <form onSubmit={submitGrant} className="space-y-3">
                        <FormField htmlFor="grantEmail" label={t('user_email_label')} error={grantForm.errors.email}>
                            <Input
                                id="grantEmail"
                                type="email"
                                autoComplete="off"
                                placeholder={t('user_email_placeholder')}
                                value={grantForm.data.email}
                                onChange={(event) => grantForm.setData('email', event.target.value)}
                            />
                        </FormField>

                        <Button type="submit" disabled={grantForm.processing || grantForm.data.email === ''}>
                            {t('device_permission_grant')}
                        </Button>
                    </form>
                </div>

                <div className="rounded-[10px] border border-border bg-card p-3.5">
                    <h2 className="mt-0 mb-3">{t('device_admin_heading')}</h2>

                    {admin ? (
                        <p className="mt-0 mb-3">
                            <strong>{admin.name}</strong> <span className="text-muted-foreground">({admin.email})</span>
                        </p>
                    ) : (
                        <p className="mt-0 mb-3 text-muted-foreground">{t('device_admin_none')}</p>
                    )}

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            setTransferConfirmOpen(true);
                        }}
                        className="space-y-3"
                    >
                        <FormField htmlFor="transferEmail" label={t('user_email_label')} error={transferForm.errors.email}>
                            <Input
                                id="transferEmail"
                                type="email"
                                autoComplete="off"
                                placeholder={t('user_email_placeholder')}
                                value={transferForm.data.email}
                                onChange={(event) => transferForm.setData('email', event.target.value)}
                            />
                        </FormField>

                        <Button type="submit" variant="outline" disabled={transferForm.processing || transferForm.data.email === ''}>
                            {t('device_transfer')}
                        </Button>
                    </form>
                </div>

                <ConfirmDialog
                    open={granteeToRevoke !== null}
                    onOpenChange={(nextOpen) => {
                        if (!nextOpen) {
                            setGranteeToRevoke(null);
                        }
                    }}
                    title={t('device_permission_revoke')}
                    description={t('device_permission_revoke_confirm', { name: granteeToRevoke?.user?.name ?? '' })}
                    onConfirm={confirmRevoke}
                />

                <ConfirmDialog
                    open={transferConfirmOpen}
                    onOpenChange={setTransferConfirmOpen}
                    title={t('device_transfer')}
                    description={t('device_transfer_confirm', {
                        name: transferForm.data.email,
                        email: transferForm.data.email,
                    })}
                    onConfirm={confirmTransfer}
                />
            </Page>
        </AppLayout>
    );
}
```

Nota sobre `device_transfer_confirm`: a chave tem os placeholders `:name` e `:email`, mas a tela só conhece o e-mail digitado — quem resolve o nome é o servidor. Passar o e-mail nos dois é proposital e o texto continua fazendo sentido; não invente uma busca de nome no cliente só para preencher o `:name`, porque ela seria exatamente a enumeração que o spec §6 fecha.

Em `resources/js/pages/devices/show.tsx`, dentro de `headerActions`, adicionar antes do botão de controle:

```tsx
            {abilities.managePermissions ? (
                <Button variant="outline" asChild>
                    <Link href={devices.permissions.index.url({ device: device.id })}>{t('device_permissions_title')}</Link>
                </Button>
            ) : null}
```

e acrescentar `abilities: { managePermissions: boolean }` às props de `DevicesShowProps`, desestruturando no componente. O `DeviceController::show()` passa esse array na Task 3.2 — nesta tarefa, adicione já a chave `'abilities' => ['managePermissions' => $device->isAdministeredBy(Auth::user())],` no `Inertia::render` do `show()`.

- [ ] **Step 8: Trocar a busca de membro por e-mail exato**

`app/Http/Controllers/App/PlaceMemberSearchController.php` — substituir a query por:

```php
        $email = trim((string) $request->query('email', ''));

        if ($email === '') {
            return response()->json(['data' => []]);
        }

        $existingIds = $place->placeUsers()->pluck('user_id')->all();

        $users = User::query()
            ->whereNotIn('id', $existingIds)
            ->where('email', $email)
            ->limit(1)
            ->get(['id', 'name', 'email']);

        return response()->json(['data' => $users]);
```

`app/Http/Requests/StorePlaceMemberRequest.php` — trocar a regra `user_id` por `email`:

```php
            'email' => ['required', 'string', 'email', 'exists:users,email'],
            'role' => ['required', 'string', 'in:admin,host'],
            'label' => ['nullable', 'string', 'max:255'],
```

e no `withValidator()`, resolver o usuário pelo e-mail antes de checar duplicidade:

```php
            $userId = User::query()->where('email', $this->input('email'))->value('id');

            if (! $place instanceof Place || $userId === null) {
                return;
            }
```

`app/Http/Controllers/App/PlaceMemberController::store()` — resolver o usuário pelo e-mail:

```php
        $user = User::query()->where('email', $validated['email'])->firstOrFail();

        $service->create($place, $user->id, $validated['role'], $validated['label'] ?: null);
```

Em `resources/js/pages/places/members.tsx`, a busca com `Popover` + `Command` deixa de existir. Remover os imports de `Command*`, `Popover*` e `searchMembers`, os estados `selectedUser`, `searchTerm`, `results`, `open`, `searchTimer`, o `useEffect` de busca e as funções `selectUser`/`clearSelectedUser`. O formulário passa a ser:

```tsx
    const { data, setData, post, processing, errors, reset } = useForm<AddMemberForm>({
        email: '',
        role: 'host',
        label: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url(place.id), {
            preserveScroll: true,
            onSuccess: () => reset('email', 'role', 'label'),
        });
    };
```

com `AddMemberForm` virando `{ email: string; role: string; label: string }`, e o primeiro `FormField` do formulário virando:

```tsx
                        <FormField htmlFor="memberEmail" label={t('user_email_label')} error={errors.email}>
                            <Input
                                id="memberEmail"
                                type="email"
                                autoComplete="off"
                                placeholder={t('user_email_placeholder')}
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                            />
                        </FormField>
```

O `disabled` do botão passa de `!data.user_id` para `data.email === ''`. A rota `places.members.search` continua existindo e serve à validação assíncrona opcional; se ficar sem nenhum consumidor no front, deixe-a no lugar — remover rota é escopo de outra tarefa.

- [ ] **Step 9: Rodar e ver passar**

```bash
./vendor/bin/sail npm run build
./vendor/bin/sail test --filter=DevicePermissionScreenTest
```

Esperado: PASS, 4 testes.

- [ ] **Step 10: Suíte inteira e commit**

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test && ./vendor/bin/sail npm run test:js
git add -A
git commit -m "feat: tela de permissoes do dispositivo com concessao e transferencia"
```

---

# FASE 3 — Cascata e visibilidade

Duas trilhas em paralelo. Depende da Fase 2 inteira.

---

## Trilha CASCATA

### Task 3.1: Revogação em cascata

**Files:**
- Modify: `app/Services/Device/DeviceGrantService.php`
- Test: `tests/Feature/Devices/DeviceRevokeCascadeTest.php`

Revogar corta na hora: o dispositivo sai dos locais onde aquela pessoa é admin, **exceto** se outro admin daquele local também sustentar o acesso; e os PINs originados nesses locais são apagados do equipamento.

- [ ] **Step 1: Escrever o teste (vai falhar)**

`tests/Feature/Devices/DeviceRevokeCascadeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Devices;

use App\Models\AccessCode;
use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use App\Services\Device\DeviceGrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceRevokeCascadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);
    }

    public function test_revoking_detaches_the_device_from_the_users_places(): void
    {
        $owner = User::factory()->create();
        $resident = User::factory()->create();

        $gate = $this->deviceOwnedBy($owner);
        $gate->deviceUsers()->create(['user_id' => $resident->id, 'role' => 'user']);

        $apto = Place::create(['name' => 'Apto 1']);
        PlaceUser::create(['place_id' => $apto->id, 'user_id' => $resident->id, 'role' => 'admin']);
        $gate->places()->attach($apto->id);

        app(DeviceGrantService::class)->revoke($gate, $resident);

        $this->assertFalse($gate->places()->where('places.id', $apto->id)->exists());
        $this->assertFalse($gate->fresh()->isUsableBy($resident));
    }

    public function test_device_stays_when_another_place_admin_still_holds_a_grant(): void
    {
        $owner = User::factory()->create();
        $resident = User::factory()->create();
        $spouse = User::factory()->create();

        $gate = $this->deviceOwnedBy($owner);
        $gate->deviceUsers()->create(['user_id' => $resident->id, 'role' => 'user']);
        $gate->deviceUsers()->create(['user_id' => $spouse->id, 'role' => 'user']);

        $apto = Place::create(['name' => 'Apto 1']);
        PlaceUser::create(['place_id' => $apto->id, 'user_id' => $resident->id, 'role' => 'admin']);
        PlaceUser::create(['place_id' => $apto->id, 'user_id' => $spouse->id, 'role' => 'admin']);
        $gate->places()->attach($apto->id);

        app(DeviceGrantService::class)->revoke($gate, $resident);

        $this->assertTrue(
            $gate->places()->where('places.id', $apto->id)->exists(),
            'O acesso do local só cai quando ninguém mais o sustenta.'
        );
    }

    public function test_revoking_removes_the_codes_from_the_device(): void
    {
        $owner = User::factory()->create();
        $resident = User::factory()->create();

        $gate = $this->deviceOwnedBy($owner);
        $gate->deviceUsers()->create(['user_id' => $resident->id, 'role' => 'user']);

        $apto = Place::create(['name' => 'Apto 1']);
        PlaceUser::create(['place_id' => $apto->id, 'user_id' => $resident->id, 'role' => 'admin']);
        $gate->places()->attach($apto->id);

        AccessCode::withoutEvents(fn () => AccessCode::create([
            'place_id' => $apto->id,
            'pin' => '123456',
            'start' => now()->subDay(),
            'end' => now()->addDays(5),
        ]));

        app(DeviceGrantService::class)->revoke($gate, $resident);

        // Com o dispositivo fora do local, a sincronização do place não o
        // alcança mais: é o que garante que o PIN saiu do equipamento.
        $this->assertFalse($gate->fresh()->places()->where('places.id', $apto->id)->exists());
    }

    private function deviceOwnedBy(User $user): Device
    {
        $device = Device::withoutEvents(fn (): Device => Device::create([
            'name' => 'Garagem A',
            'brand' => 'portatec',
            'external_device_id' => 'chip-a',
        ]));

        $device->deviceUsers()->create(['user_id' => $user->id, 'role' => 'admin']);

        return $device;
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

```bash
./vendor/bin/sail test --filter=DeviceRevokeCascadeTest
```

Esperado: FAIL — o `revoke()` mínimo da Task 2.2 só apaga a linha.

- [ ] **Step 3: Implementar a cascata**

Em `app/Services/Device/DeviceGrantService.php`, substituir `revoke()` por:

```php
    /**
     * Spec §6: corta na hora, sem aviso. Hóspede com estadia em andamento
     * perde o acesso a este dispositivo — foi escolha deliberada, porque o
     * síndico precisa conseguir cortar justamente no momento do conflito.
     */
    public function revoke(Device $device, User $user): void
    {
        DB::transaction(function () use ($device, $user): void {
            $device->deviceUsers()
                ->where('user_id', $user->id)
                ->where('role', DeviceRoleEnum::User)
                ->delete();

            foreach ($this->placesLosingAccess($device, $user) as $placeId) {
                $this->detachAndPurge($device, $placeId);
            }
        });
    }

    /**
     * Locais onde este usuário é admin, o dispositivo está anexado, e nenhum
     * outro admin do local sustenta o acesso.
     *
     * @return array<int, int>
     */
    private function placesLosingAccess(Device $device, User $user): array
    {
        $attachedPlaceIds = $device->places()->pluck('places.id');

        return $user->placeUsers()
            ->where('role', PlaceRoleEnum::Admin)
            ->whereIn('place_id', $attachedPlaceIds)
            ->pluck('place_id')
            ->reject(function (int $placeId) use ($device, $user): bool {
                return PlaceUser::query()
                    ->where('place_id', $placeId)
                    ->where('role', PlaceRoleEnum::Admin)
                    ->where('user_id', '!=', $user->id)
                    ->whereIn('user_id', $device->deviceUsers()->pluck('user_id'))
                    ->exists();
            })
            ->values()
            ->all();
    }

    private function detachAndPurge(Device $device, int $placeId): void
    {
        $place = Place::query()->find($placeId);

        if ($place === null) {
            return;
        }

        foreach ($place->getValidAccessCodes() as $accessCode) {
            app(AccessCodeSyncService::class)->syncDeletedAccessCode($accessCode);
        }

        $device->places()->detach($placeId);

        $device->load('places');
        $device->update(['place_id' => $device->places->first()?->id]);
    }
```

Imports a acrescentar no topo: `App\Enums\PlaceRoleEnum`, `App\Models\Place`, `App\Models\PlaceUser`, `App\Services\AccessCodeSyncService`.

**Atenção à ordem:** o purge roda **antes** do `detach`. `AccessCodeSyncService::syncDeletedAccessCode()` descobre os dispositivos pelo local; depois de desanexar, ele não alcançaria mais o equipamento e o PIN ficaria gravado na fechadura.

- [ ] **Step 4: Rodar e ver passar**

```bash
./vendor/bin/sail test --filter=DeviceRevokeCascadeTest
```

Esperado: PASS, 3 testes.

- [ ] **Step 5: Aplicar o mesmo purge no desanexar manual**

Em `app/Http/Controllers/App/PlaceDeviceController.php`, antes do `$place->devices()->detach($device->id);`, adicionar:

```php
        foreach ($place->getValidAccessCodes() as $accessCode) {
            app(AccessCodeSyncService::class)->syncDeletedAccessCode($accessCode);
        }
```

com `use App\Services\AccessCodeSyncService;` no topo.

- [ ] **Step 6: Suíte inteira e commit**

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test
git add app/Services/Device/DeviceGrantService.php app/Http/Controllers/App/PlaceDeviceController.php tests/Feature/Devices/DeviceRevokeCascadeTest.php
git commit -m "feat: revogar concessao desanexa e apaga os PINs do equipamento"
```

---

## Trilha VIEW

### Task 3.2: Visibilidade de códigos e histórico

**Files:**
- Modify: `app/Policies/AccessEventPolicy.php`
- Modify: `app/Http/Controllers/App/DeviceController.php` (`show()`)
- Modify: `resources/js/pages/devices/show.tsx`
- Test: `tests/Feature/Devices/DeviceVisibilityTest.php`

Duas regras do spec §8: o admin do dispositivo vê a lista de códigos gravados nele **sem os dígitos**; e o histórico é filtrado por origem.

- [ ] **Step 1: Escrever o teste (vai falhar)**

`tests/Feature/Devices/DeviceVisibilityTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Devices;

use App\Models\AccessCode;
use App\Models\AccessEvent;
use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);
    }

    public function test_device_admin_sees_codes_without_the_digits(): void
    {
        $owner = User::factory()->create();
        $gate = $this->deviceOwnedBy($owner);

        $apto = Place::create(['name' => 'Apto 1']);
        $gate->places()->attach($apto->id);

        AccessCode::withoutEvents(fn () => AccessCode::create([
            'place_id' => $apto->id,
            'pin' => '654321',
            'start' => now()->subDay(),
            'end' => now()->addDays(5),
        ]));

        $this->actingAs($owner)
            ->get("/app/devices/{$gate->id}")
            ->assertOk()
            ->assertSee('Apto 1')
            ->assertDontSee('654321');
    }

    public function test_resident_does_not_see_events_from_another_place(): void
    {
        $owner = User::factory()->create();
        $resident = User::factory()->create();

        $gate = $this->deviceOwnedBy($owner);

        $apto1 = Place::create(['name' => 'Apto 1']);
        $apto2 = Place::create(['name' => 'Apto 2']);
        PlaceUser::create(['place_id' => $apto1->id, 'user_id' => $resident->id, 'role' => 'admin']);
        $gate->places()->attach([$apto1->id, $apto2->id]);

        $mine = AccessCode::withoutEvents(fn () => AccessCode::create([
            'place_id' => $apto1->id, 'pin' => '111111', 'start' => now()->subDay(), 'end' => now()->addDay(),
        ]));
        $theirs = AccessCode::withoutEvents(fn () => AccessCode::create([
            'place_id' => $apto2->id, 'pin' => '222222', 'start' => now()->subDay(), 'end' => now()->addDay(),
        ]));

        $mineEvent = AccessEvent::create([
            'device_id' => $gate->id, 'access_code_id' => $mine->id, 'pin' => '111111', 'result' => 'success',
        ]);
        $theirsEvent = AccessEvent::create([
            'device_id' => $gate->id, 'access_code_id' => $theirs->id, 'pin' => '222222', 'result' => 'success',
        ]);

        $this->assertTrue($resident->can('view', $mineEvent));
        $this->assertFalse($resident->can('view', $theirsEvent));
    }

    public function test_device_admin_sees_every_event(): void
    {
        $owner = User::factory()->create();
        $gate = $this->deviceOwnedBy($owner);

        $apto = Place::create(['name' => 'Apto 1']);
        $gate->places()->attach($apto->id);

        $event = AccessEvent::create([
            'device_id' => $gate->id, 'access_code_id' => null, 'pin' => '999999', 'result' => 'invalid',
        ]);

        $this->assertTrue($owner->can('view', $event));
    }

    private function deviceOwnedBy(User $user): Device
    {
        $device = Device::withoutEvents(fn (): Device => Device::create([
            'name' => 'Pedestres 1',
            'brand' => 'portatec',
            'external_device_id' => 'chip-ped',
        ]));

        $device->deviceUsers()->create(['user_id' => $user->id, 'role' => 'admin']);

        return $device;
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

```bash
./vendor/bin/sail test --filter=DeviceVisibilityTest
```

Esperado: FAIL — hoje o histórico é liberado por vínculo com o local e a tela não lista códigos.

- [ ] **Step 3: Reescrever `AccessEventPolicy::view()`**

```php
    /**
     * Spec §8. O admin do dispositivo vê tudo. O membro de local vê o que veio
     * de um local dele — mais os eventos ambíguos que têm um candidato dele.
     * Com 16 unidades no mesmo portão de pedestres, a regra antiga fazia cada
     * morador acompanhar a movimentação de todos os outros.
     */
    public function view(User $user, AccessEvent $accessEvent): bool
    {
        $device = $accessEvent->device;

        if ($device === null) {
            return false;
        }

        if ($device->isAdministeredBy($user)) {
            return true;
        }

        $userPlaceIds = $user->placeUsers()->pluck('place_id');

        if ($accessEvent->access_code_id !== null) {
            return $accessEvent->accessCode !== null
                && $userPlaceIds->contains($accessEvent->accessCode->place_id);
        }

        $candidateIds = (array) data_get($accessEvent->metadata, 'candidate_access_code_ids', []);

        if ($candidateIds === []) {
            return false;
        }

        return AccessCode::query()
            ->whereKey($candidateIds)
            ->whereIn('place_id', $userPlaceIds)
            ->exists();
    }
```

Imports: `App\Models\AccessCode`. Remover o método privado `hasPlaceAccess()` se ficar sem uso.

- [ ] **Step 4: Listar os códigos na tela do dispositivo**

Em `app/Http/Controllers/App/DeviceController.php`, dentro de `show()`, antes do `return`:

```php
        $isDeviceAdmin = $device->isAdministeredBy(Auth::user());

        $codesOnDevice = $isDeviceAdmin
            ? AccessCode::query()
                ->with('place')
                ->whereIn('place_id', $device->places()->pluck('places.id'))
                ->where('start', '<=', now())
                ->where(fn ($query) => $query->whereNull('end')->orWhere('end', '>=', now()))
                ->get()
                // Spec §8: o dono do equipamento audita e revoga, mas não
                // ganha a credencial do hóspede de outra pessoa. Sem `pin`.
                ->map(fn (AccessCode $code): array => [
                    'id' => $code->id,
                    'place_name' => $code->place?->name,
                    'start' => $code->start?->toIso8601String(),
                    'end' => $code->end?->toIso8601String(),
                ])
                ->values()
                ->all()
            : [];
```

e passar `'codesOnDevice' => $codesOnDevice,` e `'abilities' => ['managePermissions' => $isDeviceAdmin],` no array do `Inertia::render`.

Import: `App\Models\AccessCode`.

- [ ] **Step 5: Renderizar na tela**

Em `resources/js/pages/devices/show.tsx`, acrescentar ao tipo das props:

```tsx
interface CodeOnDevice {
    id: number;
    place_name: string | null;
    start: string | null;
    end: string | null;
}
```

e o campo `codesOnDevice: CodeOnDevice[];` em `DevicesShowProps`, desestruturando no componente.

Inserir o bloco abaixo depois do card de funções do dispositivo e antes do card de comandos/syncs recentes:

```tsx
                {abilities.managePermissions ? (
                    <div className="rounded-lg border border-neutral-200 bg-white p-3.5">
                        <h2 className="mt-0">{t('device_codes_heading')}</h2>
                        {codesOnDevice.length === 0 ? (
                            <p className="m-0 text-muted-foreground">{t('device_codes_empty')}</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full border-collapse text-left">
                                    <thead>
                                        <tr className="border-b border-neutral-200">
                                            <th className="py-2 pr-3 font-medium">{t('device_codes_origin_place')}</th>
                                            <th className="py-2 font-medium">{t('device_codes_window')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {/*
                                          Spec §8: o dono do equipamento audita e revoga, mas não
                                          ganha a credencial do hóspede de outra pessoa. Nenhuma
                                          coluna de PIN aqui — nem o backend manda o dígito.
                                        */}
                                        {codesOnDevice.map((code) => (
                                            <tr key={code.id} className="border-b border-neutral-200 last:border-b-0">
                                                <td className="py-2 pr-3">{code.place_name ?? '—'}</td>
                                                <td className="py-2">
                                                    {code.start ? formatDateTime(code.start) : '—'}
                                                    {' – '}
                                                    {code.end ? formatDateTime(code.end) : '∞'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                ) : null}
```

`formatDateTime` já existe no rodapé deste arquivo — reutilize, não crie outra.

- [ ] **Step 6: Rodar e ver passar**

```bash
./vendor/bin/sail npm run build
./vendor/bin/sail test --filter=DeviceVisibilityTest
```

Esperado: PASS, 3 testes.

- [ ] **Step 7: Suíte inteira e commit**

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test
git add app/Policies/AccessEventPolicy.php app/Http/Controllers/App/DeviceController.php resources/js/pages/devices/show.tsx tests/Feature/Devices/DeviceVisibilityTest.php
git commit -m "feat: visibilidade de codigos e historico em dispositivo compartilhado"
```

---

# FASE 4 — Limpeza final

Depende de tudo. Uma tarefa só.

---

### Task 4.1: Remover `place_device_functions`

**Files:**
- Create: `database/migrations/2026_09_08_000002_drop_place_device_functions_table.php`
- Delete: `app/Models/PlaceDeviceFunction.php`
- Delete: `app/Services/Device/DevicePlaceFunctionSyncService.php`
- Modify: `app/Models/Place.php`, `app/Models/Device.php`, `app/Models/DeviceFunction.php` (relações)
- Modify: `app/Http/Controllers/App/DeviceControlController.php:80`
- Modify: `app/Services/Device/DeviceCommandService.php` (linhas 53, 149, 185, 210, 288)
- Modify: `database/seeders/UserSeeder.php`
- Modify: `tests/Unit/DeviceCommandServicePayloadMappingTest.php`, `tests/Feature/Devices/DevicesTest.php`

A tabela nunca implementou granularidade — é preenchida com todas as funções × todos os locais. Mas ela **é lida** em cinco pontos, todos como fallback para descobrir a que locais o dispositivo pertence. Cada um vira a relação `places` do dispositivo.

- [ ] **Step 1: Escrever o teste de não-regressão do fallback (vai falhar)**

Em `tests/Unit/DeviceCommandServicePayloadMappingTest.php`, adicionar:

```php
    public function test_command_log_resolves_place_from_device_places(): void
    {
        $place = \App\Models\Place::create(['name' => 'Apto 1']);

        $device = \App\Models\Device::withoutEvents(fn () => \App\Models\Device::create([
            'name' => 'Garagem A',
            'brand' => 'portatec',
            'external_device_id' => 'chip-a',
        ]));
        $device->places()->attach($place->id);

        $function = \App\Models\DeviceFunction::create([
            'device_id' => $device->id, 'type' => 'switch', 'pin' => '2',
        ]);

        $this->assertSame($place->id, $device->places()->value('places.id'));
        $this->assertSame($device->id, $function->device_id);
    }
```

- [ ] **Step 2: Rodar e ver passar (é uma âncora, não um bug)**

```bash
./vendor/bin/sail test --filter=test_command_log_resolves_place_from_device_places
```

Esperado: PASS. Este teste fixa o comportamento que os cinco fallbacks passam a ter.

- [ ] **Step 3: Trocar os cinco fallbacks**

Em cada um dos pontos abaixo, apagar o `?? $device->placeDeviceFunctions()->value('place_id')` (ou o `->merge($device->placeDeviceFunctions()->pluck('place_id'))`), deixando só `places` e `place_id`:

- `app/Http/Controllers/App/DeviceControlController.php:80`
- `app/Services/Device/DeviceCommandService.php:53`
- `app/Services/Device/DeviceCommandService.php:149`
- `app/Services/Device/DeviceCommandService.php:185`
- `app/Services/Device/DeviceCommandService.php:210` (já reescrito na Task 1.2 — confirme que não sobrou referência)

E em `app/Services/Device/DeviceCommandService.php:288`, trocar
`$deviceFunction->placeDeviceFunctions->pluck('place_id')` por
`$deviceFunction->device->places->pluck('id')`.

- [ ] **Step 4: Confirmar que não sobrou leitor**

```bash
grep -rn "placeDeviceFunctions\|PlaceDeviceFunction" app resources/js || echo "limpo"
```

Esperado: só as declarações de relação nos models, que saem no próximo passo.

- [ ] **Step 5: Apagar model, service e relações**

```bash
rm app/Models/PlaceDeviceFunction.php app/Services/Device/DevicePlaceFunctionSyncService.php
```

Remover os métodos `placeDeviceFunctions()` de `Place`, `Device` e `DeviceFunction`, e as linhas de `UserSeeder.php` que criam as pivôs.

- [ ] **Step 6: Escrever a migração com o backfill de segurança**

`database/migrations/2026_09_08_000002_drop_place_device_functions_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('place_device_functions')) {
            return;
        }

        // As duas tabelas são escritas juntas hoje, mas nada no banco garante
        // isso. Um dispositivo que só constasse na tabela antiga perderia o
        // vínculo com o local em silêncio — daí o backfill antes do drop.
        $pairs = DB::table('place_device_functions')
            ->join('device_functions', 'device_functions.id', '=', 'place_device_functions.device_function_id')
            ->select('place_device_functions.place_id', 'device_functions.device_id')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            $exists = DB::table('device_place')
                ->where('place_id', $pair->place_id)
                ->where('device_id', $pair->device_id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('device_place')->insert([
                'place_id' => $pair->place_id,
                'device_id' => $pair->device_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::drop('place_device_functions');
    }

    public function down(): void
    {
        Schema::create('place_device_functions', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_function_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }
};
```

- [ ] **Step 7: Rodar do zero**

```bash
./vendor/bin/sail artisan migrate:fresh
./vendor/bin/sail pint && ./vendor/bin/sail test
```

Esperado: verde. Corrija os testes que ainda importavam `PlaceDeviceFunction`.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "refactor: remove place_device_functions"
```

---

## Verificação final

Depois da Fase 4, com tudo mergeado:

- [ ] `./vendor/bin/sail artisan migrate:fresh && ./vendor/bin/sail test` — verde
- [ ] `./vendor/bin/sail npm run build` — sem erro
- [ ] `./vendor/bin/sail npm run test:js` — verde
- [ ] `grep -rn "PlaceClone\|placeDeviceFunctions" app routes resources/js tests` — vazio
- [ ] Percorrer o spec §1 a §9 e confirmar que cada decisão tem código correspondente

---

## Cobertura do spec

| Spec | Tarefa |
|---|---|
| §4 modelo de dados + backfill | 0.1 |
| §5 `DevicePolicy` | 1.3 |
| §5 `PlacePolicy::update` | 1.4 |
| §5 `DeviceController::update` para de sincronizar | 2.1 |
| §5 tela de anexar escopada | 2.1 |
| §6 conceder | 2.2 |
| §6 revogar em cascata | 3.1 |
| §6 transferir | 2.2 |
| §6 escolher pessoa por e-mail exato | 2.2 |
| §7 unicidade de PIN | 1.1 |
| §7 colisão herdada tolerada | 1.1 (não bloqueia) + 1.2 (evento sem vínculo) |
| §7 resolução do evento | 1.2 |
| §8 códigos sem dígitos | 3.2 |
| §8 histórico filtrado | 3.2 |
| §9 remover clone | 1.5 |
| §9 remover `place_device_functions` | 4.1 |
