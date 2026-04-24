<?php

namespace App\Enums\TOS;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * Restriction printed on an Envoy Card (rulebook p. 8). The common values are
 * "Earth" and "Malifaux" — an Envoy with a given restriction may only be taken
 * when the chosen Allegiance matches its Restriction. We include "other" to
 * cover future Envoys with bespoke restriction text.
 */
enum EnvoyRestrictionEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Earth = 'earth';
    case Malifaux = 'malifaux';
    case Other = 'other';
}
