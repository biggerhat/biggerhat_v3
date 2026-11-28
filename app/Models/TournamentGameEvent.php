<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * One change to a tournament game's result (a score entered or corrected, a forfeit set or cleared), with who made it
 * and the result before and after, so a disputed score can be traced.
 *
 * @property int $id
 * @property int $tournament_game_id
 * @property int|null $user_id
 * @property string $action
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read TournamentGame $game
 * @property-read User|null $user
 */
class TournamentGameEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(TournamentGame::class, 'tournament_game_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The result as it is stored on the game, in the shape the history compares. */
    public static function snapshot(TournamentGame $game): array
    {
        return [
            'result' => $game->result->value,
            'player_one_vp' => $game->player_one_vp,
            'player_two_vp' => $game->player_two_vp,
            'is_forfeit' => (bool) $game->is_forfeit,
            'forfeit_player_id' => $game->forfeit_player_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $before  a snapshot() taken before the change
     */
    public static function record(TournamentGame $game, string $action, array $before): self
    {
        return self::create([
            'tournament_game_id' => $game->id,
            'user_id' => Auth::id(),
            'action' => $action,
            'before' => $before,
            'after' => self::snapshot($game->refresh()),
        ]);
    }
}
