<?php

namespace App\Models;

use App\Enums\TournamentGameResultEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperTournamentGame
 */
class TournamentGame extends Model
{
    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'result' => TournamentGameResultEnum::class,
            'is_bye' => 'boolean',
            'is_forfeit' => 'boolean',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(TournamentRound::class, 'tournament_round_id');
    }

    public function playerOne(): BelongsTo
    {
        return $this->belongsTo(TournamentPlayer::class, 'player_one_id');
    }

    public function playerTwo(): BelongsTo
    {
        return $this->belongsTo(TournamentPlayer::class, 'player_two_id');
    }

    public function forfeitPlayer(): BelongsTo
    {
        return $this->belongsTo(TournamentPlayer::class, 'forfeit_player_id');
    }

    public function playerOneCrewBuild(): BelongsTo
    {
        return $this->belongsTo(CrewBuild::class, 'player_one_crew_build_id');
    }

    public function playerTwoCrewBuild(): BelongsTo
    {
        return $this->belongsTo(CrewBuild::class, 'player_two_crew_build_id');
    }

    public function trackerGame(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_id');
    }
}
