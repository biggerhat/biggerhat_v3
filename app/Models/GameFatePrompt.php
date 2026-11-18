<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One player asking the other to do something with their Fate hand: discard N cards (their choice) or show their hand. Only the player
 * it is addressed to can answer; a shown hand's cards are given to the asker alone.
 *
 * @property int $id
 * @property int $game_id
 * @property int $from_player_id
 * @property int $to_player_id
 * @property string $kind
 * @property int|null $count
 * @property string|null $note
 * @property string $status
 * @property list<int>|null $cards
 * @property \Illuminate\Support\Carbon|null $dismissed_at
 */
class GameFatePrompt extends Model
{
    public const DISCARD = 'discard';

    public const REVEAL = 'reveal';

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'count' => 'integer',
            'cards' => 'array',
            'dismissed_at' => 'datetime',
        ];
    }
}
