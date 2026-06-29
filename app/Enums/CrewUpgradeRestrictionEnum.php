<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;
use Illuminate\Support\Str;

enum CrewUpgradeRestrictionEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case FriendlyKeyword = 'friendly_keyword';
    case FriendlyUniqueKeyword = 'friendly_unique_keyword';
    case FriendlyLivingKeyword = 'friendly_living_keyword';
    case FriendlyNonPeonKeyword = 'friendly_non_peon_keyword';
    case FriendlyNonBeastNonPeonKeyword = 'friendly_non_beast_non_peon_keyword';
    case FriendlyUniqueKeywordAndKeywordWithPromotedToken = 'friendly_unique_keyword_and_keyword_with_promoted_token';
    case FriendlyKeywordSize3OrGreater = 'friendly_keyword_size_3_or_greater';
}
