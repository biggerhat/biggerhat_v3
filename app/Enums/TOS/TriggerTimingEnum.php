<?php

namespace App\Enums\TOS;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * When a Trigger resolves within the Action sequence (rulebook p. 25).
 * By default Triggers resolve during the Resolve Action step. "Immediately"
 * Triggers fire during Declare Triggers and can modify the duel outcome.
 */
enum TriggerTimingEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Default = 'default';
    case Immediately = 'immediately';
}
