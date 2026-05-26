<?php

namespace Database\Factories\Campaign;

use App\Enums\LeaderArchetypeEnum;
use App\Models\Campaign\LeaderArchetype;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaderArchetype>
 */
class LeaderArchetypeFactory extends Factory
{
    protected $model = LeaderArchetype::class;

    public function definition(): array
    {
        return [
            'slug' => LeaderArchetypeEnum::Generalist->value,
            'name' => 'Generalist',
            'df' => 5,
            'wp' => 5,
            'sp' => 6,
            'health' => 14,
            'attack_actions_count' => 1,
            'attack_action_cost_cap' => 7,
            'attack_gets_trigger' => false,
            'tactical_actions_count' => 1,
            'tactical_action_cost_cap' => 7,
            'abilities_count' => 1,
            'ability_cost_cap' => 7,
            'special_notes' => null,
        ];
    }

    public function heavyHitter(): static
    {
        return $this->state(fn () => [
            'slug' => LeaderArchetypeEnum::HeavyHitter->value,
            'name' => 'Heavy Hitter',
            'df' => 6, 'wp' => 4, 'sp' => 6, 'health' => 14,
            'attack_action_cost_cap' => 10,
            'attack_gets_trigger' => true,
            'tactical_action_cost_cap' => 5,
            'abilities_count' => 0,
        ]);
    }
}
