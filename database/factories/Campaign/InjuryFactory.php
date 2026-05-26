<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\Injury;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Injury>
 */
class InjuryFactory extends Factory
{
    protected $model = Injury::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'body' => $this->faker->sentence(),
            'flip_value' => $this->faker->numberBetween(1, 13),
            'suit_pool' => 'pc',
            'reflip_if_no_triggers' => false,
            'reflip_if_master_or_totem' => false,
            'is_traitor' => false,
            'is_close_call' => false,
            'annihilates_model' => false,
        ];
    }

    public function killedOff(): static
    {
        return $this->state(fn () => [
            'flip_value' => 13,
            'annihilates_model' => true,
            'name' => 'Killed Off',
        ]);
    }

    public function traitor(): static
    {
        return $this->state(fn () => [
            'flip_value' => null,
            'suit_pool' => 'black_joker',
            'is_traitor' => true,
            'reflip_if_master_or_totem' => true,
            'name' => 'Traitor',
        ]);
    }
}
