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
 * @property string|null $duel_role attacker or resister, when the flip is one side of an opposed duel
 * @property int|null $duel_with the opposing flip of that duel
 * @property int|null $total the duel total (the flip plus the stat), once its player has said what it is
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
