<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum ContentTypeEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Lore = 'lore';
    case DeepDives = 'deep_dives';
    case BattleReports = 'battle_reports';
    case Rules = 'rules';
}
