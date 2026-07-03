<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\AdvancementAttackMod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdvancementAttackMod>
 */
class AdvancementAttackModFactory extends Factory
{
    protected $model = AdvancementAttackMod::class;

    public function definition(): array
    {
        return [
            'flip_value' => $this->faker->numberBetween(1, 13),
            'is_black_joker' => false,
            'is_red_joker' => false,
            'is_always_available' => false,
            'modifier_type' => 'trigger',
            'name' => $this->faker->unique()->words(2, true),
            'effect_text' => $this->faker->sentence(),
            'suit' => 'ram',
            'skl_from' => null,
            'skl_to' => null,
            'trigger_id' => null,
        ];
    }

    public function alwaysAvailable(): static
    {
        return $this->state(fn () => [
            'flip_value' => null,
            'is_always_available' => true,
        ]);
    }

    public function sklBoost(int $from, int $to): static
    {
        return $this->state(fn () => [
            'modifier_type' => 'skl_boost',
            'suit' => null,
            'skl_from' => $from,
            'skl_to' => $to,
        ]);
    }

    public function signature(): static
    {
        return $this->state(fn () => [
            'modifier_type' => 'signature',
            'suit' => null,
        ]);
    }
}
