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
