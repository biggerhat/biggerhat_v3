<?php

namespace Database\Factories;

use App\Enums\FactionEnum;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TournamentPlayer>
 */
class TournamentPlayerFactory extends Factory
{
    protected $model = TournamentPlayer::class;

    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'user_id' => null,
            'display_name' => fake()->name(),
            'faction' => fake()->randomElement(FactionEnum::cases())->value,
            'is_ringer' => false,
            'is_disqualified' => false,
            'dropped_after_round' => null,
        ];
    }

    public function ringer(): self
    {
        return $this->state(['is_ringer' => true]);
    }
}
