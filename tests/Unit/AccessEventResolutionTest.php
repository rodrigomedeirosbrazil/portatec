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
