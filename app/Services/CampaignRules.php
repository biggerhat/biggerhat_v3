<?php

namespace App\Services;

use App\Enums\Campaign\LeaderTagEnum;

/**
 * Pure-function service for M4E Campaign Mode (Index of the Untold) mechanics.
 * Encodes every numeric rule from pages 18–21 of the rulebook so the rest of
 * the codebase (UI, controllers, aftermath wizard) never re-derives them.
 *
 * Every method is static + side-effect-free. Inputs are explicit primitives
 * so the rules can be unit-tested without database fixtures.
 */
class CampaignRules
{
    /**
     * Maximum encounter size for a campaign game.
     *
     * Rules (pg 19): "the maximum encounter size is equal to the size of the
     * smaller arsenal total + 6." Players may agree to play smaller. Games
     * usually work best at 50 or less — we don't enforce that hard cap; the
     * UI surfaces it as a hint.
     */
    public static function maxEncounterSize(int $arsenalA, int $arsenalB): int
    {
        return min($arsenalA, $arsenalB) + 6;
    }

    /**
     * Campaign Rating for a crew (pg 19).
     *
     * "equipment count + advancements (leader + totem) − injury count". CR can
     * be negative; the next-game ss-pool bonus uses signed differences.
     */
    public static function campaignRating(int $equipmentCount, int $advancementCount, int $injuryCount): int
    {
        return $equipmentCount + $advancementCount - $injuryCount;
    }

    /**
     * Soulstone pool bonus for the lower-rated crew (pg 19).
     *
     * "The crew with the lower campaign rating gains a bonus equal to the
     * difference, to a maximum of +3 soulstones." Zero if the asking crew
     * is not strictly lower-rated. May exceed the normal 6-ss pool cap.
     */
    public static function ssPoolBonusForLower(int $crewCr, int $opponentCr): int
    {
        $diff = $opponentCr - $crewCr;

        if ($diff <= 0) {
            return 0;
        }

        return min(3, $diff);
    }

    /**
     * Scrip earned in the Payday phase (pg 21).
     *
     * Base = ceil(vp / 3) — "1 scrip for each 3 VP or portion thereof".
     * + 1 if the crew won.
     * + (opponentCr − crewCr) if the crew is lower-rated.
     */
    public static function scripFromGame(int $vp, bool $won, int $crewCr, int $opponentCr): int
    {
        $base = (int) ceil($vp / 3);
        $winBonus = $won ? 1 : 0;
        $diff = max(0, $opponentCr - $crewCr);

        return $base + $winBonus + $diff;
    }

    /**
     * Experience points the Leader earns from a game (pg 31).
     *
     * +1 always (for playing).
     * +1 if the Leader is a Bruiser and killed at least one non-peon enemy.
     * +1 if the Leader is a Strategist and resolved Interact within 6" of
     *    the enemy deployment zone at least once.
     * +1 if the crew lost.
     *
     * Capped at 3 (the rulebook's "this means your leader may gain a maximum
     * of 3 experience points from a single game"). Equipment / optional rules
     * may add more outside this function.
     */
    public static function xpFromGame(
        LeaderTagEnum $tag,
        bool $bruiserKilledNonPeon,
        bool $strategistInteractedInEnemyDz,
        bool $lost,
    ): int {
        $xp = 1;

        if ($tag === LeaderTagEnum::Bruiser && $bruiserKilledNonPeon) {
            $xp++;
        }

        if ($tag === LeaderTagEnum::Strategist && $strategistInteractedInEnemyDz) {
            $xp++;
        }

        if ($lost) {
            $xp++;
        }

        return min(3, $xp);
    }

    /**
     * Scrip cost to add a model to the crew's arsenal during a New Hires step
     * (pg 18).
     *
     * Base cost = model.cost (soulstone cost).
     * + 1 if the model is out-of-keyword in the declared faction.
     * − 5 if this is the first hire of the week ("the first model a player
     *     adds to their arsenal each week requires 5 fewer scrip").
     *
     * Never returns less than zero — the rulebook is silent on negatives but
     * the practical interpretation is a free hire, not a refund.
     */
    public static function newHireScripCost(int $modelCost, bool $outOfKeywordInFaction, bool $isFirstHireOfWeek): int
    {
        $cost = $modelCost;

        if ($outOfKeywordInFaction) {
            $cost++;
        }

        if ($isFirstHireOfWeek) {
            $cost -= 5;
        }

        return max(0, $cost);
    }

    /**
     * Scoring adjustment for Strategic Withdrawal (pg 20).
     *
     * Returns the VP totals to compare for the game result, accounting for:
     * - Withdrew on turn ≤ 2: withdrawing crew gets 0 scoring credit.
     * - Withdrew on turn ≥ 3: opposing crew counts as scoring +1 VP more
     *   than the withdrawing crew, IF the withdrawing crew has ≥ opponent VP.
     *
     * @return array{withdrew_vp: int, opponent_vp: int}
     */
    public static function withdrawalAdjustedVp(int $withdrewVp, int $opponentVp, int $withdrewTurn): array
    {
        if ($withdrewTurn <= 2) {
            return ['withdrew_vp' => 0, 'opponent_vp' => $opponentVp];
        }

        // Turn 3+: opposing crew scores +1 more than withdrawing crew when
        // withdrawing crew has as many or more VP than the opposing crew.
        if ($withdrewVp >= $opponentVp) {
            return ['withdrew_vp' => $withdrewVp, 'opponent_vp' => $withdrewVp + 1];
        }

        return ['withdrew_vp' => $withdrewVp, 'opponent_vp' => $opponentVp];
    }

    /**
     * Aftermath hand size (pg 20).
     *
     * "1 card for completing the game without using strategic withdrawal" +
     * "1 card for each scheme your crew completed (to a maximum of 3)".
     * Max hand size = 4.
     */
    public static function aftermathHandSize(bool $completedWithoutWithdrawing, int $schemesCompleted): int
    {
        $count = 0;
        if ($completedWithoutWithdrawing) {
            $count++;
        }
        $count += min(3, max(0, $schemesCompleted));

        return min(4, $count);
    }
}
