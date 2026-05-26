<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\Totem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Totem>
 */
class TotemFactory extends Factory
{
    protected $model = Totem::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'flip_value' => $this->faker->numberBetween(1, 13),
            'is_black_joker' => false,
            'is_red_joker' => false,
            'df' => 5, 'wp' => 5, 'sp' => 6, 'health' => 9,
            'abilities' => null,
            'attack_actions' => null,
            'tactical_actions' => null,
            'special_replace_with_other_totem' => false,
            'is_mini_master' => false,
        ];
    }

    public function snivelingCoward(): static
    {
        return $this->state(fn () => [
            'flip_value' => null,
            'is_black_joker' => true,
            'health' => 6,
            'df' => 4, 'wp' => 4,
            'special_replace_with_other_totem' => true,
            'name' => 'Sniveling Coward',
        ]);
    }

    public function miniMaster(): static
    {
        return $this->state(fn () => [
            'flip_value' => null,
            'is_red_joker' => true,
            'is_mini_master' => true,
            'name' => 'Mini-Master',
        ]);
    }
}
