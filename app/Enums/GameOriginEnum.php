<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * Where a game's record came from. A tracked game was played through the Game Tracker (setup, turns, live updates). A logged
 * game was played and tracked somewhere else and entered by hand afterwards: it is completed from the start and has no
 * play screen, board or live updates, but it counts in stats like any other.
 */
enum GameOriginEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Tracked = 'tracked';
    case Logged = 'logged';

    public function label(): string
    {
        return match ($this) {
            self::Tracked => 'Game Tracker',
            self::Logged => 'Logged by hand',
        };
    }
}
