<?php

namespace App\Models;

use App\Enums\FateZoneEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Fate card in one player's deck. Its zone says where it is; nothing here is ever sent to a client except through
 * App\Services\Fate\FateDeckService::state(), which decides who may see it.
 *
 * @property int $id
 * @property int $game_id
 * @property int $game_player_id
 * @property int $card
 * @property FateZoneEnum $zone
 * @property int $position
 * @property string|null $joker_suit
 * @property int|null $flip_id
 * @property-read GamePlayer $player
 */
class GameFateCard extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'zone' => FateZoneEnum::class,
            'card' => 'integer',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<GamePlayer, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'game_player_id');
    }
}
