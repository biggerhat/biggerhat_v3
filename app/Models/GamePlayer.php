<?php

namespace App\Models;

use App\Enums\FactionEnum;
use App\Enums\GameRoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property User $user
 * @mixin IdeHelperGamePlayer
 */
class GamePlayer extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'faction' => FactionEnum::class,
            'role' => GameRoleEnum::class,
            'crew_skipped' => 'boolean',
            'is_turn_complete' => 'boolean',
            'is_game_complete' => 'boolean',
            'hidden_at' => 'datetime',
            'scheme_notes' => 'array',
            'scheme_pool' => 'array',
            'crew_upgrade_power_bars' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'master_id');
    }

    public function crewBuild(): BelongsTo
    {
        return $this->belongsTo(CrewBuild::class);
    }

    public function currentScheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class, 'current_scheme_id');
    }

    public function crewMembers(): HasMany
    {
        return $this->hasMany(GameCrewMember::class)->orderBy('sort_order');
    }

    public function turns(): HasMany
    {
        return $this->hasMany(GameTurn::class);
    }
}
