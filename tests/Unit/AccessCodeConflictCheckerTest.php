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
