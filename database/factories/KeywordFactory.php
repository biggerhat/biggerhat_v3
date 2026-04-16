<?php

namespace Database\Factories;

use App\Enums\GameModeTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Keyword>
 */
class KeywordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_mode_type' => GameModeTypeEnum::Standard,
            'name' => $this->faker->unique()->word(),
        ];
    }
}
