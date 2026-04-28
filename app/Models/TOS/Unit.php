<?php

namespace App\Models\TOS;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Enums\TOS\UnitSideEnum;
use App\Traits\TOS\GeneratesTosSlug;
use Database\Factories\TOS\UnitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @mixin IdeHelperUnit
 */
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use GeneratesTosSlug, HasFactory;

    protected $table = 'tos_units';

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function casts(): array
    {
        return [
            'restriction' => AllegianceTypeEnum::class,
        ];
    }

    /**
     * Glory-side Tactics value, falling back to the Standard `tactics`
     * column when no Glory-specific override is set. Most Units have the
     * same Tactics on both sides, so storing the override only when it
     * differs keeps the data tight without forcing every row to duplicate.
     */
    public function effectiveGloryTactics(): ?string
    {
        return $this->glory_tactics ?? $this->tactics;
    }

    /**
     * Whether the Unit's Glory Tactics value differs from its Standard one.
     * Used by the UI to decide when to surface the Glory-side number
     * separately (most Units don't need the dual badge).
     */
    public function hasDistinctGloryTactics(): bool
    {
        return $this->glory_tactics !== null && $this->glory_tactics !== $this->tactics;
    }

    protected static function newFactory(): UnitFactory
    {
        return UnitFactory::new();
    }

    /**
     * Cascade child rows manually so the test env (which runs SQLite without
     * foreign-key enforcement — see `.env.testing DB_FOREIGN_KEYS=false`)
     * still reflects production behaviour. The migration also declares
     * `cascadeOnDelete` for production safety.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $unit) {
            // Bulk delete — children have no further deleting hooks of their
            // own, so a single DELETE per relation is correct AND much cheaper
            // than iterating with `each(->delete())` (1 query vs N).
            $unit->sides()->delete();
            $unit->sculpts()->delete();
            $unit->allegiances()->detach();
            $unit->specialUnitRules()->detach();
        });
    }

    public function sides(): HasMany
    {
        return $this->hasMany(UnitSide::class, 'unit_id');
    }

    public function sculpts(): HasMany
    {
        return $this->hasMany(UnitSculpt::class, 'unit_id');
    }

    /**
     * TOS units plug into the shared `packageables` morph pivot — same table
     * Malifaux Characters use — so a Starter Box / Campaign Box can list TOS
     * contents alongside its Malifaux entries.
     *
     * @return MorphToMany<\App\Models\Package, $this>
     */
    public function packages(): MorphToMany
    {
        return $this->morphToMany(\App\Models\Package::class, 'packageable')->withPivot('quantity');
    }

    public function allegiances(): BelongsToMany
    {
        return $this->belongsToMany(Allegiance::class, 'tos_allegiance_unit', 'unit_id', 'allegiance_id');
    }

    public function specialUnitRules(): BelongsToMany
    {
        return $this->belongsToMany(SpecialUnitRule::class, 'tos_unit_special_rule', 'unit_id', 'special_unit_rule_id')
            ->using(UnitSpecialRulePivot::class)
            ->withPivot('parameters');
    }

    public function combinedArmsChild(): BelongsTo
    {
        return $this->belongsTo(self::class, 'combined_arms_child_id');
    }

    /**
     * Reverse self-relation pointing back at the parent unit that embeds this
     * one as its Combined Arms child (rulebook p. 11). The public unit index
     * intentionally surfaces children too so users can read every card; this
     * relation backs the `notCombinedArmsChild()` scope used by the command
     * palette + company builder, where only the parent should appear.
     */
    public function combinedArmsParent(): HasOne
    {
        return $this->hasOne(self::class, 'combined_arms_child_id');
    }

    public function standardSide(): ?UnitSide
    {
        return $this->sides->firstWhere('side', UnitSideEnum::Standard);
    }

    public function glorySide(): ?UnitSide
    {
        return $this->sides->firstWhere('side', UnitSideEnum::Glory);
    }

    /**
     * Top-level listings should exclude units that are Combined Arms children of
     * another unit — the child card displays inline on its parent (rulebook p. 11)
     * and should not appear as a standalone row.
     */
    public function scopeNotCombinedArmsChild(Builder $query): Builder
    {
        return $query->whereDoesntHave('combinedArmsParent');
    }

    /**
     * Units that may be hired into the given Allegiance. A unit qualifies
     * if it's attached to the Allegiance directly via the
     * `tos_allegiance_unit` pivot, OR carries a Neutral `restriction`
     * matching ANY of the Allegiance's types (rulebook "Neutral (Earth)"
     * / "Neutral (Malifaux)" pools — a hybrid Allegiance pulls in both).
     */
    public function scopeHireableInto(Builder $query, Allegiance $allegiance): Builder
    {
        return $query->where(function (Builder $q) use ($allegiance) {
            $q->whereHas('allegiances', fn (Builder $inner) => $inner->where('tos_allegiances.id', $allegiance->id))
                ->orWhereIn('restriction', $allegiance->typeValues());
        });
    }
}
