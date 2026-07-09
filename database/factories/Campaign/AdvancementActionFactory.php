<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\AdvancementAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdvancementAction>
 */
class AdvancementActionFactory extends Factory
{
    protected $model = AdvancementAction::class;

    public function definition(): array
    {
        return [
            'flip_value' => $this->faker->numberBetween(1, 13),
            'is_joker' => false,
            'is_always_available' => false,
            'is_signature' => false,
            'talent_name' => $this->faker->unique()->words(2, true),
            'effect_text' => $this->faker->sentence(),
            'action_id' => null,
            // Shaped like the leader's stored actions[] entry (see Action model
            // columns) so bespoke rows apply without a translation layer.
            'stat_block' => ['type' => 'tactical', 'range' => 8, 'range_type' => 'ft', 'stat' => 5, 'resisted_by' => 'df', 'target_number' => null, 'damage' => 2],
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
            'stat_block' => null,
        ]);
    }

    public function lookup(int $actionId): static
    {
        return $this->state(fn () => [
            'action_id' => $actionId,
            'stat_block' => null,
        ]);
    }

    public function signature(): static
    {
        return $this->state(fn () => ['is_signature' => true]);
    }
}
