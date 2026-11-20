<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum GameStatusEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Setup = 'setup';
    case FactionSelect = 'faction_select';
    case MasterSelect = 'master_select';
    case CrewSelect = 'crew_select';
    /** Tabletop games only: both crews are placed on the table, in the order the rulebook gives, before schemes are chosen. */
    case Deployment = 'deployment';
    case SchemeSelect = 'scheme_select';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Abandoned = 'abandoned';

    public function isSetupPhase(): bool
    {
        return in_array($this, [self::Setup, self::FactionSelect, self::MasterSelect, self::CrewSelect, self::Deployment, self::SchemeSelect]);
    }

    public function isActive(): bool
    {
        return $this === self::InProgress;
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Abandoned]);
    }
}
