<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\AdvancementAbility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdvancementAbility>
 */
class AdvancementAbilityFactory extends Factory
{
    protected $model = AdvancementAbility::class;

    public function definition(): array
    {
        return [
            'flip_value' => $this->faker->numberBetween(1, 13),
            'is_joker' => false,
            'is_always_available' => false,
            'talent_name' => $this->faker->unique()->words(2, true),
            'effect_text' => $this->faker->sentence(),
            'ability_id' => null,
            'suits' => null,
            'defensive_ability_type' => null,
        ];
    }

    public function alwaysAvailable(): static
    {
        return $this->state(fn () => [
            'flip_value' => null,
            'is_always_available' => true,
        ]);
    }

    public function anyJoker(): static
    {
        return $this->state(fn () => [
            'flip_value' => null,
            'is_joker' => true,
        ]);
    }

    public function lookup(int $abilityId): static
    {
        return $this->state(fn () => [
            'ability_id' => $abilityId,
        ]);
    }
}
