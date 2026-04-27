<?php

namespace App\Models;

use App\Enums\FactionEnum;
use App\Traits\LogsCreationActivity;
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
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
