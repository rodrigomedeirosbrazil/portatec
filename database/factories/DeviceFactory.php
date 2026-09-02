<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeviceBrandEnum;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'place_id' => null,
            'name' => $this->faker->words(2, true),
            'brand' => DeviceBrandEnum::Portatec,
            'last_sync' => now()->subMinute(),
            'tuya_online' => null,
        ];
    }
}
