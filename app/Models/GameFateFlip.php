<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A flip in progress or just finished: who flipped, the + or - modifier, and which card became the result (a row of game_fate_cards).
 *
 * @property int $id
 * @property int $game_id
 * @property int $game_player_id
 * @property int $modifier
 * @property string|null $label
 * @property string|null $subject what the flip is for on the board (a model's id)
 * @property int|null $chosen_id
 * @property list<int>|null $cheaters game_player ids that have had their chance to cheat
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property-read GamePlayer $player
 */
class GameFateFlip extends Model
{
    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'modifier' => 'integer',
            'cheaters' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<GamePlayer, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'game_player_id');
    }
}
