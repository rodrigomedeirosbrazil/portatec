# Status da fechadura Tuya — Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mostrar trancada/destrancada, bateria e alerta da fechadura Tuya nas telas de controle e
no detalhe do dispositivo, atualizando em tempo real pelo MQTT.

**Architecture:** O status continua em `devices.tuya_status_payload`, agora mesclado por `code`
(`TuyaStatusPayload`) e sem os DPs sensíveis. Um objeto de valor (`TuyaLockStatus`) deriva o que a
tela mostra. O `TuyaMqttService` dispara `PlaceTuyaLockStatusEvent` pelo Reverb quando o status
derivado muda; o front assina com um hook próprio e exibe um painel.

**Tech Stack:** Laravel 12 / PHP 8.4, PHPUnit 11, Reverb, Inertia + React + TypeScript, Vitest.

**Spec:** `docs/superpowers/specs/2026-10-07-status-fechadura-tuya-design.md`

**Branch:** `docs/status-fechadura-tuya` (já contém o AGENTS.md e a spec). PR único no final.

**Regras do repositório (AGENTS.md):** todo comando PHP/Composer/Artisan/NPM roda via
`./vendor/bin/sail`. Não leia nem edite o `.env`. Rode `./vendor/bin/sail pint` e
`./vendor/bin/sail test` antes de considerar pronto.

---

## Mapa de arquivos

| Arquivo | Ação | Responsabilidade |
|---|---|---|
| `app/Services/Tuya/TuyaStatusPayload.php` | criar | regra única de gravação do status (merge, filtro de sensíveis, `t`) |
| `app/Services/Tuya/DTOs/TuyaLockStatus.php` | criar | deriva `locked`/`battery`/`alert`/`updatedAt` do payload |
| `app/Models/Device.php` | modificar | atalho `tuyaLockStatus()` |
| `app/Events/PlaceTuyaLockStatusEvent.php` | criar | broadcast do status da fechadura |
| `app/Services/Tuya/TuyaMqttService.php` | modificar | merge, broadcast de fechadura, broadcast de online/offline |
| `app/Services/Tuya/TuyaIntegrationService.php` | modificar | snapshot usa merge |
| `app/Http/Controllers/App/TuyaConnectController.php` | modificar | importação usa merge |
| `database/migrations/2026_10_07_000001_scrub_sensitive_tuya_status_payload.php` | criar | limpa DPs sensíveis já gravados |
| `app/Http/Resources/DeviceResource.php` | modificar | `lock_status` |
| `app/Http/Controllers/App/DeviceControlController.php` | modificar | `lock_status`, `supports_tuya_temporary_password` |
| `app/Http/Controllers/App/PlaceControlController.php` | modificar | idem |
| `app/Http/Controllers/App/DeviceController.php` | modificar | `placeId` no `show` |
| `resources/lang/pt_BR/app.php` | modificar | textos do painel |
| `resources/js/types/models.ts` | modificar | tipo `TuyaLockStatus`, `Device.lock_status` |
| `resources/js/hooks/tuya-lock-status-reducer.ts` | criar | estado puro por dispositivo |
| `resources/js/hooks/use-tuya-lock-status.ts` | criar | assina `.PlaceTuyaLockStatus` |
| `resources/js/lib/format-relative.ts` | criar | "há 5 minutos" |
| `resources/js/components/device-control/tuya-lock-status-panel.tsx` | criar | painel |
| `resources/js/pages/devices/control.tsx`, `resources/js/pages/places/control.tsx`, `resources/js/pages/devices/show.tsx` | modificar | usar o painel |
| `AGENTS.md` | modificar | registrar as classes novas |

---

### Task 1: `TuyaStatusPayload`

**Files:**
- Create: `app/Services/Tuya/TuyaStatusPayload.php`
- Test: `tests/Unit/TuyaStatusPayloadTest.php`

