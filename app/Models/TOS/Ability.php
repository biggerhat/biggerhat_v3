<?php

namespace App\Models\TOS;

use App\Enums\TOS\UsageLimitEnum;
use App\Traits\TOS\GeneratesTosSlug;
use Database\Factories\TOS\AbilityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperAbility
 */
class Ability extends Model
{
    /** @use HasFactory<AbilityFactory> */
    use GeneratesTosSlug, HasFactory;

    protected $table = 'tos_abilities';

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function casts(): array
    {
        return [
            'is_general' => 'boolean',
            'usage_limit' => UsageLimitEnum::class,
        ];
    }

    protected static function newFactory(): AbilityFactory
    {
        return AbilityFactory::new();
    }

    /**
     * Manual cascade mirrors the DB-level `cascadeOnDelete` on the pivot FK,
     * because the test env runs SQLite with FK enforcement off (see `.env.testing`
     * DB_FOREIGN_KEYS=false). Production (MySQL) relies on the constraint; tests
     * rely on this hook. Same pattern as `Unit::booted`.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $ability) {
            $ability->unitSides()->detach();
        });
    }

    public function allegiance(): BelongsTo
    {
        return $this->belongsTo(Allegiance::class, 'allegiance_id');
    }

    public function unitSides(): BelongsToMany
    {
        return $this->belongsToMany(UnitSide::class, 'tos_unit_side_ability', 'ability_id', 'unit_side_id');
    }

    public function scopeGeneral(Builder $query): Builder
    {
        return $query->where('is_general', true);
    }

    /**
     * Abilities usable by a given Allegiance — general-pool abilities plus
     * any that are allegiance-specific to the passed allegiance.
     */
    public function scopeForAllegiance(Builder $query, Allegiance|int $allegiance): Builder
    {
        $id = $allegiance instanceof Allegiance ? $allegiance->id : $allegiance;

        return $query->where(function (Builder $q) use ($id) {
            $q->where('is_general', true)->orWhere('allegiance_id', $id);
        });
    }
}
