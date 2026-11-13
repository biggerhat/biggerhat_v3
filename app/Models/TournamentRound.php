<?php

namespace App\Models;

use App\Enums\DeploymentEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Traits\LogsCreationActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperTournamentRound
 */
class TournamentRound extends Model
{
    use HasFactory, LogsCreationActivity;

    protected $guarded = ['id'];

    protected $appends = ['label', 'short_label'];

    public function casts(): array
    {
        return [
            'status' => TournamentRoundStatusEnum::class,
            'deployment' => DeploymentEnum::class,
            'scheme_pool' => 'array',
            'scenario' => 'array',
            'is_cut' => 'boolean',
            'started_at' => 'datetime',
            'last_turn_notified_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * "Round 3", or for a top-cut round the stage it is: Finals, Semifinals, Quarterfinals, "Round of 16".
     * Needs the games loaded to know the stage; without them a cut round is just "Top cut".
     */
    public function getLabelAttribute(): string
    {
        if (! $this->is_cut) {
            return 'Round '.$this->round_number;
        }

        return $this->cutStageName() ?? 'Top cut';
    }

    /** Compact form for tabs and chips: "R3", "F", "SF", "QF", "R16". */
    public function getShortLabelAttribute(): string
    {
        if (! $this->is_cut) {
            return 'R'.$this->round_number;
        }

        return match ($this->cutGameCount()) {
            1 => 'F',
            2 => 'SF',
            4 => 'QF',
            null => 'Cut',
            default => 'R'.($this->cutGameCount() * 2),
        };
    }

    private function cutGameCount(): ?int
    {
        return $this->relationLoaded('games') ? $this->games->where('is_bye', false)->count() : null;
    }

    private function cutStageName(): ?string
    {
        return match ($this->cutGameCount()) {
            null => null,
            1 => 'Finals',
            2 => 'Semifinals',
            4 => 'Quarterfinals',
            default => 'Round of '.($this->cutGameCount() * 2),
        };
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }

    /** @return HasMany<TournamentGame, $this> */
    public function games(): HasMany
    {
        // Order: real games first (by table number), then byes at the bottom.
        return $this->hasMany(TournamentGame::class)
            ->orderBy('is_bye')
            ->orderByRaw('table_number IS NULL')
            ->orderBy('table_number');
    }
}
