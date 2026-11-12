<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A league: several events whose finishing places add up to one leaderboard (see App\Tournaments\SeriesStandings).
 *
 * @property int $id
 * @property string $uuid
 * @property int $creator_id
 * @property string $name
 * @property string|null $description
 * @property list<int> $points_table
 * @property int $participation_points
 * @property int|null $best_of
 * @property-read User $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Tournament> $tournaments
 * @property-read int|null $tournaments_count
 * @property-read int|null $finished_count
 */
class TournamentSeries extends Model
{
    protected $table = 'tournament_series';

    protected $guarded = ['id'];

    /** Points for 1st..8th when an organizer does not choose their own. */
    public const DEFAULT_POINTS = [10, 8, 6, 5, 4, 3, 2, 1];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $series) {
            $series->uuid ??= (string) Str::uuid();
        });
    }

    public function casts(): array
    {
        return [
            'points_table' => 'array',
            'participation_points' => 'integer',
            'best_of' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /** @return HasMany<Tournament, $this> */
    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class, 'series_id')->orderBy('event_date');
    }
}
