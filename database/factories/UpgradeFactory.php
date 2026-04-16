<?php

namespace Database\Factories;

use App\Enums\GameModeTypeEnum;
use App\Enums\UpgradeDomainTypeEnum;
use App\Enums\UpgradeTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Upgrade>
 */
class UpgradeFactory extends Factory
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
            'domain' => $this->faker->randomElement(UpgradeDomainTypeEnum::cases()),
            'type' => $this->faker->optional()->randomElement(UpgradeTypeEnum::cases()),
            'front_image' => 'seed/upgrade-front.png',
            'back_image' => 'seed/upgrade-back.png',
        ];
    }
}
