<?php

namespace App\Models;

use App\Enums\PermissionEnum;
use App\Enums\PoolSeasonEnum;
use App\Enums\TournamentStatusEnum;
use App\Enums\TournamentTiebreakerEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @mixin IdeHelperTournament
 */
class Tournament extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $appends = ['season_label'];

    public function casts(): array
    {
        return [
            'status' => TournamentStatusEnum::class,
            'season' => PoolSeasonEnum::class,
            'encounter_type' => \App\Enums\EncounterTypeEnum::class,
            'tiebreaker_mode' => TournamentTiebreakerEnum::class,
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

    public function rsvps(): HasMany
    {
        return $this->hasMany(TournamentRsvp::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(TournamentPlayer::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(TournamentRound::class)->orderBy('round_number');
    }

    /**
     * All games across all rounds. Defined so that nested route-model bindings
     * (`/tournaments/{tournament}/games/{game}`) can be scoped, ensuring the
     * resolved game actually belongs to this tournament.
     */
    public function games(): HasManyThrough
    {
        return $this->hasManyThrough(
            TournamentGame::class,
            TournamentRound::class,
            'tournament_id',
            'tournament_round_id',
        );
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

    /**
     * Can this user manage this tournament?
     * True for the creator, invited organizers, or users with the global
     * manage_tournaments permission (super_admin).
     */
    public function canManage(?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        if ($this->isOrganizer($userId)) {
            return true;
        }

        $user = User::find($userId);

        return $user !== null && $user->can(PermissionEnum::ManageTournaments->value);
    }
}
