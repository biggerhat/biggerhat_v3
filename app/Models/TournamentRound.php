<?php

namespace App\Models;

use App\Enums\DeploymentEnum;
use App\Enums\TournamentRoundStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperTournamentRound
 */
class TournamentRound extends Model
{
    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'status' => TournamentRoundStatusEnum::class,
            'deployment' => DeploymentEnum::class,
            'scheme_pool' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(TournamentGame::class);
    }
}
