<?php

namespace Database\Factories;

use App\Enums\PoolSeasonEnum;
use App\Enums\SuitEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Strategy>
 */
class StrategyFactory extends Factory
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
            'suit' => $this->faker->optional()->randomElement(SuitEnum::cases()),
            'setup' => $this->faker->paragraph(),
            'rules' => $this->faker->paragraph(),
            'scoring' => $this->faker->paragraph(),
        ];
    }
}
