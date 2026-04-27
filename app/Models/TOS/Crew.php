<?php

namespace App\Models\TOS;

use App\Models\User;
use App\Traits\TOS\GeneratesTosSlug;
use Database\Factories\TOS\CrewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperCrew
 */
class Crew extends Model
{
    /** @use HasFactory<CrewFactory> */
    use GeneratesTosSlug, HasFactory;

    protected $table = 'tos_crews';

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function newFactory(): CrewFactory
    {
        return CrewFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allegiance(): BelongsTo
    {
        return $this->belongsTo(Allegiance::class);
    }

    public function crewUnits(): HasMany
    {
        return $this->hasMany(CrewUnit::class)->orderBy('position');
    }

    /**
     * The (single) commander row, if one has been picked. Maintained purely
     * by the application — we don't enforce uniqueness at the DB layer.
     */
    public function commanderUnit(): HasMany
    {
        return $this->hasMany(CrewUnit::class)->where('is_commander', true);
    }

    /**
     * Sum of unit Scrip costs minus the Commander's contribution (Commanders
     * provide the budget rather than spending it — rulebook p. 9). Convenience
     * helper for Vue badges; exact validation lives in the controller.
     */
    public function scripSpent(): int
    {
        // Reuse already-eager-loaded crewUnits when callers preloaded them
        // (e.g. CrewController::view) — avoids a redundant query per page
        // render. Falls back to a fresh fetch when called in isolation.
        /** @var \Illuminate\Database\Eloquent\Collection<int, CrewUnit> $units */
        $units = $this->relationLoaded('crewUnits')
            ? $this->crewUnits
            : $this->crewUnits()->with('unit:id,scrip')->get();

        $commanderBudget = 0;
        $unitsCost = 0;
        foreach ($units as $cu) {
            $scrip = $cu->unit->scrip ?? 0;
            if ($cu->is_commander) {
                $commanderBudget += $scrip;
            } else {
                $unitsCost += $scrip;
            }
        }

        return $unitsCost - $commanderBudget;
    }
}
