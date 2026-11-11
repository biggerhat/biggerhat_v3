<?php

namespace Database\Factories;

use App\Enums\FactionEnum;
use App\Models\TournamentPlayer;
use App\Models\TournamentPlayerMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TournamentPlayerMember>
 */
class TournamentPlayerMemberFactory extends Factory
{
    protected $model = TournamentPlayerMember::class;

    public function definition(): array
    {
        return [
            'tournament_player_id' => TournamentPlayer::factory(),
            'display_name' => fake()->name(),
            'user_id' => null,
            'faction' => FactionEnum::cases()[0]->value,
            'allegiance_id' => null,
            'garrison_id' => null,
            'position' => 0,
        ];
    }
}
