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
