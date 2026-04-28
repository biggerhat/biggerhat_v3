<?php

namespace App\Models\TOS;

use App\Enums\TOS\AssetLimitTypeEnum;
use App\Traits\TOS\GeneratesTosSlug;
use Database\Factories\TOS\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rule-evaluation helpers (`canAttachTo`, `slotLocations`, `isUnique`,
 * `hasSlotLimit`) all consult `$this->limits`. They each defensively call
 * `loadMissing('limits')`, which is idempotent — but production callers
 * (e.g. `CompanyController::attachAsset`) should still eager-load `limits`
 * up front so the rule walk doesn't trigger N+1 across a large picker list.
 *
 * @mixin IdeHelperAsset
 */
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use GeneratesTosSlug, HasFactory;

    protected $table = 'tos_assets';

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function newFactory(): AssetFactory
    {
        return AssetFactory::new();
    }

    protected static function booted(): void
    {
        static::deleting(function (self $asset) {
            $asset->limits()->delete();
            $asset->allegiances()->detach();
            $asset->abilities()->detach();
            $asset->actions()->detach();
        });
    }

    public function allegiances(): BelongsToMany
    {
        return $this->belongsToMany(Allegiance::class, 'tos_allegiance_asset', 'asset_id', 'allegiance_id');
    }

    public function abilities(): BelongsToMany
    {
        return $this->belongsToMany(Ability::class, 'tos_asset_ability', 'asset_id', 'ability_id')
            ->withPivot('sort_order')
            ->orderBy('tos_asset_ability.sort_order');
    }

    public function actions(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'tos_asset_action', 'asset_id', 'action_id')
            ->withPivot('sort_order')
            ->orderBy('tos_asset_action.sort_order');
    }

    public function limits(): HasMany
    {
        return $this->hasMany(AssetLimit::class, 'asset_id');
    }

    /**
     * Whether this asset can attach to the given unit. Combines:
     *   • the rulebook p. 12 baseline rule — "Assets may only be attached to
     *     units that they share an Allegiance with." If the Asset lists any
     *     Allegiances at all, the target Unit must share at least one.
     *   • every unit-derivable Limit row (Restricted by name / type /
     *     allegiance, Adjunct by size).
     *
     * Slot collisions are per-unit rules but require knowledge of which other
     * Assets are already attached — that's a Company-build concern (Phase 2+).
     * Unique is per-Company — also deferred. Both skip this per-unit check.
     */
    public function canAttachTo(Unit $unit): bool
    {
        $this->loadMissing(['allegiances', 'limits']);
        $unit->loadMissing(['specialUnitRules', 'allegiances']);

        // Baseline allegiance match (rulebook p. 12). Only enforced when the
        // Asset declares at least one Allegiance — universal-Allegiance Assets
        // (rare but legal in expansions) remain unrestricted by this check.
        if ($this->allegiances->isNotEmpty()) {
            $sharedAllegiance = $this->allegiances
                ->pluck('id')
                ->intersect($unit->allegiances->pluck('id'))
                ->isNotEmpty();

            if (! $sharedAllegiance) {
                return false;
            }
        }

        foreach ($this->limits as $limit) {
            if ($limit->limit_type === AssetLimitTypeEnum::Unique || $limit->limit_type === AssetLimitTypeEnum::Slot) {
                continue;
            }

            if (! $limit->matchesUnit($unit)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Slot locations occupied by this Asset. An Asset can list multiple Slot
     * limits (rare but legal — e.g. an Asset that takes both an arm and a
     * back slot); we return them all so the caller can check collisions
     * against every existing attachment on the same unit.
     *
     * @return array<int, string>
     */
    public function slotLocations(): array
    {
        $this->loadMissing('limits');
        $out = [];
        foreach ($this->limits as $limit) {
            if ($limit->limit_type !== AssetLimitTypeEnum::Slot) {
                continue;
            }
            $value = $limit->parameter_value;
            if ($value !== null && $value !== '') {
                $out[] = mb_strtolower($value);
            }
        }

        return $out;
    }

    /**
     * Whether the Asset prints a Slot limit at all. Used to gate slot-
     * collision checks on the call site (`CompanyController::attachAsset`)
     * without forcing the caller to walk the limits list itself.
     */
    public function hasSlotLimit(): bool
    {
        return ! empty($this->slotLocations());
    }

    /**
     * Whether the Asset prints a Unique limit (rulebook p. 12 — "this Asset
     * can only be taken once per Company").
     */
    public function isUnique(): bool
    {
        $this->loadMissing('limits');
        foreach ($this->limits as $limit) {
            if ($limit->limit_type === AssetLimitTypeEnum::Unique) {
                return true;
            }
        }

        return false;
    }
}
