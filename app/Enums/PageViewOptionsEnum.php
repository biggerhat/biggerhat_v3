<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum PageViewOptionsEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Images = 'images';
    case KeywordBreakdown = 'keyword_breakdown';
    case Table = 'table';
    case Full = 'full';
}
