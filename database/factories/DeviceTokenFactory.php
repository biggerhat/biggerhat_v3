<?php

namespace Database\Factories;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DeviceToken>
 */
class DeviceTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform' => $this->faker->randomElement(['ios', 'android']),
            'token' => Str::random(64),
            'last_seen_at' => now(),
        ];
    }

    public function ios(): static
    {
        return $this->state(['platform' => 'ios']);
    }

    public function android(): static
    {
        return $this->state(['platform' => 'android']);
    }
}
