<?php

namespace App\Models;

use App\Enums\DeploymentEnum;
use App\Enums\GameStatusEnum;
use App\Enums\PoolSeasonEnum;
use App\Traits\LogsCreationActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property GameStatusEnum $status
 * @property DeploymentEnum|null $deployment
 * @property PoolSeasonEnum $season
 * @mixin IdeHelperGame
 */
class Game extends Model
{
    use HasFactory, LogsCreationActivity, SoftDeletes;

    protected $guarded = ['id'];

    protected $appends = ['season_label'];

    public function getSeasonLabelAttribute(): string
    {
        return $this->season?->label() ?? ''; // @phpstan-ignore nullsafe.neverNull
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
            'settings' => 'array',
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

    /** TournamentGame this tracker game was created for, if any. */
    public function tournamentGame(): HasOne
    {
        return $this->hasOne(TournamentGame::class);
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

    /** Games the user participates in (slot 1 or 2) and hasn't hidden. */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->whereHas('players', fn ($q) => $q->where('user_id', $userId)->whereNull('hidden_at'));
    }

    /** Games still being played (setup through in_progress). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [GameStatusEnum::Completed->value, GameStatusEnum::Abandoned->value]);
    }

    /** Games that have finished (completed or abandoned). */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereIn('status', [GameStatusEnum::Completed->value, GameStatusEnum::Abandoned->value]);
    }

    /** Recently-active games opted into public observation, excluding abandoned. */
    public function scopeObservable(Builder $query): Builder
    {
        return $query->where('is_observable', true)
            ->where('updated_at', '>=', now()->subDay())
            ->where('status', '!=', GameStatusEnum::Abandoned->value);
    }
}
