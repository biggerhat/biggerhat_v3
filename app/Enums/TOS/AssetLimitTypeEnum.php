<?php

namespace App\Enums\TOS;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * Asset limitation categories per rulebook p. 12:
 *   - Restricted (X) : must match a specific unit name / type / allegiance
 *   - Slot (Location): one per Company per Slot location
 *   - Unique         : one copy per Company
 *   - Adjunct (size) : swaps a model of matching base size inside a Squad
 */
enum AssetLimitTypeEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Restricted = 'restricted';
    case Slot = 'slot';
    case Unique = 'unique';
    case Adjunct = 'adjunct';
}
