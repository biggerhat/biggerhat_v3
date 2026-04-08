<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumSelectOptions;

enum EncounterTypeEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumSelectOptions;

    case Traditional = 'traditional';
    case EnforcerBrawl = 'enforcer_brawl';
    case Crossroads = 'crossroads';
    case TeamEvent = 'team_event';
    case DoubleRush = 'double_rush';
    case TotalWar = 'total_war';

    public function label(): string
    {
        return match ($this) {
            self::Traditional => 'Traditional',
            self::EnforcerBrawl => 'Enforcer Brawl',
            self::Crossroads => 'Crossroads',
            self::TeamEvent => 'Team Event',
            self::DoubleRush => 'Double Rush',
            self::TotalWar => 'Total War',
        };
    }
}
