<?php

namespace Database\Factories;

use App\Enums\DefensiveAbilityTypeEnum;
use App\Enums\GameModeTypeEnum;
use App\Enums\SuitEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ability>
 */
class AbilityFactory extends Factory
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
            'name' => $this->faker->unique()->words(random_int(1, 3), true),
            'suits' => $this->faker->optional(0.3)->randomElement(SuitEnum::cases()),
            'defensive_ability_type' => $this->faker->optional(0.3)->randomElement(DefensiveAbilityTypeEnum::cases()),
            'costs_stone' => $this->faker->boolean(15),
            'description' => $this->faker->sentence(random_int(5, 20)),
        ];
    }
}
