<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = now()->addDays($this->faker->numberBetween(1, 30));

        return [
            'place_id' => Place::factory(),
            'guest_name' => $this->faker->name(),
            'check_in' => $checkIn,
            'check_out' => (clone $checkIn)->addDays($this->faker->numberBetween(1, 5)),
            'source' => 'manual',
        ];
    }
}
