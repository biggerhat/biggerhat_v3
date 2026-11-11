<?php

namespace App\Models;

use App\Enums\EncounterTypeEnum;
use App\Enums\PermissionEnum;
use App\Enums\PoolSeasonEnum;
use App\Enums\TournamentStatusEnum;
use App\Enums\TournamentTiebreakerEnum;
use App\Tournaments\Rulesets\Ruleset;
use App\Tournaments\Rulesets\RulesetRegistry;
use App\Tournaments\ScoringConfig;
use App\Traits\LogsCreationActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @mixin IdeHelperTournament
 */
class Tournament extends Model
{
    use HasFactory;
    use LogsCreationActivity;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $appends = ['season_label', 'ruleset_label', 'registration_closed'];

    public function casts(): array
    {
        return [
            'status' => TournamentStatusEnum::class,
            'season' => PoolSeasonEnum::class,
            'encounter_type' => EncounterTypeEnum::class,
            'tiebreaker_mode' => TournamentTiebreakerEnum::class,
            'event_date' => 'date',
            'scoring_config' => 'array',
            'variants' => 'array',
            'allow_self_report' => 'boolean',
            'announced_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'require_crew_lists' => 'boolean',
            'details' => 'array',
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

    /** The format this tournament is run under (unknown/missing keys fall back to the default ruleset). */
    public function rulesetDefinition(): Ruleset
    {
        return app(RulesetRegistry::class)->resolve($this->attributes['ruleset'] ?? null);
    }

    /**
     * Effective scoring parameters: ruleset defaults, then the per-tournament bye
     * overrides (legacy bye_* columns, null = use the ruleset's), then any
     * `scoring_config` JSON overrides.
     */
    public function scoringConfig(): ScoringConfig
    {
        return $this->rulesetDefinition()->scoring
            ->with([
                'bye_tp' => $this->attributes['bye_tp'] ?? null,
                'bye_diff' => $this->attributes['bye_diff'] ?? null,
                'bye_vp' => $this->attributes['bye_vp'] ?? null,
            ])
            ->with((array) ($this->scoring_config ?? []));
    }

    /**
     * Ordered standings tiebreaker keys after TP. A ruleset that lets the TO choose
     * (Gaining Grounds) follows `tiebreaker_mode`; otherwise the ruleset's own order
     * applies. A `scoring_config.tiebreakers` list overrides both.
     *
     * @return list<string>
     */
    public function tiebreakerKeys(): array
    {
        $override = Arr::get((array) ($this->scoring_config ?? []), 'tiebreakers');
        if (is_array($override) && $override !== []) {
            return self::sanitizeTiebreakers($override);
        }

        $ruleset = $this->rulesetDefinition();
        if ($ruleset->tiebreakerToggle) {
            /** @var TournamentTiebreakerEnum $mode */
            $mode = $this->tiebreaker_mode ?? TournamentTiebreakerEnum::DiffVp;

            return $mode->keys();
        }

        return $ruleset->tiebreakers;
    }

    /**
     * Variants the TO enabled, limited to the ruleset's catalog (a removed/renamed variant is ignored).
     * Boolean variants map to true; parameterised ones to their number.
     *
     * @return array<string, bool|int>
     */
    public function activeVariants(): array
    {
        $catalog = $this->rulesetDefinition()->variantsByKey();
        $active = [];
        foreach ((array) ($this->variants ?? []) as $key => $value) {
            $def = $catalog[$key] ?? null;
            if ($def === null) {
                continue;
            }
            if ($def['param'] !== null) {
                $active[$key] = (int) $value;
            } elseif ($value) {
                $active[$key] = true;
            }
        }

        return $active;
    }

    /**
     * Keep the TO's order (array_intersect would reorder to the canonical sos/diff/vp), drop unknown keys and repeats.
     *
     * @param  array<mixed>  $keys
     * @return list<string>
     */
    private static function sanitizeTiebreakers(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            if (is_string($key) && in_array($key, ['sos', 'diff', 'vp'], true) && ! in_array($key, $out, true)) {
                $out[] = $key;
            }
        }

        return $out;
    }

    public function getRulesetLabelAttribute(): string
    {
        return $this->rulesetDefinition()->label;
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

    /** @return HasMany<TournamentAward, $this> */
    public function awards(): HasMany
    {
        return $this->hasMany(TournamentAward::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<TournamentTable, $this> */
    public function tables(): HasMany
    {
        return $this->hasMany(TournamentTable::class)->orderBy('number');
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

    /** A team event pairs and ranks teams of `team_size` people instead of individuals. */
    /** Registered competitors (players, or teams in team events); the ringer doesn't take a seat. */
    public function competitorCount(): int
    {
        return $this->players()->where('is_ringer', false)->count();
    }

    /** True once the organizer's registration deadline has passed (new RSVPs are refused; cancelling stays possible). */
    public function getRegistrationClosedAttribute(): bool
    {
        $closes = $this->registration_closes_at;

        return $closes !== null && $closes->isPast();
    }

    public function isFull(): bool
    {
        return $this->max_players !== null && $this->competitorCount() >= $this->max_players;
    }

    /**
     * User ids of the RSVPs currently waiting for a seat, in waitlist order.
     *
     * @return array<int, int>
     */
    public function waitlistedUserIds(): array
    {
        $positions = $this->waitlistPositions();
        if ($positions === []) {
            return [];
        }

        $userIds = $this->rsvps()->whereIn('id', array_keys($positions))->pluck('user_id', 'id');

        return collect($positions)->sort()->keys()->map(fn ($rsvpId) => (int) $userIds->get($rsvpId))->values()->all();
    }

    /**
     * Waitlist position (1 = next in) for each RSVP that doesn't fit under the player limit, keyed by RSVP id.
     * RSVPs are taken oldest first, and users who are already registered don't count as waiting. Individual events only:
     * in team events an RSVP is a person while the limit counts teams.
     *
     * @return array<int, int>
     */
    public function waitlistPositions(): array
    {
        if ($this->max_players === null || $this->isTeamEvent()) {
            return [];
        }

        $registeredUserIds = $this->players()->whereNotNull('user_id')->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $remaining = max(0, $this->max_players - $this->competitorCount());
        $positions = [];
        $position = 0;

        foreach ($this->rsvps()->orderBy('created_at')->orderBy('id')->get(['id', 'user_id']) as $rsvp) {
            /** @var TournamentRsvp $rsvp */
            if (in_array((int) $rsvp->user_id, $registeredUserIds, true)) {
                continue;
            }
            if ($remaining > 0) {
                $remaining--;

                continue;
            }
            $positions[(int) $rsvp->id] = ++$position;
        }

        return $positions;
    }

    public function isTeamEvent(): bool
    {
        return (int) ($this->attributes['team_size'] ?? 1) > 1;
    }

    public function ringerPlayer(): ?TournamentPlayer
    {
        return $this->players()->where('is_ringer', true)->first(); // @phpstan-ignore return.type
    }

    /** Scorekeepers: may enter and correct results from the scoring desk, nothing else. */
    public function scorers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tournament_scorers')->withTimestamps();
    }

    public function isScorer(int $userId): bool
    {
        return $this->scorers()->where('user_id', $userId)->exists();
    }

    /** Can this user enter results? Everyone who can manage the event, plus its scorekeepers. */
    public function canScore(?int $userId): bool
    {
        return $userId !== null && ($this->canManage($userId) || $this->isScorer($userId));
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

    /** Tournaments the user owns or is an invited organizer on. */
    public function scopeForOrganizer(Builder $query, int $userId): Builder
    {
        return $query->where(fn ($q) => $q->where('creator_id', $userId)
            ->orWhereHas('organizers', fn ($o) => $o->where('user_id', $userId)));
    }
}
