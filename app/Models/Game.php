<?php

namespace App\Models;

use App\Enums\DeploymentEnum;
use App\Enums\GameStatusEnum;
use App\Enums\PoolSeasonEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property GameStatusEnum $status
 * @property DeploymentEnum|null $deployment
 * @property PoolSeasonEnum $season
 *
 * @mixin IdeHelperGame
 */
class Game extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $appends = ['season_label'];

    public function getSeasonLabelAttribute(): string
    {
        return $this->season->label();
    }

    public function casts(): array
    {
        return [
            'status' => GameStatusEnum::class,
            'deployment' => DeploymentEnum::class,
            'season' => PoolSeasonEnum::class,
            'scheme_pool' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'is_tie' => 'boolean',
            'is_solo' => 'boolean',
            'is_observable' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $game) {
            if (! $game->uuid) {
                $game->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(GamePlayer::class)->orderBy('slot');
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }

    public function crewMembers(): HasMany
    {
        return $this->hasMany(GameCrewMember::class);
    }

    public function turns(): HasMany
    {
        return $this->hasMany(GameTurn::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(GameLog::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function playerOne(): ?GamePlayer
    {
        return $this->players->firstWhere('slot', 1);
    }

    public function playerTwo(): ?GamePlayer
    {
        return $this->players->firstWhere('slot', 2);
    }

    public function playerForUser(int $userId): ?GamePlayer
    {
        return $this->players->firstWhere('user_id', $userId);
    }

    public function opponentForUser(int $userId): ?GamePlayer
    {
        return $this->players->first(fn (GamePlayer $p) => $p->user_id !== $userId);
    }

    public function isFull(): bool
    {
        return $this->players->count() >= 2;
    }

    public function schemes()
    {
        return Scheme::whereIn('id', $this->scheme_pool ?? [])->get();
    }
}
