<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    protected $model = Equipment::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'br' => $this->faker->numberBetween(1, 13),
            'cc' => $this->faker->numberBetween(1, 3),
            'is_always_available' => false,
            'is_red_joker_entry' => false,
            'ttw_only' => false,
            'is_omens_mark' => false,
            'pool_suit_a' => 'ram',
            'pool_suit_b' => 'crow',
            'is_unique' => false,
            'leader_only' => false,
            'non_unique_only' => false,
            'annihilate_after_game' => false,
            'body' => $this->faker->sentence(),
            'granted_ability' => null,
            'granted_action' => null,
        ];
    }

    public function alwaysAvailable(): static
    {
        return $this->state(fn () => [
            'br' => null,
            'is_always_available' => true,
        ]);
    }

    public function thoseWhoThirst(): static
    {
        return $this->state(fn () => ['ttw_only' => true]);
    }
}
