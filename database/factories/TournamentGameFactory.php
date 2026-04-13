<?php

namespace Database\Factories;

use App\Enums\TournamentGameResultEnum;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TournamentGame>
 */
class TournamentGameFactory extends Factory
{
    protected $model = TournamentGame::class;

    public function definition(): array
    {
        return [
            'tournament_round_id' => TournamentRound::factory(),
            'player_one_id' => TournamentPlayer::factory(),
            'player_two_id' => TournamentPlayer::factory(),
            'is_bye' => false,
            'is_forfeit' => false,
            'result' => TournamentGameResultEnum::Pending->value,
        ];
    }

    public function bye(): self
    {
        return $this->state([
            'player_two_id' => null,
            'is_bye' => true,
            'result' => TournamentGameResultEnum::Completed->value,
        ]);
    }

    public function withScore(int $p1Vp, int $p2Vp): self
    {
        return $this->state([
            'player_one_strategy_vp' => min($p1Vp, 4),
            'player_one_scheme_vp' => max(0, $p1Vp - 4),
            'player_one_vp' => $p1Vp,
            'player_two_strategy_vp' => min($p2Vp, 4),
            'player_two_scheme_vp' => max(0, $p2Vp - 4),
            'player_two_vp' => $p2Vp,
            'result' => TournamentGameResultEnum::Completed->value,
        ]);
    }

    public function forfeit(int $loserId): self
    {
        return $this->state([
            'is_forfeit' => true,
            'forfeit_player_id' => $loserId,
            'result' => TournamentGameResultEnum::Forfeited->value,
        ]);
    }
}
