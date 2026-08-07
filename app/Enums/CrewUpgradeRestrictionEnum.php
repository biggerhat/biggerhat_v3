<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum CrewUpgradeRestrictionEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case FriendlyKeyword = 'friendly_keyword';
    case FriendlyUniqueKeyword = 'friendly_unique_keyword';
    case FriendlyLivingKeyword = 'friendly_living_keyword';
    case FriendlyConstructKeyword = 'friendly_construct_keyword';
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
    // No keyword qualifier — some crew cards just say "Friendly models gain...".
    case Friendly = 'friendly';
    // Tier-4 Crew Card Advancement upgrades a borrowed generic (pg 15-16
    // catalog) effect's restriction from "either" to "both" of the crew's
    // chosen keywords (pg 31-32: "it will affect both of the crew's keywords
    // instead of just one") — see CombinedCrewCardEffects::BORROWED_RESTRICTION.
    case FriendlyNonPeonBothKeywords = 'friendly_non_peon_both_keywords';

    public function descriptor(CrewUpgradeRestrictionDescriptorTypeEnum $descriptorType): string
    {
        return sprintf('%s gain the following %s:', $this->subjectPhrase(), $descriptorType->label());
    }

    /**
     * Same qualifying text as descriptor(), without naming a specific
     * action/ability/trigger type — used as a single shared header over a
     * group of mixed-type effects that all carry the same restriction,
     * instead of repeating a type-specific line per effect.
     */
    public function descriptorGeneric(): string
    {
        return sprintf('%s gain the following:', $this->subjectPhrase());
    }

    /**
     * FriendlyNonPeonKeyword/FriendlyNonPeonBothKeywords describe the whole
     * crew's default Crew Card restriction (pg 15-16, 31-32), not a single
     * Upgrade's own keyword-attachment restriction — Str::headline() on
     * those case names ("Friendly Non Peon Keyword") reads nothing like the
     * rulebook's actual phrasing, so they get their own fixed text here.
     * Every other case is a real per-Upgrade restriction, where the
     * label-derived phrasing already matches what a real crew card prints.
     */
    private function subjectPhrase(): string
    {
        return match ($this) {
            self::FriendlyNonPeonKeyword => 'Non-peon models in this crew with either of your chosen keywords',
            self::FriendlyNonPeonBothKeywords => 'Non-peon models in this crew with both of your chosen keywords',
            default => sprintf('%s models', $this->label()),
        };
    }
}
