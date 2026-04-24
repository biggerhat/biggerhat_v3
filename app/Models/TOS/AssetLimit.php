<?php

namespace App\Models\TOS;

use App\Enums\TOS\AssetLimitParameterTypeEnum;
use App\Enums\TOS\AssetLimitTypeEnum;
use Database\Factories\TOS\AssetLimitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAssetLimit
 */
class AssetLimit extends Model
{
    /** @use HasFactory<AssetLimitFactory> */
    use HasFactory;

    protected $table = 'tos_asset_limits';

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'limit_type' => AssetLimitTypeEnum::class,
            'parameter_type' => AssetLimitParameterTypeEnum::class,
        ];
    }

    protected static function newFactory(): AssetLimitFactory
    {
        return AssetLimitFactory::new();
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function parameterUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'parameter_unit_id');
    }

    public function parameterAllegiance(): BelongsTo
    {
        return $this->belongsTo(Allegiance::class, 'parameter_allegiance_id');
    }

    /**
     * Does this unit satisfy the limit's unit-derivable constraint? Called by
     * `Asset::canAttachTo()`. Slot / Unique rules skip this check.
     */
    public function matchesUnit(Unit $unit): bool
    {
        return match ($this->parameter_type) {
            AssetLimitParameterTypeEnum::UnitName => $this->matchesByUnitName($unit),
            AssetLimitParameterTypeEnum::UnitType => $this->matchesByUnitType($unit),
            AssetLimitParameterTypeEnum::Allegiance => $this->matchesByAllegiance($unit),
            AssetLimitParameterTypeEnum::SizeMm => $this->matchesBySizeMm($unit),
            AssetLimitParameterTypeEnum::Location, null => true,
        };
    }

    private function matchesByUnitName(Unit $unit): bool
    {
        if ($this->parameter_unit_id) {
            return $this->parameter_unit_id === $unit->id;
        }
        $needle = $this->parameter_value;

        return $needle !== null && (mb_strtolower($unit->name) === mb_strtolower($needle) || $unit->slug === $needle);
    }

    private function matchesByUnitType(Unit $unit): bool
    {
        $needle = $this->parameter_value;
        if ($needle === null) {
            return false;
        }

        return $unit->specialUnitRules->contains(fn ($r) => $r->slug === mb_strtolower($needle));
    }

    private function matchesByAllegiance(Unit $unit): bool
    {
        if ($this->parameter_allegiance_id) {
            return $unit->allegiances->contains(fn ($a) => $a->id === $this->parameter_allegiance_id);
        }
        $needle = $this->parameter_value;

        return $needle !== null && $unit->allegiances->contains(fn ($a) => $a->slug === $needle || $a->name === $needle);
    }

    private function matchesBySizeMm(Unit $unit): bool
    {
        $size = (int) ($this->parameter_value ?? 0);
        if ($size === 0) {
            return false;
        }

        foreach ($unit->specialUnitRules as $rule) {
            $params = $rule->pivot->parameters ?? null;
            if (is_array($params) && isset($params['base_mm']) && (int) $params['base_mm'] === $size) {
                return true;
            }
            if (is_array($params) && isset($params['model_size_mm']) && (int) $params['model_size_mm'] === $size) {
                return true;
            }
        }

        return false;
    }
}
