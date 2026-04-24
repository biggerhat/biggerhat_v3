<?php

namespace App\Enums\TOS;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * The Other Side has four canonical Action Types (rulebook p. 22):
 *   # Magic   y Melee   z Missile   ! Morale
 * An Action may carry more than one type, but for Phase 1 we model the primary
 * type as a single enum value; multi-type actions can be added via a future
 * pivot or array column if cards in the wild demand it.
 */
enum ActionTypeEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Magic = 'magic';
    case Melee = 'melee';
    case Missile = 'missile';
    case Morale = 'morale';

    public function symbol(): string
    {
        return match ($this) {
            self::Magic => '#',
            self::Melee => 'y',
            self::Missile => 'z',
            self::Morale => '!',
        };
    }
}
