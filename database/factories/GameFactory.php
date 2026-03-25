<?php

namespace Database\Factories;

use App\Enums\DeploymentEnum;
use App\Enums\GameStatusEnum;
use App\Enums\PoolSeasonEnum;
use App\Models\Strategy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Game>
 */
class GameFactory extends Factory
{
    public function definition(): array
    {
        return [
            'creator_id' => User::factory(),
            'encounter_size' => 50,
            'season' => PoolSeasonEnum::GainingGrounds0,
            'status' => GameStatusEnum::Setup,
            'deployment' => $this->faker->randomElement(DeploymentEnum::cases()),
            'strategy_id' => Strategy::factory(),
            'scheme_pool' => [],
            'max_turns' => 5,
            'current_turn' => 0,
            'is_solo' => false,
            'is_observable' => false,
        ];
    }

    public function solo(): static
    {
        return $this->state(['is_solo' => true, 'status' => GameStatusEnum::FactionSelect, 'started_at' => now()]);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => GameStatusEnum::InProgress, 'current_turn' => 1, 'started_at' => now()]);
    }

    public function completed(): static
    {
        return $this->state(['status' => GameStatusEnum::Completed, 'started_at' => now(), 'completed_at' => now()]);
    }
}
