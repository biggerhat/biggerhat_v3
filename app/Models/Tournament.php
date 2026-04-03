<?php

namespace App\Models;

use App\Enums\PoolSeasonEnum;
use App\Enums\TournamentStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @mixin IdeHelperTournament
 */
class Tournament extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $appends = ['season_label'];

    public function casts(): array
    {
        return [
            'status' => TournamentStatusEnum::class,
            'season' => PoolSeasonEnum::class,
            'is_public' => 'boolean',
            'event_date' => 'date',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $tournament) {
            if (! $tournament->uuid) {
                $tournament->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function getSeasonLabelAttribute(): string
    {
        /** @var PoolSeasonEnum $season */
        $season = $this->season;

        return $season->label();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function organizers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tournament_organizers');
    }

    public function players(): HasMany
    {
        return $this->hasMany(TournamentPlayer::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(TournamentRound::class)->orderBy('round_number');
    }

    public function activePlayers(): HasMany
    {
        return $this->players()
            ->where('is_disqualified', false)
            ->whereNull('dropped_after_round');
    }

    public function ringerPlayer(): ?TournamentPlayer
    {
        return $this->players()->where('is_ringer', true)->first(); // @phpstan-ignore return.type
    }

    public function isOrganizer(int $userId): bool
    {
        return $this->creator_id === $userId
            || $this->organizers()->where('user_id', $userId)->exists();
    }
}
