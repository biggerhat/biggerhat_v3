<?php

namespace App\Models;

use App\Enums\TournamentGameResultEnum;
use App\Enums\TournamentReportStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperTournamentGame
 */
class TournamentGame extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'result' => TournamentGameResultEnum::class,
            'is_bye' => 'boolean',
            'is_forfeit' => 'boolean',
            'score_breakdown' => 'array',
            'report_status' => TournamentReportStatusEnum::class,
            'reported_score' => 'array',
            'reported_at' => 'datetime',
        ];
    }

    /**
     * Score components for one side, keyed by the ruleset's score-field keys
     * (e.g. ['strategy' => 3, 'scheme' => 2]). Empty when none were recorded.
     *
     * @param  'player_one'|'player_two'  $side
     * @return array<string, int>
     */
    public function breakdownFor(string $side): array
    {
        return (array) (((array) $this->score_breakdown)[$side] ?? []);
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

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(TournamentPlayer::class, 'reported_by_player_id');
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
