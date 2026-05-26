<?php

namespace App\Enums\Campaign;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * Bruiser vs Strategist tag picked during Leader Build (pg 18). Drives the
 * conditional +1 XP per game: Bruiser earns +1 for killing a non-peon enemy;
 * Strategist earns +1 for resolving Interact within 6" of the enemy
 * deployment zone.
 */
enum LeaderTagEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Bruiser = 'bruiser';
    case Strategist = 'strategist';
}
