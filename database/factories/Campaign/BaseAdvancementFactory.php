<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\Advancement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Shared definition for the four flip-based advancement tables. Subclasses
 * differ only in their target model + physical table.
 *
 * @extends Factory<Advancement>
 */
abstract class BaseAdvancementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'body' => $this->faker->sentence(),
            'flip_value' => $this->faker->numberBetween(1, 13),
            'is_always_available' => false,
            'is_black_joker' => false,
            'is_red_joker' => false,
            'modifier_type' => 'trigger',
            'suit' => 'ram',
            'skl_from' => null,
            'skl_to' => null,
            'grants_signature' => false,
            'joker_freechoice' => false,
            'stat_block' => null,
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

    public function sklBoost(int $from, int $to): static
    {
        return $this->state(fn () => [
            'modifier_type' => 'skl',
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
            'grants_signature' => true,
        ]);
    }
}
