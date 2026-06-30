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
    case FriendlyKeywordWithoutSummonToken = 'friendly_keyword_without_summon_token';
    case FriendlyUniqueKeywordAndKeywordWithBeastCharacteristic = 'friendly_unique_keyword_and_keyword_with_beast_characteristic';
    case FriendlyNonStoryKeywordWithoutSummonToken = 'friendly_non_story_keyword_without_summon_token';
    case FriendlyKeywordMinion = 'friendly_keyword_minion';
    case FriendlyUniqueKeywordAndKeywordWithTheLivingCharacteristic = 'friendly_unique_keyword_and_keyword_with_living_characteristic';
    case FriendlyNonGaminKeyword = 'friendly_non_gamin_keyword';

    public function descriptor(CrewUpgradeRestrictionDescriptorTypeEnum $descriptorType): string
    {
        return sprintf("%s models gain the following %s:", $this->label(), $descriptorType->label());
    }
}
