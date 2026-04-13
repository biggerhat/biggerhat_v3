<?php

namespace Database\Factories;

use App\Enums\TournamentRoundStatusEnum;
use App\Models\Tournament;
use App\Models\TournamentRound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TournamentRound>
 */
class TournamentRoundFactory extends Factory
{
    protected $model = TournamentRound::class;

    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'round_number' => 1,
            'status' => TournamentRoundStatusEnum::Setup->value,
        ];
    }

    public function inProgress(): self
    {
        return $this->state(['status' => TournamentRoundStatusEnum::InProgress->value, 'started_at' => now()]);
    }

    public function completed(): self
    {
        return $this->state([
            'status' => TournamentRoundStatusEnum::Completed->value,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
    }
}
