<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_bookings_sync_scheduled_daily_at_6_brt(): void
    {
        $schedule = app(Schedule::class);

        require base_path('routes/console.php');

        $event = collect($schedule->events())
            ->first(fn ($item) => str_contains((string) $item->command, 'bookings:sync'));

        $this->assertNotNull($event);
        $this->assertSame('0 6 * * *', $event->getExpression());
        $this->assertSame('America/Sao_Paulo', $event->timezone);
    }
}
