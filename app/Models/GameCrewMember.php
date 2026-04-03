<?php

namespace App\Models;

use App\Enums\CharacterStationEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperGameCrewMember
 */
class GameCrewMember extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'station' => CharacterStationEnum::class,
            'is_killed' => 'boolean',
            'is_summoned' => 'boolean',
            'is_activated' => 'boolean',
            'is_custom' => 'boolean',
            'attached_upgrades' => 'array',
            'attached_tokens' => 'array',
            'attached_markers' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gamePlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function customCharacter(): BelongsTo
    {
        return $this->belongsTo(CustomCharacter::class);
    }
}
