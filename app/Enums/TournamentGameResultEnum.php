<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum TournamentGameResultEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Pending = 'pending';
    case Completed = 'completed';
    case Agreed = 'agreed';
    case Forfeited = 'forfeited';
}
