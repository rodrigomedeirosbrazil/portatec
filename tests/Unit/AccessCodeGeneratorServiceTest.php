<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AccessCode\AccessCodeGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccessCodeGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_pin_returns_six_digits_and_avoids_existing_pin_in_same_place(): void
    {
        $placeId = DB::table('places')->insertGetId([
            'name' => 'Pin Place',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('access_codes')->insert([
            'place_id' => $placeId,
            'pin' => '123456',
            'start' => now()->subDay(),
            'end' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(AccessCodeGeneratorService::class);
        $pin = $service->generatePin($placeId);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $pin);
        $this->assertNotSame('123456', $pin);
    }
}
