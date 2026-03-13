<?php

namespace Database\Factories;

use App\Models\TuyaAccount;
use App\Models\TuyaDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TuyaDevice>
 */
class TuyaDeviceFactory extends Factory
{
    protected $model = TuyaDevice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tuya_account_id' => TuyaAccount::factory(),
            'place_id' => null,
            'device_id' => 'dev-'.fake()->unique()->uuid(),
            'name' => fake()->words(2, true),
            'category' => 'ms',
            'online' => true,
            'status' => null,
            'enabled' => true,
        ];
    }
}
