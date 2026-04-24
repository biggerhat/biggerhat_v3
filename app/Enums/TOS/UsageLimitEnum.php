<?php

namespace App\Enums\TOS;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * "Once per" frequency limits for Actions and Abilities (rulebook p. 22
 * callout). Null on the parent column means the Action/Ability has no
 * frequency cap.
 */
enum UsageLimitEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case OncePerActivation = 'once_per_activation';
    case OncePerTurn = 'once_per_turn';
    case OncePerGame = 'once_per_game';
}
