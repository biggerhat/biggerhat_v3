<?php

namespace Database\Factories;

use App\Enums\SuitEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Trigger>
 */
class TriggerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(random_int(1, 3), true),
            'suits' => $this->faker->optional(0.8)->randomElement(SuitEnum::cases()),
            'stone_cost' => $this->faker->numberBetween(0, 2),
            'description' => $this->faker->optional(0.8)->sentence(random_int(5, 15)),
        ];
    }
}
