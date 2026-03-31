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

    protected $fillable = [
        'game_id', 'game_player_id', 'character_id', 'custom_character_id',
        'display_name', 'faction',
        'current_health', 'max_health', 'cost', 'station', 'hiring_category',
        'front_image', 'back_image', 'is_killed', 'is_summoned', 'is_activated',
        'is_custom', 'attached_upgrades', 'attached_tokens', 'attached_markers',
        'sort_order', 'notes',
    ];

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
