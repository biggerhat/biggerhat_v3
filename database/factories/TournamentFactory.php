<?php

namespace Database\Factories;

use App\Enums\PoolSeasonEnum;
use App\Enums\TournamentStatusEnum;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tournament>
 */
class TournamentFactory extends Factory
{
    protected $model = Tournament::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Tournament',
            'description' => fake()->optional()->sentence(),
            'creator_id' => User::factory(),
            'encounter_size' => 50,
            'encounter_type' => 'traditional',
            'planned_rounds' => 3,
            'season' => PoolSeasonEnum::cases()[0]->value,
            'status' => TournamentStatusEnum::Draft->value,
            'location' => fake()->city(),
            'event_date' => now()->addWeek()->toDateString(),
            'round_time_limit' => 135,
        ];
    }

    public function inRegistration(): self
    {
        return $this->state(['status' => TournamentStatusEnum::Registration->value]);
    }

    public function active(): self
    {
        return $this->state(['status' => TournamentStatusEnum::Active->value]);
    }

    public function completed(): self
    {
        return $this->state(['status' => TournamentStatusEnum::Completed->value]);
    }
}
