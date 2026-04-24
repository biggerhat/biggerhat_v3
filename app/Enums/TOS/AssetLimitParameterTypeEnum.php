<?php

namespace App\Enums\TOS;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * What kind of value the `parameter_value` / parameter FK columns refer to on
 * a given Asset Limit row. Chosen based on the `limit_type`:
 *   Restricted → UnitName (+ parameter_unit_id), UnitType, or Allegiance (+ parameter_allegiance_id)
 *   Slot       → Location (free-form string)
 *   Unique     → null
 *   Adjunct    → SizeMm
 */
enum AssetLimitParameterTypeEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case UnitName = 'unit_name';
    case UnitType = 'unit_type';
    case Allegiance = 'allegiance';
    case Location = 'location';
    case SizeMm = 'size_mm';
}
