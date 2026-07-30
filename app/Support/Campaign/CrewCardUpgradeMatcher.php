<?php

namespace App\Support\Campaign;

use App\Enums\CrewUpgradeRestrictionEnum;

/**
 * Determines whether a specific hired model qualifies for a Crew Card
 * effect's restriction (e.g. "Friendly non-Peon Ten Thunders models gain the
 * following action:"), so the Arsenal Sheet can show each Unit which
 * currently-held Crew Card actions/abilities actually apply to it.
 *
 * Ground truth for these restrictions: the generic pg 15-16 Crew Card catalog
 * (see CombinedCrewCardEffects::DEFAULT_RESTRICTION) confirms the
 * FriendlyNonPeonKeyword wording verbatim, and confirms Size/Peon are the
 * model's own Sz stat / station tier. Pg 31-32 (Tier-4 Crew Card Advancement)
 * confirms a borrowed generic effect upgrades to FriendlyNonPeonBothKeywords —
 * "it will affect both of the crew's keywords instead of just one," with the
 * non-peon restriction explicitly retained in the worked examples (see
 * CombinedCrewCardEffects::BORROWED_RESTRICTION). The other restriction variants come
 * from specific per-master Crew Cards not reproduced in the local rulebook
 * PDF — their matching logic here is a best-effort reading of the enum case
 * names themselves, not verified against the printed card text. Two clauses
 * are structurally unverifiable with data this app tracks at all and are
 * conservatively treated as never-true: a "Promoted" token (no token concept
 * exists on a CampaignArsenalModel/CustomCharacter — only in-game
 * GameCrewMember rows track tokens) and a "Story" characteristic (not a
 * seeded Characteristic in this catalog).
 */
class CrewCardUpgradeMatcher
{
    /**
     * @param  array<int, string>  $crewKeywordNames  The crew's own chosen keyword(s).
     * @param  array<int, string>  $modelKeywordNames  This specific model's keyword(s).
     * @param  array<int, string>  $modelCharacteristics  This specific model's characteristics (Unique, Living, Construct, Beast, ...).
     */
    public static function matches(
        ?string $restriction,
        array $crewKeywordNames,
        array $modelKeywordNames,
        array $modelCharacteristics,
        ?string $station,
        ?int $size,
        string $acquiredVia,
    ): bool {
        $enum = $restriction !== null ? CrewUpgradeRestrictionEnum::tryFrom($restriction) : null;
        if ($enum === null) {
            return false;
        }

        $normalizedCrewKeywords = array_map('strtolower', $crewKeywordNames);
        $normalizedModelKeywords = array_map('strtolower', $modelKeywordNames);
        $normalizedCharacteristics = array_map('strtolower', $modelCharacteristics);

        $sharesKeyword = count(array_intersect($normalizedCrewKeywords, $normalizedModelKeywords)) > 0;
        // "Both" keywords (Tier-4 Crew Card Advancement, pg 31-32) — every one
        // of the crew's own chosen keywords must appear on the model. Vacuously
        // false if the crew somehow has no keywords at all (nothing to match).
        $sharesBothKeywords = count($normalizedCrewKeywords) > 0
            && count(array_intersect($normalizedCrewKeywords, $normalizedModelKeywords)) === count($normalizedCrewKeywords);
        $has = fn (string $name): bool => in_array(strtolower($name), $normalizedCharacteristics, true);
        $isPeon = strtolower((string) $station) === 'peon';
        $isMinion = strtolower((string) $station) === 'minion';
        $isUnique = $has('unique');
        $isLiving = $has('living');
        $isBeast = $has('beast');
        $hasGaminKeyword = in_array('gamin', $normalizedModelKeywords, true);
        $withoutSummonToken = $acquiredVia !== 'summon';

        return match ($enum) {
            CrewUpgradeRestrictionEnum::Friendly => true,
            CrewUpgradeRestrictionEnum::FriendlyKeyword => $sharesKeyword,
            CrewUpgradeRestrictionEnum::FriendlyUniqueKeyword => $sharesKeyword && $isUnique,
            CrewUpgradeRestrictionEnum::FriendlyLivingKeyword => $sharesKeyword && $isLiving,
            CrewUpgradeRestrictionEnum::FriendlyConstructKeyword => $sharesKeyword && $has('construct'),
            CrewUpgradeRestrictionEnum::FriendlyNonPeonKeyword => $sharesKeyword && ! $isPeon,
            CrewUpgradeRestrictionEnum::FriendlyNonBeastNonPeonKeyword => $sharesKeyword && ! $isPeon && ! $isBeast,
            // Promoted-token clause is unverifiable (see class docblock) — only the Unique clause is evaluated.
            CrewUpgradeRestrictionEnum::FriendlyUniqueKeywordAndKeywordWithPromotedToken => $sharesKeyword && $isUnique,
            CrewUpgradeRestrictionEnum::FriendlyKeywordSize3OrGreater => $sharesKeyword && $size !== null && $size >= 3,
            CrewUpgradeRestrictionEnum::FriendlyKeywordWithoutSummonToken => $sharesKeyword && $withoutSummonToken,
            CrewUpgradeRestrictionEnum::FriendlyUniqueKeywordAndKeywordWithBeastCharacteristic => $sharesKeyword && ($isUnique || $isBeast),
            // "Story" clause is unverifiable (see class docblock) — no seeded Characteristic matches it, so it never excludes a model.
            CrewUpgradeRestrictionEnum::FriendlyNonStoryKeywordWithoutSummonToken => $sharesKeyword && $withoutSummonToken,
            CrewUpgradeRestrictionEnum::FriendlyKeywordMinion => $sharesKeyword && $isMinion,
            CrewUpgradeRestrictionEnum::FriendlyUniqueKeywordAndKeywordWithTheLivingCharacteristic => $sharesKeyword && ($isUnique || $isLiving),
            CrewUpgradeRestrictionEnum::FriendlyNonGaminKeyword => $sharesKeyword && ! $hasGaminKeyword,
            CrewUpgradeRestrictionEnum::FriendlyNonPeonBothKeywords => $sharesBothKeywords && ! $isPeon,
        };
    }
}
