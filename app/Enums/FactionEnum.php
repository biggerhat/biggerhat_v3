<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum FactionEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Arcanists = 'arcanists';
    case Bayou = 'bayou';
    case Guild = 'guild';
    case ExplorersSociety = 'explorers_society';
    case Neverborn = 'neverborn';
    case Outcasts = 'outcasts';
    case Resurrectionists = 'resurrectionists';
    case TenThunders = 'ten_thunders';
}