- [ ] **Step 1: Escrever os testes**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tuya\TuyaStatusPayload;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TuyaStatusPayloadTest extends TestCase
{
    public function test_it_merges_by_code_and_keeps_entries_that_did_not_change(): void
    {
        $current = [
            ['code' => 'lock_motor_state', 'value' => false, 't' => 1000],
            ['code' => 'residual_electricity', 'value' => 51, 't' => 1000],
        ];

        $merged = TuyaStatusPayload::merge($current, [
            ['code' => 'lock_motor_state', 'value' => true, 't' => 2000],
        ]);

        $this->assertSame([
            ['code' => 'lock_motor_state', 'value' => true, 't' => 2000],
            ['code' => 'residual_electricity', 'value' => 51, 't' => 1000],
        ], $merged);
    }

    public function test_it_drops_entries_without_a_code(): void
    {
        $merged = TuyaStatusPayload::merge([], [
            ['46' => true],
            ['dpId' => 47, 'value' => true],
            ['code' => '', 'value' => 1],
            'not-an-array',
        ]);

        $this->assertSame([], $merged);
    }

    public function test_it_never_stores_sensitive_codes_and_removes_them_from_current(): void
    {
        $current = [
            ['code' => 'ble_unlock_check', 'value' => 'secret', 't' => 1000],
            ['code' => 'lock_motor_state', 'value' => false, 't' => 1000],
        ];

        $merged = TuyaStatusPayload::merge($current, [
            ['code' => 'check_code_set', 'value' => 'secret', 't' => 2000],
            ['code' => 'ble_unlock_check', 'value' => 'secret', 't' => 2000],
        ]);

        $this->assertSame([['code' => 'lock_motor_state', 'value' => false, 't' => 1000]], $merged);
    }

    public function test_it_drops_extra_keys_from_the_event(): void
    {
        $merged = TuyaStatusPayload::merge([], [
            ['47' => true, 'code' => 'lock_motor_state', 't' => 2000, 'value' => true],
        ]);

        $this->assertSame([['code' => 'lock_motor_state', 'value' => true, 't' => 2000]], $merged);
    }

    public function test_without_t_an_unchanged_value_keeps_the_previous_t(): void
    {
        $merged = TuyaStatusPayload::merge(
            [['code' => 'lock_motor_state', 'value' => false, 't' => 1000]],
            [['code' => 'lock_motor_state', 'value' => false]],
        );

        $this->assertSame(1000, $merged[0]['t']);
    }

    public function test_without_t_a_changed_value_uses_now(): void
    {
        Carbon::setTestNow('2026-10-07 22:00:00');

        $merged = TuyaStatusPayload::merge(
            [['code' => 'lock_motor_state', 'value' => false, 't' => 1000]],
            [['code' => 'lock_motor_state', 'value' => true]],
        );

        $this->assertSame(Carbon::parse('2026-10-07 22:00:00')->getTimestampMs(), $merged[0]['t']);
    }

    public function test_without_t_a_new_entry_has_null_t(): void
    {
        $merged = TuyaStatusPayload::merge([], [['code' => 'residual_electricity', 'value' => 51]]);

        $this->assertSame([['code' => 'residual_electricity', 'value' => 51, 't' => null]], $merged);
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `./vendor/bin/sail artisan test --filter=TuyaStatusPayloadTest`
Expected: FAIL — `Class "App\Services\Tuya\TuyaStatusPayload" not found`.

- [ ] **Step 3: Implementar**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tuya;

/**
 * Regra única de como o status Tuya é gravado em `devices.tuya_status_payload`.
 *
 * Eventos MQTT trazem só os DPs que mudaram (AGENTS.md §11.5), então o status é mesclado por
 * `code` em vez de substituído. DPs sem `code` e os sensíveis nunca são gravados.
 */
final class TuyaStatusPayload
{
    /**
     * `ble_unlock_check` carrega em claro o código que abre a fechadura por Bluetooth
     * (AGENTS.md §11.6.1); `check_code_set` é o DP que grava esse código.
     */
    public const SENSITIVE_CODES = ['ble_unlock_check', 'check_code_set'];

    /**
     * @param  array<int|string, mixed>  $current
     * @param  array<int|string, mixed>  $incoming
     * @return list<array{code: string, value: mixed, t: int|null}>
     */
    public static function merge(array $current, array $incoming): array
    {
        $byCode = [];

        foreach ($current as $entry) {
            $code = self::storableCode($entry);

            if ($code !== null) {
                $byCode[$code] = [
                    'code' => $code,
                    'value' => $entry['value'] ?? null,
                    't' => self::timestamp($entry['t'] ?? null),
                ];
            }
        }

        foreach ($incoming as $entry) {
            $code = self::storableCode($entry);

            if ($code === null) {
                continue;
            }

            $value = $entry['value'] ?? null;
            $t = self::timestamp($entry['t'] ?? null);
            $previous = $byCode[$code] ?? null;

            // O snapshot do /devices/detail não traz `t`. Valor igual não é mudança; valor
            // diferente foi percebido agora.
            if ($t === null && $previous !== null) {
                $t = $previous['value'] === $value ? $previous['t'] : (int) now()->getTimestampMs();
            }

            $byCode[$code] = ['code' => $code, 'value' => $value, 't' => $t];
        }

        return array_values($byCode);
    }

    private static function storableCode(mixed $entry): ?string
    {
        if (! is_array($entry)) {
            return null;
        }

        $code = $entry['code'] ?? null;

        if (! is_string($code) || $code === '' || in_array($code, self::SENSITIVE_CODES, true)) {
            return null;
        }

        return $code;
    }

    private static function timestamp(mixed $t): ?int
    {
        return is_numeric($t) ? (int) $t : null;
    }
}
```

- [ ] **Step 4: Rodar e ver passar**

Run: `./vendor/bin/sail artisan test --filter=TuyaStatusPayloadTest`
Expected: PASS (7 testes).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Tuya/TuyaStatusPayload.php tests/Unit/TuyaStatusPayloadTest.php
git commit -m "feat: TuyaStatusPayload mescla o status por code e descarta DPs sensiveis"
```

---

### Task 2: `TuyaLockStatus` e `Device::tuyaLockStatus()`

**Files:**
- Create: `app/Services/Tuya/DTOs/TuyaLockStatus.php`
- Modify: `app/Models/Device.php` (junto de `isTuyaLock()`, perto da linha 205)
- Test: `tests/Unit/TuyaLockStatusTest.php`

- [ ] **Step 1: Escrever os testes**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\DeviceBrandEnum;
use App\Models\Device;
use App\Services\Tuya\DTOs\TuyaLockStatus;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TuyaLockStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-07 22:00:00');
    }

    public function test_it_is_null_for_a_device_that_is_not_a_tuya_lock(): void
    {
        $this->assertNull(TuyaLockStatus::fromDevice(new Device(['brand' => DeviceBrandEnum::Portatec])));
        $this->assertNull(TuyaLockStatus::fromDevice(new Device([
            'brand' => DeviceBrandEnum::Tuya,
            'tuya_category' => 'kg',
        ])));
    }

    public function test_motor_state_true_means_unlocked(): void
    {
        $status = TuyaLockStatus::fromDevice($this->lock([
            ['code' => 'lock_motor_state', 'value' => true, 't' => $this->ms('2026-10-07 21:59:00')],
        ]));

        $this->assertFalse($status->locked);
        $this->assertSame('2026-10-07T21:59:00+00:00', $status->toArray()['updated_at']);
    }

    public function test_motor_state_false_means_locked(): void
    {
        $status = TuyaLockStatus::fromDevice($this->lock([
            ['code' => 'lock_motor_state', 'value' => false, 't' => null],
        ]));

        $this->assertTrue($status->locked);
        $this->assertNull($status->updatedAt);
    }

    public function test_everything_is_unknown_without_status(): void
    {
        $this->assertSame(
            ['locked' => null, 'battery' => null, 'alert' => null, 'updated_at' => null],
            TuyaLockStatus::fromDevice($this->lock([]))->toArray(),
        );
    }

    public function test_battery_outside_0_to_100_is_unknown(): void
    {
        $this->assertSame(51, TuyaLockStatus::fromDevice($this->lock([
            ['code' => 'residual_electricity', 'value' => 51, 't' => null],
        ]))->battery);

        $this->assertNull(TuyaLockStatus::fromDevice($this->lock([
            ['code' => 'residual_electricity', 'value' => -1, 't' => null],
        ]))->battery);
    }

    public function test_a_relevant_alert_within_24_hours_is_shown(): void
    {
        $status = TuyaLockStatus::fromDevice($this->lock([
            ['code' => 'alarm_lock', 'value' => 'pry', 't' => $this->ms('2026-10-07 10:00:00')],
        ]));

        $this->assertSame('pry', $status->alert);
    }

    public function test_an_alert_older_than_24_hours_is_not_shown(): void
    {
        $status = TuyaLockStatus::fromDevice($this->lock([
            ['code' => 'alarm_lock', 'value' => 'pry', 't' => $this->ms('2026-10-06 21:00:00')],
        ]));

        $this->assertNull($status->alert);
    }

    public function test_an_alert_without_t_is_not_shown(): void
    {
        $status = TuyaLockStatus::fromDevice($this->lock([
            ['code' => 'alarm_lock', 'value' => 'pry', 't' => null],
        ]));

        $this->assertNull($status->alert);
    }

    public function test_failed_attempts_are_not_alerts(): void
    {
        $status = TuyaLockStatus::fromDevice($this->lock([
            ['code' => 'alarm_lock', 'value' => 'wrong_finger', 't' => $this->ms('2026-10-07 21:00:00')],
        ]));

        $this->assertNull($status->alert);
    }

    public function test_the_device_shortcut_returns_the_same_status(): void
    {
        $device = $this->lock([['code' => 'lock_motor_state', 'value' => false, 't' => null]]);

        $this->assertEquals(TuyaLockStatus::fromDevice($device), $device->tuyaLockStatus());
    }

    /** @param list<array<string, mixed>> $payload */
    private function lock(array $payload): Device
    {
        return new Device([
            'brand' => DeviceBrandEnum::Tuya,
            'tuya_category' => 'ms',
            'tuya_status_payload' => $payload,
        ]);
    }

    private function ms(string $datetime): int
    {
        return Carbon::parse($datetime)->getTimestampMs();
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `./vendor/bin/sail artisan test --filter=TuyaLockStatusTest`
Expected: FAIL — `Class "App\Services\Tuya\DTOs\TuyaLockStatus" not found`.

- [ ] **Step 3: Implementar o objeto de valor**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tuya\DTOs;

use App\Models\Device;
use Carbon\CarbonImmutable;

/**
 * O que a tela mostra de uma fechadura Tuya, derivado de `devices.tuya_status_payload`.
 * Cada campo pode ser `null`: o modelo pode não expor o DP, ou ele ainda não foi reportado.
 */
final readonly class TuyaLockStatus
{
    /** Alarmes que pedem atenção. Tentativas erradas (`wrong_*`) e `key_in` seriam ruído. */
    public const RELEVANT_ALERTS = [
        'pry',
        'shock',
        'low_battery',
        'power_off',
        'too_hot',
        'unclosed_time',
        'tongue_bad',
        'tongue_not_out',
    ];

    private const ALERT_WINDOW_HOURS = 24;

    public function __construct(
        public ?bool $locked,
        public ?int $battery,
        public ?string $alert,
        public ?CarbonImmutable $updatedAt,
    ) {}

    public static function fromDevice(Device $device): ?self
    {
        if (! $device->isTuyaLock()) {
            return null;
        }

        $entries = collect($device->tuya_status_payload ?? [])
            ->filter(fn (mixed $entry): bool => is_array($entry) && is_string($entry['code'] ?? null))
            ->keyBy('code');

        $motor = $entries->get('lock_motor_state');
        $motorValue = $motor['value'] ?? null;

        return new self(
            // O DP 47 é `true` quando o motor está ABERTO (observado no hardware).
            locked: is_bool($motorValue) ? ! $motorValue : null,
            battery: self::battery($entries->get('residual_electricity')['value'] ?? null),
            alert: self::recentAlert($entries->get('alarm_lock')),
            updatedAt: self::time($motor['t'] ?? null),
        );
    }

    /** @return array{locked: bool|null, battery: int|null, alert: string|null, updated_at: string|null} */
    public function toArray(): array
    {
        return [
            'locked' => $this->locked,
            'battery' => $this->battery,
            'alert' => $this->alert,
            'updated_at' => $this->updatedAt?->toIso8601String(),
        ];
    }

    private static function battery(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $level = (int) $value;

        return $level >= 0 && $level <= 100 ? $level : null;
    }

    /** @param array<string, mixed>|null $entry */
    private static function recentAlert(?array $entry): ?string
    {
        $value = $entry['value'] ?? null;
        $at = self::time($entry['t'] ?? null);

        if (! in_array($value, self::RELEVANT_ALERTS, true) || $at === null) {
            return null;
        }

        return $at->greaterThanOrEqualTo(now()->subHours(self::ALERT_WINDOW_HOURS)) ? $value : null;
    }

    private static function time(mixed $milliseconds): ?CarbonImmutable
    {
        return is_numeric($milliseconds)
            ? CarbonImmutable::createFromTimestampMs((int) $milliseconds)
            : null;
    }
}
```

- [ ] **Step 4: Adicionar o atalho no model**

Em `app/Models/Device.php`, importar `use App\Services\Tuya\DTOs\TuyaLockStatus;` e adicionar logo
depois de `isTuyaLock()`:

```php
    /** Status derivado para a tela; `null` quando o dispositivo não é fechadura Tuya. */
    public function tuyaLockStatus(): ?TuyaLockStatus
    {
        return TuyaLockStatus::fromDevice($this);
    }
```

- [ ] **Step 5: Rodar e ver passar**

Run: `./vendor/bin/sail artisan test --filter=TuyaLockStatusTest`
Expected: PASS (10 testes).

- [ ] **Step 6: Commit**

```bash
git add app/Services/Tuya/DTOs/TuyaLockStatus.php app/Models/Device.php tests/Unit/TuyaLockStatusTest.php
git commit -m "feat: TuyaLockStatus deriva trancada, bateria e alerta da fechadura"
```

---

### Task 3: Gravar o status com merge nos três pontos

**Files:**
- Modify: `app/Services/Tuya/TuyaMqttService.php` (`handleDeviceReport`)
- Modify: `app/Services/Tuya/TuyaIntegrationService.php:83` (`refreshDeviceSnapshot`)
- Modify: `app/Http/Controllers/App/TuyaConnectController.php:127` (importação)
- Test: `tests/Unit/TuyaMqttServiceTest.php`

- [ ] **Step 1: Ajustar o teste existente e escrever os novos**

Em `tests/Unit/TuyaMqttServiceTest.php`, o teste `test_it_stores_the_reported_status_of_a_device`
passa a esperar o formato normalizado (com `t`):

```php
        $device->refresh();
        $this->assertSame(
            [['code' => 'lock_motor_state', 'value' => true, 't' => null]],
            $device->tuya_status_payload,
        );
```

E acrescentar:

```php
    public function test_a_partial_report_keeps_the_other_reported_codes(): void
    {
        $device = Device::create([
            'name' => 'Fechadura',
            'brand' => DeviceBrandEnum::Tuya,
            'external_device_id' => 'dev-3',
            'tuya_status_payload' => [
                ['code' => 'residual_electricity', 'value' => 51, 't' => 1000],
                ['code' => 'lock_motor_state', 'value' => false, 't' => 1000],
            ],
        ]);

        (new TuyaMqttService)->handleMessage([
            'protocol' => 4,
            'data' => [
                'devId' => 'dev-3',
                'status' => [['47' => true, 'code' => 'lock_motor_state', 'value' => true, 't' => 2000]],
            ],
        ]);

        $this->assertSame([
            ['code' => 'residual_electricity', 'value' => 51, 't' => 1000],
            ['code' => 'lock_motor_state', 'value' => true, 't' => 2000],
        ], $device->refresh()->tuya_status_payload);
    }

    public function test_it_never_stores_the_ble_unlock_check(): void
    {
        $device = Device::create([
            'name' => 'Fechadura',
            'brand' => DeviceBrandEnum::Tuya,
            'external_device_id' => 'dev-4',
            'tuya_status_payload' => [],
        ]);

        (new TuyaMqttService)->handleMessage([
            'protocol' => 4,
            'data' => [
                'devId' => 'dev-4',
                'status' => [['code' => 'ble_unlock_check', 'value' => 'AAH//w==', 't' => 2000]],
            ],
        ]);

        $this->assertSame([], $device->refresh()->tuya_status_payload);
    }
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `./vendor/bin/sail artisan test --filter=TuyaMqttServiceTest`
Expected: FAIL nos três testes acima (o payload ainda é substituído).

- [ ] **Step 3: Usar o merge no MQTT**

Em `TuyaMqttService::handleDeviceReport`, trocar o `forceFill` por:

```php
        $device->forceFill([
            'tuya_status_payload' => TuyaStatusPayload::merge($device->tuya_status_payload ?? [], $status),
            'last_sync' => now(),
        ])->save();
```

(`TuyaStatusPayload` está no mesmo namespace `App\Services\Tuya`, não precisa de `use`.)

- [ ] **Step 4: Usar o merge no snapshot**

Em `TuyaIntegrationService::refreshDeviceSnapshot`, trocar a linha
`'tuya_status_payload' => $snapshot->status,` por:

```php
            'tuya_status_payload' => TuyaStatusPayload::merge($device->tuya_status_payload ?? [], $snapshot->status),
```

- [ ] **Step 5: Usar o merge na importação**

Em `TuyaConnectController`, importar `use App\Services\Tuya\TuyaStatusPayload;` e trocar
`'tuya_status_payload' => $meta['status'] ?? [],` por:

```php
                    'tuya_status_payload' => TuyaStatusPayload::merge([], $meta['status'] ?? []),
```

- [ ] **Step 6: Rodar e ver passar**

Run: `./vendor/bin/sail artisan test --filter='TuyaMqttServiceTest|TuyaConnectWizardTest|TuyaTokenRefreshTest'`
Expected: PASS. Se algum teste do wizard comparar `tuya_status_payload` exato, atualize a
expectativa para o formato `{code, value, t}`.

- [ ] **Step 7: Commit**

```bash
git add app/Services/Tuya/TuyaMqttService.php app/Services/Tuya/TuyaIntegrationService.php app/Http/Controllers/App/TuyaConnectController.php tests/Unit/TuyaMqttServiceTest.php
git commit -m "fix: status Tuya e mesclado por code em vez de sobrescrito a cada evento"
```

---

### Task 4: Migration de limpeza dos DPs sensíveis

**Files:**
- Create: `database/migrations/2026_10_07_000001_scrub_sensitive_tuya_status_payload.php`
- Test: `tests/Feature/ScrubSensitiveTuyaStatusPayloadMigrationTest.php`

- [ ] **Step 1: Escrever o teste**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeviceBrandEnum;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScrubSensitiveTuyaStatusPayloadMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_removes_the_ble_unlock_check_and_keeps_the_other_codes(): void
    {
        $device = Device::create([
            'name' => 'Fechadura',
            'brand' => DeviceBrandEnum::Tuya,
            'external_device_id' => 'dev-1',
            'tuya_status_payload' => [
                ['code' => 'ble_unlock_check', 'value' => 'AAH//w==', 't' => 1000],
                ['47' => false, 'code' => 'lock_motor_state', 'value' => false, 't' => 1000],
            ],
        ]);

        $migration = require database_path('migrations/2026_10_07_000001_scrub_sensitive_tuya_status_payload.php');
        $migration->up();

        $this->assertSame(
            [['code' => 'lock_motor_state', 'value' => false, 't' => 1000]],
            $device->refresh()->tuya_status_payload,
        );
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `./vendor/bin/sail artisan test --filter=ScrubSensitiveTuyaStatusPayloadMigrationTest`
Expected: FAIL — arquivo de migration não encontrado.

- [ ] **Step 3: Implementar a migration**

```php
<?php

use App\Services\Tuya\TuyaStatusPayload;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove de `tuya_status_payload` os DPs sensíveis já gravados — o `ble_unlock_check` carrega em
 * claro o código que abre a fechadura por Bluetooth (AGENTS.md §11.6.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('devices')
            ->where('brand', 'tuya')
            ->whereNotNull('tuya_status_payload')
            ->orderBy('id')
            ->each(function (object $row): void {
                $payload = json_decode((string) $row->tuya_status_payload, true);

                if (! is_array($payload)) {
                    return;
                }

                DB::table('devices')
                    ->where('id', $row->id)
                    ->update(['tuya_status_payload' => json_encode(TuyaStatusPayload::merge($payload, []))]);
            });
    }

    /** Nada a restaurar: o dado removido não deveria ter sido gravado. */
    public function down(): void {}
};
```

- [ ] **Step 4: Rodar e ver passar**

Run: `./vendor/bin/sail artisan test --filter=ScrubSensitiveTuyaStatusPayloadMigrationTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_10_07_000001_scrub_sensitive_tuya_status_payload.php tests/Feature/ScrubSensitiveTuyaStatusPayloadMigrationTest.php
git commit -m "fix: migration remove o codigo BLE ja gravado no status Tuya"
```

---

### Task 5: Broadcast do status da fechadura e do online/offline

**Files:**
- Create: `app/Events/PlaceTuyaLockStatusEvent.php`
- Modify: `app/Services/Tuya/TuyaMqttService.php` (`handleDeviceReport`, `handleBizEvent`)
- Test: `tests/Unit/TuyaMqttServiceTest.php`

- [ ] **Step 1: Escrever os testes**

Acrescentar em `tests/Unit/TuyaMqttServiceTest.php` (imports: `App\Events\PlaceDeviceStatusEvent`,
`App\Events\PlaceTuyaLockStatusEvent`, `App\Models\Place`, `Illuminate\Support\Facades\Event`):

```php
    public function test_it_broadcasts_the_lock_status_to_every_place_when_it_changes(): void
    {
        Event::fake([PlaceTuyaLockStatusEvent::class]);

        $device = $this->tuyaLock('dev-10', [['code' => 'lock_motor_state', 'value' => false, 't' => 1000]]);
        $first = Place::create(['name' => 'Casa 1']);
        $second = Place::create(['name' => 'Casa 2']);
        $device->places()->attach([$first->id, $second->id]);

        $this->report('dev-10', [['code' => 'lock_motor_state', 'value' => true, 't' => 2000]]);

        Event::assertDispatchedTimes(PlaceTuyaLockStatusEvent::class, 2);
        Event::assertDispatched(
            PlaceTuyaLockStatusEvent::class,
            fn (PlaceTuyaLockStatusEvent $event): bool => $event->placeId === $first->id
                && $event->deviceId === $device->id
                && $event->status['locked'] === false,
        );
    }

    public function test_it_does_not_broadcast_when_the_lock_status_did_not_change(): void
    {
        Event::fake([PlaceTuyaLockStatusEvent::class]);

        $device = $this->tuyaLock('dev-11', [['code' => 'lock_motor_state', 'value' => false, 't' => 1000]]);
        $device->places()->attach(Place::create(['name' => 'Casa'])->id);

        // Mesmo valor e mesmo `t`: é o mesmo relatório chegando por /sta e /pen.
        $this->report('dev-11', [['code' => 'lock_motor_state', 'value' => false, 't' => 1000]]);
        // DP que o painel não usa.
        $this->report('dev-11', [['code' => 'doorbell_volume', 'value' => 'mute', 't' => 3000]]);

        Event::assertNotDispatched(PlaceTuyaLockStatusEvent::class);
    }

    public function test_it_does_not_broadcast_lock_status_for_a_device_that_is_not_a_lock(): void
    {
        Event::fake([PlaceTuyaLockStatusEvent::class]);

        $device = Device::create([
            'name' => 'Portão',
            'brand' => DeviceBrandEnum::Tuya,
            'tuya_category' => 'tdq',
            'external_device_id' => 'dev-12',
            'tuya_status_payload' => [],
        ]);
        $device->places()->attach(Place::create(['name' => 'Casa'])->id);

        $this->report('dev-12', [['code' => 'switch_1', 'value' => true, 't' => 2000]]);

        Event::assertNotDispatched(PlaceTuyaLockStatusEvent::class);
    }

    public function test_it_broadcasts_availability_only_when_online_changes(): void
    {
        Event::fake([PlaceDeviceStatusEvent::class]);

        $device = Device::create([
            'name' => 'Fechadura',
            'brand' => DeviceBrandEnum::Tuya,
            'external_device_id' => 'dev-13',
            'tuya_online' => true,
        ]);
        $place = Place::create(['name' => 'Casa']);
        $device->places()->attach($place->id);

        $service = new TuyaMqttService;
        $service->handleMessage(['protocol' => 20, 'data' => ['bizCode' => 'online', 'bizData' => ['devId' => 'dev-13']]]);
        Event::assertNotDispatched(PlaceDeviceStatusEvent::class);

        $service->handleMessage(['protocol' => 20, 'data' => ['bizCode' => 'offline', 'bizData' => ['devId' => 'dev-13']]]);
        Event::assertDispatched(
            PlaceDeviceStatusEvent::class,
            fn (PlaceDeviceStatusEvent $event): bool => $event->placeId === $place->id && $event->isAvailable === false,
        );
    }

    /** @param list<array<string, mixed>> $payload */
    private function tuyaLock(string $externalId, array $payload): Device
    {
        return Device::create([
            'name' => 'Fechadura',
            'brand' => DeviceBrandEnum::Tuya,
            'tuya_category' => 'ms',
            'external_device_id' => $externalId,
            'tuya_status_payload' => $payload,
        ]);
    }

    /** @param list<array<string, mixed>> $status */
    private function report(string $externalId, array $status): void
    {
        (new TuyaMqttService)->handleMessage([
            'protocol' => 4,
            'data' => ['devId' => $externalId, 'status' => $status],
        ]);
    }
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `./vendor/bin/sail artisan test --filter=TuyaMqttServiceTest`
Expected: FAIL — `Class "App\Events\PlaceTuyaLockStatusEvent" not found`.

- [ ] **Step 3: Criar o evento**

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Status da fechadura Tuya mudou. Vai no mesmo canal do `PlaceDeviceStatusEvent`, já autorizado
 * em routes/channels.php.
 */
class PlaceTuyaLockStatusEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** @param array{locked: bool|null, battery: int|null, alert: string|null, updated_at: string|null} $status */
    public function __construct(
        public int $placeId,
        public int $deviceId,
        public array $status,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("Place.Device.Status.{$this->placeId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PlaceTuyaLockStatus';
    }
}
```

- [ ] **Step 4: Disparar no `TuyaMqttService`**

Importar `use App\Events\PlaceDeviceStatusEvent;` e `use App\Events\PlaceTuyaLockStatusEvent;`.

`handleDeviceReport` fica:

```php
    /** @param array<string, mixed> $data */
    private function handleDeviceReport(array $data): void
    {
        $device = $this->findDevice($data['devId'] ?? null);

        if ($device === null) {
            return;
        }

        $status = is_array($data['status'] ?? null) ? $data['status'] : [];
        $before = $device->tuyaLockStatus()?->toArray();

        $device->forceFill([
            'tuya_status_payload' => TuyaStatusPayload::merge($device->tuya_status_payload ?? [], $status),
            'last_sync' => now(),
        ])->save();

        Log::info('[Tuya MQTT] status reportado', [
            'device_id' => $device->id,
            'codes' => collect($status)->pluck('code')->filter()->values()->all(),
        ]);

        $after = $device->tuyaLockStatus()?->toArray();

        // Só quando mudou: o mesmo relatório chega por /sta e /pen, e a bateria é reportada
        // várias vezes por acionamento.
        if ($after === null || $after === $before) {
            return;
        }

        foreach ($this->placeIdsOf($device) as $placeId) {
            PlaceTuyaLockStatusEvent::dispatch($placeId, $device->id, $after);
        }
    }
```

`handleBizEvent` fica:

```php
    /** @param array<string, mixed> $data */
    private function handleBizEvent(array $data): void
    {
        $bizCode = (string) ($data['bizCode'] ?? '');

        if (! array_key_exists($bizCode, self::ONLINE_BIZ_CODES)) {
            return;
        }

        $device = $this->findDevice(data_get($data, 'bizData.devId'));

        if ($device === null) {
            return;
        }

        $wasOnline = $device->tuya_online;
        $isOnline = self::ONLINE_BIZ_CODES[$bizCode];

        $device->forceFill([
            'tuya_online' => $isOnline,
            'last_sync' => now(),
        ])->save();

        if ($wasOnline === $isOnline) {
            return;
        }

        foreach ($this->placeIdsOf($device) as $placeId) {
            PlaceDeviceStatusEvent::dispatch($placeId, $device->id, $device->isAvailable());
        }
    }

    /**
     * Mesma resolução de local do DeviceCommandService, mais o `place_id` legado.
     *
     * @return list<int>
     */
    private function placeIdsOf(Device $device): array
    {
        return $device->places()
            ->pluck('places.id')
            ->push($device->place_id)
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
```

- [ ] **Step 5: Rodar e ver passar**

Run: `./vendor/bin/sail artisan test --filter=TuyaMqttServiceTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Events/PlaceTuyaLockStatusEvent.php app/Services/Tuya/TuyaMqttService.php tests/Unit/TuyaMqttServiceTest.php
git commit -m "feat: broadcast do status da fechadura e do online/offline Tuya"
```

---

### Task 6: Props das telas

**Files:**
- Modify: `app/Http/Resources/DeviceResource.php` (depois de `supports_tuya_temporary_password`)
- Modify: `app/Http/Controllers/App/DeviceControlController.php` (`mapDevice`)
- Modify: `app/Http/Controllers/App/PlaceControlController.php` (`mapDevice`)
- Modify: `app/Http/Controllers/App/DeviceController.php` (`show`)
- Test: `tests/Feature/Devices/TuyaLockStatusScreensTest.php`

- [ ] **Step 1: Escrever os testes**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Devices;

use App\Enums\DeviceBrandEnum;
use App\Enums\PlaceRoleEnum;
use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TuyaLockStatusScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_control_sends_the_lock_status(): void
    {
        [$user, , $lock] = $this->scenario();

        $this->actingAs($user)
            ->get("/app/devices/{$lock->id}/control")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('device.lock_status.locked', true)
                ->where('device.lock_status.battery', 51)
                ->where('device.supports_tuya_temporary_password', false));
    }

    public function test_place_control_sends_the_lock_status_only_for_locks(): void
    {
        [$user, $place, $lock] = $this->scenario();
        $other = Device::create(['name' => 'Portão', 'brand' => DeviceBrandEnum::Portatec]);
        $other->places()->attach($place->id);

        $this->actingAs($user)
            ->get("/app/places/{$place->id}/control")
            ->assertOk()
            ->assertInertia(function ($page) use ($lock): void {
                $devices = collect($page->toArray()['props']['devices'])->keyBy('id');

                $this->assertTrue($devices[$lock->id]['lock_status']['locked']);
                $this->assertNull($devices->firstWhere('name', 'Portão')['lock_status']);
            });
    }

    public function test_device_show_sends_the_lock_status_and_a_place_to_listen_on(): void
    {
        [$user, $place, $lock] = $this->scenario();

        $this->actingAs($user)
            ->get("/app/devices/{$lock->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('device.lock_status.locked', true)
                ->where('placeId', $place->id));
    }

    /** @return array{0: User, 1: Place, 2: Device} */
    private function scenario(): array
    {
        $user = User::factory()->create();
        $place = Place::create(['name' => 'Casa da Praia']);
        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $user->id,
            'role' => PlaceRoleEnum::Admin,
            'label' => $user->name,
        ]);

        // Sem integration_id: o refresh de snapshot das telas sai cedo, sem HTTP.
        $lock = Device::create([
            'name' => 'Smart Fechadura',
            'brand' => DeviceBrandEnum::Tuya,
            'tuya_category' => 'ms',
            'external_device_id' => 'dev-1',
            'tuya_status_payload' => [
                ['code' => 'lock_motor_state', 'value' => false, 't' => null],
                ['code' => 'residual_electricity', 'value' => 51, 't' => null],
            ],
        ]);
        $lock->places()->attach($place->id);

        return [$user, $place, $lock];
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `./vendor/bin/sail artisan test --filter=TuyaLockStatusScreensTest`
Expected: FAIL — `lock_status` não existe nas props.

- [ ] **Step 3: `DeviceResource`**

Depois de `'supports_tuya_temporary_password' => ...,` acrescentar:

```php
            'lock_status' => $this->tuyaLockStatus()?->toArray(),
```

- [ ] **Step 4: Controllers de controle**

Em `DeviceControlController::mapDevice` e em `PlaceControlController::mapDevice`, depois de
`'is_tuya_lock' => $device->isTuyaLock(),` acrescentar:

```php
            'supports_tuya_temporary_password' => $device->supportsTuyaTemporaryPassword(),
            'lock_status' => $device->tuyaLockStatus()?->toArray(),
```

- [ ] **Step 5: `DeviceController::show`**

No array do `Inertia::render('devices/show', [...])`, depois de `'codesOnDevice' => $codesOnDevice,`:

```php
            // Canal de realtime do status da fechadura: os canais são por local. Usa um local que
            // o usuário enxerga; sem nenhum, a tela fica só com o status do carregamento.
            'placeId' => $device->visiblePlacesFor(Auth::user())->first()?->id,
```

- [ ] **Step 6: Rodar e ver passar**

Run: `./vendor/bin/sail artisan test --filter='TuyaLockStatusScreensTest|DevicesTest|PlacesTest'`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Resources/DeviceResource.php app/Http/Controllers/App/DeviceControlController.php app/Http/Controllers/App/PlaceControlController.php app/Http/Controllers/App/DeviceController.php tests/Feature/Devices/TuyaLockStatusScreensTest.php
git commit -m "feat: telas de controle e detalhe recebem o status da fechadura"
```

---

### Task 7: Reducer do status no front

**Files:**
- Modify: `resources/js/types/models.ts`
- Create: `resources/js/hooks/tuya-lock-status-reducer.ts`
- Test: `resources/js/hooks/__tests__/tuya-lock-status-reducer.test.ts`

- [ ] **Step 1: Tipos**

Em `resources/js/types/models.ts`, antes de `export interface Device`:

```ts
/** `TuyaLockStatus::toArray()` — cada campo pode ser desconhecido. */
export interface TuyaLockStatus {
    locked: boolean | null;
    battery: number | null;
    alert: string | null;
    updated_at: string | null;
}
```

E dentro de `Device`, depois de `supports_tuya_temporary_password?: boolean;`:

```ts
    lock_status?: TuyaLockStatus | null;
```

- [ ] **Step 2: Escrever o teste**

```ts
import { describe, expect, it } from 'vitest';

import type { TuyaLockStatus } from '@/types';

import { createInitialLockState, getLockStatus, tuyaLockStatusReducer } from '../tuya-lock-status-reducer';

const locked: TuyaLockStatus = { locked: true, battery: 51, alert: null, updated_at: null };
const unlocked: TuyaLockStatus = { locked: false, battery: 51, alert: null, updated_at: '2026-10-07T22:00:00+00:00' };

describe('tuyaLockStatusReducer', () => {
    it('starts from the initial status of each device', () => {
        const state = createInitialLockState({ '4': locked });

        expect(getLockStatus(state, 4)).toEqual(locked);
        expect(getLockStatus(state, 5)).toBeNull();
    });

    it('replaces the status of the device in the event', () => {
        const state = tuyaLockStatusReducer(createInitialLockState({ '4': locked, '7': locked }), {
            type: 'status_received',
            deviceId: 4,
            status: unlocked,
        });

        expect(getLockStatus(state, 4)).toEqual(unlocked);
        expect(getLockStatus(state, 7)).toEqual(locked);
    });

    it('stores an event for a device it did not know', () => {
        const state = tuyaLockStatusReducer(createInitialLockState(), {
            type: 'status_received',
            deviceId: 9,
            status: unlocked,
        });

        expect(getLockStatus(state, 9)).toEqual(unlocked);
    });
});
```

- [ ] **Step 3: Rodar e ver falhar**

Run: `./vendor/bin/sail npm run test:js -- tuya-lock-status-reducer`
Expected: FAIL — módulo `../tuya-lock-status-reducer` não encontrado.

- [ ] **Step 4: Implementar**

```ts
import type { TuyaLockStatus } from '@/types';

export type DeviceId = number;

/** Status da fechadura por dispositivo, indexado pelo id como string. */
export type TuyaLockStatusState = Record<string, TuyaLockStatus>;

export type TuyaLockStatusAction = { type: 'status_received'; deviceId: DeviceId; status: TuyaLockStatus };

export function createInitialLockState(initial: Record<string, TuyaLockStatus> = {}): TuyaLockStatusState {
    return { ...initial };
}

export function tuyaLockStatusReducer(state: TuyaLockStatusState, action: TuyaLockStatusAction): TuyaLockStatusState {
    switch (action.type) {
        case 'status_received':
            return { ...state, [String(action.deviceId)]: action.status };
    }
}

export function getLockStatus(state: TuyaLockStatusState, deviceId: DeviceId): TuyaLockStatus | null {
    return state[String(deviceId)] ?? null;
}
```

- [ ] **Step 5: Rodar e ver passar**

Run: `./vendor/bin/sail npm run test:js -- tuya-lock-status-reducer`
Expected: PASS (3 testes).

- [ ] **Step 6: Commit**

```bash
git add resources/js/types/models.ts resources/js/hooks/tuya-lock-status-reducer.ts resources/js/hooks/__tests__/tuya-lock-status-reducer.test.ts
git commit -m "feat: reducer do status da fechadura Tuya no front"
```

---

### Task 8: Hook, formatação relativa, painel e textos

**Files:**
- Create: `resources/js/hooks/use-tuya-lock-status.ts`
- Create: `resources/js/lib/format-relative.ts`
- Test: `resources/js/lib/__tests__/format-relative.test.ts`
- Create: `resources/js/components/device-control/tuya-lock-status-panel.tsx`
- Modify: `resources/lang/pt_BR/app.php` (bloco "Integração Tuya")

- [ ] **Step 1: Teste da formatação relativa**

```ts
import { describe, expect, it } from 'vitest';

import { formatRelative } from '../format-relative';

const NOW = Date.parse('2026-10-07T22:00:00Z');

describe('formatRelative', () => {
    it('uses the largest unit that fits', () => {
        expect(formatRelative('2026-10-07T21:55:00Z', NOW)).toBe('há 5 minutos');
        expect(formatRelative('2026-10-07T19:00:00Z', NOW)).toBe('há 3 horas');
        expect(formatRelative('2026-10-05T22:00:00Z', NOW)).toBe('anteontem');
    });

    it('falls back to seconds', () => {
        expect(formatRelative('2026-10-07T21:59:30Z', NOW)).toBe('há 30 segundos');
    });
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `./vendor/bin/sail npm run test:js -- format-relative`
Expected: FAIL — módulo não encontrado.

- [ ] **Step 3: Implementar a formatação**

```ts
const UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60],
];

/** "há 5 minutos", "anteontem" — a partir de um ISO 8601. */
export function formatRelative(isoString: string, now: number = Date.now()): string {
    const seconds = Math.round((Date.parse(isoString) - now) / 1000);
    const formatter = new Intl.RelativeTimeFormat('pt-BR', { numeric: 'auto' });

    for (const [unit, size] of UNITS) {
        if (Math.abs(seconds) >= size) {
            return formatter.format(Math.round(seconds / size), unit);
        }
    }

    return formatter.format(seconds, 'second');
}
```

- [ ] **Step 4: Rodar e ver passar**

Run: `./vendor/bin/sail npm run test:js -- format-relative`
Expected: PASS. Se o ICU do Node formatar diferente (ex.: "há 2 dias" em vez de "anteontem"),
ajuste a expectativa do teste ao que o `Intl` do container devolve — o texto vem do `Intl`, não
do nosso código.

- [ ] **Step 5: Hook**

```ts
import { useCallback, useReducer } from 'react';

import { useEcho } from '@/hooks/use-echo';
import {
    createInitialLockState,
    type DeviceId,
    getLockStatus,
    tuyaLockStatusReducer,
} from '@/hooks/tuya-lock-status-reducer';
import type { TuyaLockStatus } from '@/types';

/** Payload do evento `.PlaceTuyaLockStatus` (`PlaceTuyaLockStatusEvent`). */
interface PlaceTuyaLockStatusPayload {
    deviceId: DeviceId;
    status: TuyaLockStatus;
}

export interface UseTuyaLockStatusOptions {
    /** Local cujo canal `Place.Device.Status.{placeId}` assinar. `null` não assina. */
    placeId: number | null;
    /** Status vindo do carregamento da página, por id do dispositivo. */
    initial: Record<string, TuyaLockStatus>;
}

/** Status das fechaduras Tuya de um local, atualizado pelo Reverb. */
export function useTuyaLockStatus({ placeId, initial }: UseTuyaLockStatusOptions) {
    const [state, dispatch] = useReducer(tuyaLockStatusReducer, initial, createInitialLockState);

    useEcho<PlaceTuyaLockStatusPayload>(placeId ? `Place.Device.Status.${placeId}` : null, '.PlaceTuyaLockStatus', (payload) => {
        dispatch({ type: 'status_received', deviceId: payload.deviceId, status: payload.status });
    });

    const get = useCallback((deviceId: DeviceId) => getLockStatus(state, deviceId), [state]);

    return { get };
}
```

- [ ] **Step 6: Textos**

Em `resources/lang/pt_BR/app.php`, no bloco `// Integração Tuya`, depois de
`'tuya_lock_no_temp_password_dp' => ...,`:

```php
    'tuya_lock_status_title' => 'Fechadura',
    'tuya_lock_locked' => 'Trancada',
    'tuya_lock_unlocked' => 'Destrancada',
    'tuya_lock_unknown' => 'Estado desconhecido',
    'tuya_lock_battery' => 'Bateria: :level%',
    'tuya_lock_updated' => 'Atualizado :when',
    'tuya_lock_alerts' => [
        'pry' => 'Alerta: tentativa de arrombamento',
        'shock' => 'Alerta: impacto na fechadura',
        'low_battery' => 'Alerta: bateria baixa',
        'power_off' => 'Alerta: fechadura sem energia',
        'too_hot' => 'Alerta: temperatura alta',
        'unclosed_time' => 'Alerta: a porta ficou destrancada',
        'tongue_bad' => 'Alerta: falha na lingueta',
        'tongue_not_out' => 'Alerta: a lingueta não travou',
    ],
```

- [ ] **Step 7: Painel**

```tsx
import { StatusBadge, type StatusBadgeVariant } from '@/components/status-badge';
import { useTranslations } from '@/hooks/use-translations';
import { formatRelative } from '@/lib/format-relative';
import { cn } from '@/lib/utils';
import type { TuyaLockStatus } from '@/types';

/** Abaixo disso a bateria aparece em destaque. */
const LOW_BATTERY_THRESHOLD = 20;

interface TuyaLockStatusPanelProps {
    status: TuyaLockStatus | null;
}

/** Trancada/destrancada, bateria e alerta de uma fechadura Tuya. Cada linha some quando o dado é desconhecido. */
export function TuyaLockStatusPanel({ status }: TuyaLockStatusPanelProps) {
    const { t } = useTranslations();

    const locked = status?.locked ?? null;
    const variant: StatusBadgeVariant = locked === true ? 'success' : locked === false ? 'warning' : 'neutral';
    const label = locked === true ? t('tuya_lock_locked') : locked === false ? t('tuya_lock_unlocked') : t('tuya_lock_unknown');

    return (
        <div className="space-y-1.5">
            <StatusBadge variant={variant}>{label}</StatusBadge>
            {status?.battery != null ? (
                <p
                    className={cn(
                        'm-0',
                        status.battery < LOW_BATTERY_THRESHOLD ? 'font-medium text-destructive' : 'text-neutral-500',
                    )}
                >
                    {t('tuya_lock_battery', { level: status.battery })}
                </p>
            ) : null}
            {status?.alert ? <p className="m-0 font-medium text-destructive">{t(`tuya_lock_alerts.${status.alert}`)}</p> : null}
            {status?.updated_at ? (
                <p className="m-0 text-sm text-neutral-500">{t('tuya_lock_updated', { when: formatRelative(status.updated_at) })}</p>
            ) : null}
        </div>
    );
}
```

Confirme que `StatusBadgeVariant` é exportado de `resources/js/components/status-badge.tsx`
(é: `export type StatusBadgeVariant = 'success' | 'neutral' | 'warning' | 'error';`).

- [ ] **Step 8: Commit**

```bash
git add resources/js/hooks/use-tuya-lock-status.ts resources/js/lib/format-relative.ts resources/js/lib/__tests__/format-relative.test.ts resources/js/components/device-control/tuya-lock-status-panel.tsx resources/lang/pt_BR/app.php
git commit -m "feat: painel e hook de status da fechadura Tuya"
```

---

### Task 9: Usar o painel nas três telas

**Files:**
- Modify: `resources/js/pages/devices/control.tsx`
- Modify: `resources/js/pages/places/control.tsx`
- Modify: `resources/js/pages/devices/show.tsx`

- [ ] **Step 1: `devices/control.tsx`**

Imports novos:

```tsx
import { TuyaLockStatusPanel } from '@/components/device-control/tuya-lock-status-panel';
import { useTuyaLockStatus } from '@/hooks/use-tuya-lock-status';
import type { TuyaLockStatus } from '@/types';
```

Em `interface ControlDevice`, depois de `is_tuya_lock: boolean;`:

```tsx
    supports_tuya_temporary_password: boolean;
    lock_status: TuyaLockStatus | null;
```

Logo depois de `const commands = useDeviceCommands({...});`:

```tsx
    const lockStatus = useTuyaLockStatus({
        placeId: placeId || null,
        initial: device.lock_status ? { [String(device.id)]: device.lock_status } : {},
    });
```

Trocar o ramo `device.is_tuya_lock ? (<p ...>{t('device_control_tuya_lock_message')}</p>)` por:

```tsx
                    ) : device.is_tuya_lock ? (
                        <div className="space-y-2">
                            <TuyaLockStatusPanel status={lockStatus.get(device.id)} />
                            {device.supports_tuya_temporary_password ? (
                                <p className="m-0 text-neutral-500">{t('device_control_tuya_lock_message')}</p>
                            ) : null}
                        </div>
```

- [ ] **Step 2: `places/control.tsx`**

Mesmos três imports e os mesmos dois campos em `interface ControlDevice`. Depois de
`const commands = useDeviceCommands({...});`:

```tsx
    const initialLockStatus = useMemo(
        () =>
            Object.fromEntries(
                devices.flatMap((device) => (device.lock_status ? [[String(device.id), device.lock_status]] : [])),
            ),
        [devices],
    );

    const lockStatus = useTuyaLockStatus({ placeId: place.id, initial: initialLockStatus });
```

Trocar o ramo `device.is_tuya_lock ? (...)` por:

```tsx
                                ) : device.is_tuya_lock ? (
                                    <div className="space-y-2">
                                        <TuyaLockStatusPanel status={lockStatus.get(device.id)} />
                                        {device.supports_tuya_temporary_password ? (
                                            <p className="m-0 text-neutral-500">{t('device_control_tuya_lock_message')}</p>
                                        ) : null}
                                    </div>
```

- [ ] **Step 3: `devices/show.tsx`**

Imports novos:

```tsx
import { TuyaLockStatusPanel } from '@/components/device-control/tuya-lock-status-panel';
import { useTuyaLockStatus } from '@/hooks/use-tuya-lock-status';
```

Em `DevicesShowProps`, acrescentar `placeId: number | null;` e recebê-lo na desestruturação:
`export default function DevicesShow({ device, recentCommands, recentTuyaSyncs, codesOnDevice, abilities, placeId }: DevicesShowProps)`.

No começo do componente, depois de `const { t } = useTranslations();`:

```tsx
    const lockStatus = useTuyaLockStatus({
        placeId,
        initial: device.lock_status ? { [String(device.id)]: device.lock_status } : {},
    });
```

Dentro da grade de cartões (`grid-cols-[repeat(auto-fit,minmax(220px,1fr))]`), depois do cartão
de `status`:

```tsx
                    {device.is_tuya_lock ? (
                        <div className="rounded-lg border border-neutral-200 bg-white p-3.5">
                            <strong>{t('tuya_lock_status_title')}</strong>
                            <div className="mt-1.5">
                                <TuyaLockStatusPanel status={lockStatus.get(device.id)} />
                            </div>
                        </div>
                    ) : null}
```

- [ ] **Step 4: Tipos, testes JS e build**

Run: `./vendor/bin/sail npx tsc --noEmit`
Expected: sem erros novos. (Se o projeto já tiver erros de tipo antes desta branch, compare com
`git stash` + `tsc` para garantir que nenhum é destes arquivos.)

Run: `./vendor/bin/sail npm run test:js`
Expected: PASS.

Run: `./vendor/bin/sail npm run build`
Expected: build conclui sem erro.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/devices/control.tsx resources/js/pages/places/control.tsx resources/js/pages/devices/show.tsx
git commit -m "feat: status da fechadura nas telas de controle e no detalhe"
```

---

### Task 10: AGENTS.md, verificação final e PR

**Files:**
- Modify: `AGENTS.md` (§11.1 tabela de camadas, §11.7 colunas)

- [ ] **Step 1: Documentar as peças novas**

Na tabela da §11.1, acrescentar as linhas:

```markdown
| Persistência | `TuyaStatusPayload` | mescla o status por `code`; nunca grava DPs sensíveis |
| Derivação | `DTOs\TuyaLockStatus` | trancada, bateria e alerta da fechadura para a tela |
```

Na §11.7, depois da linha de `devices`, acrescentar:

```markdown
`tuya_status_payload` é uma lista `{code, value, t}` mesclada por `code` — toda gravação passa
por `TuyaStatusPayload::merge`. O `PlaceTuyaLockStatusEvent` sai pelo canal
`Place.Device.Status.{placeId}` quando o status derivado da fechadura muda.
```

- [ ] **Step 2: Pint e suíte completa**

Run: `./vendor/bin/sail pint`
Expected: arquivos formatados (commit se mudar algo).

Run: `./vendor/bin/sail test`
Expected: PASS em toda a suíte.

- [ ] **Step 3: Verificação manual (ambiente local)**

Com `./vendor/bin/sail up -d`, Horizon e Reverb rodando: abra `/app/devices/{id}/control` de uma
fechadura Tuya e confirme o painel. Para simular um evento sem a Tuya:

```bash
./vendor/bin/sail artisan tinker --execute='app(App\Services\Tuya\TuyaMqttService::class)->handleMessage(["protocol" => 4, "data" => ["devId" => App\Models\Device::find(4)->external_device_id, "status" => [["code" => "lock_motor_state", "value" => true, "t" => now()->getTimestampMs()]]]]);'
```

Expected: o selo muda para "Destrancada" sem recarregar a página.

- [ ] **Step 4: Commit**

```bash
git add AGENTS.md
git commit -m "docs: AGENTS.md registra TuyaStatusPayload e TuyaLockStatus"
```

- [ ] **Step 5: Abrir o PR**

```bash
git push -u origin docs/status-fechadura-tuya
gh pr create --base main --title "Status da fechadura Tuya em tempo real" --body-file <arquivo com o corpo>
```

O corpo resume: achados documentados (AGENTS.md §11.4, §11.5, §11.6.1, §11.9), o que a feature
faz, a correção do status sobrescrito, a remoção do código BLE (incluindo a migration) e como
testar. Termina com a linha de atribuição do Claude Code.
