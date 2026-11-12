<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A side award an organizer hands out at the end of an event (best painted, sportsmanship, ...), independent of the standings.
 *
 * @property int $id
 * @property int $tournament_id
 * @property string $name
 * @property int|null $player_id
 * @property string|null $note
 * @property int $sort_order
 * @property-read Tournament $tournament
 * @property-read TournamentPlayer|null $player
 */
class TournamentAward extends Model
{
    protected $guarded = ['id'];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(TournamentPlayer::class, 'player_id');
    }
}
