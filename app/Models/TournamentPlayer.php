<?php

namespace App\Models;

use App\Enums\FactionEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Garrison;
use App\Traits\LogsCreationActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperTournamentPlayer
 */
class TournamentPlayer extends Model
{
    use HasFactory, LogsCreationActivity;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'faction' => FactionEnum::class,
            'is_ringer' => 'boolean',
            'is_disqualified' => 'boolean',
            'disqualified_at' => 'datetime',
            'checked_in_at' => 'datetime',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function crewBuild(): BelongsTo
    {
        return $this->belongsTo(CrewBuild::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** TOS rulesets: the Allegiance this player declared for the whole event. */
    public function allegiance(): BelongsTo
    {
        return $this->belongsTo(Allegiance::class, 'allegiance_id');
    }

    /** TOS rulesets: the Garrison (public pool) this player linked for the event. */
    public function garrison(): BelongsTo
    {
        return $this->belongsTo(Garrison::class, 'garrison_id');
    }

    /**
     * Team events: the people on this team, in seat order. Empty for individual players.
     *
     * @return HasMany<TournamentPlayerMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(TournamentPlayerMember::class)->orderBy('position')->orderBy('id');
    }

    /** Competitors a given account plays for: as the player themself, or as a member of the team. */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(fn ($q) => $q->where('user_id', $userId)
            ->orWhereHas('members', fn ($m) => $m->where('user_id', $userId)));
    }

    /**
     * Every account tied to this competitor — the player's own, plus each team member's.
     *
     * @return list<int>
     */
    public function linkedUserIds(): array
    {
        $ids = $this->members->pluck('user_id')->push($this->user_id)->filter()->unique()->values()->all();

        return array_map('intval', $ids);
    }

    public function meta(): BelongsTo
    {
        return $this->belongsTo(Meta::class);
    }

    /**
     * Effective meta for this player — explicit per-tournament override
     * if set, otherwise falls back to the linked user's profile meta.
     */
    public function effectiveMetaId(): ?int
    {
        return $this->meta_id ?? $this->user?->meta_id;
    }

    public function gamesAsPlayerOne(): HasMany
    {
        return $this->hasMany(TournamentGame::class, 'player_one_id');
    }

    public function gamesAsPlayerTwo(): HasMany
    {
        return $this->hasMany(TournamentGame::class, 'player_two_id');
    }

    public function isActive(): bool
    {
        return ! $this->is_disqualified && $this->dropped_after_round === null;
    }

    public function isActiveForRound(int $roundNumber): bool
    {
        return ! $this->is_disqualified
            && ($this->dropped_after_round === null || $this->dropped_after_round >= $roundNumber);
    }
}
