<?php

namespace Database\Factories;

use App\Enums\PoolSeasonEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Scheme>
 */
class SchemeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'season' => $this->faker->randomElement(PoolSeasonEnum::cases()),
            'reveal' => $this->faker->paragraph(),
            'scoring' => $this->faker->paragraph(),
            'additional' => $this->faker->paragraph(),
        ];
    }
}
