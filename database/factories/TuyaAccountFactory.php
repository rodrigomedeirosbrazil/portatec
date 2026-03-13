<?php

namespace Database\Factories;

use App\Models\TuyaAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TuyaAccount>
 */
class TuyaAccountFactory extends Factory
{
    protected $model = TuyaAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'uid' => 'tuya-'.fake()->unique()->uuid(),
            'access_token' => 'at-'.fake()->sha1(),
            'refresh_token' => 'rt-'.fake()->sha1(),
            'expires_at' => now()->addHours(2),
            'platform_url' => 'https://openapi.tuyaus.com',
            'active' => true,
        ];
    }
}
