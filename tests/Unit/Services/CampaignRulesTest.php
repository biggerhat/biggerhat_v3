<?php

use App\Enums\LeaderTagEnum;
use App\Services\CampaignRules;

describe('maxEncounterSize', function () {
    it('returns smaller arsenal + 6', function () {
        expect(CampaignRules::maxEncounterSize(27, 35))->toBe(33);
        expect(CampaignRules::maxEncounterSize(50, 50))->toBe(56);
    });

    it('is symmetric', function () {
        expect(CampaignRules::maxEncounterSize(35, 27))
            ->toBe(CampaignRules::maxEncounterSize(27, 35));
    });
});

describe('campaignRating', function () {
    it('sums equipment and advancements then subtracts injuries', function () {
        expect(CampaignRules::campaignRating(equipmentCount: 2, advancementCount: 2, injuryCount: 1))->toBe(3);
    });

    it('can be negative when injuries exceed gains', function () {
        expect(CampaignRules::campaignRating(equipmentCount: 0, advancementCount: 1, injuryCount: 3))->toBe(-2);
    });
});

describe('ssPoolBonusForLower', function () {
    it('returns 0 when the asking crew is equal or higher rated', function () {
        expect(CampaignRules::ssPoolBonusForLower(crewCr: 3, opponentCr: 3))->toBe(0);
        expect(CampaignRules::ssPoolBonusForLower(crewCr: 5, opponentCr: 2))->toBe(0);
    });

    it('returns the rating difference when strictly lower', function () {
        expect(CampaignRules::ssPoolBonusForLower(crewCr: -1, opponentCr: 1))->toBe(2);
    });

    it('caps the bonus at +3 even for larger gaps', function () {
        expect(CampaignRules::ssPoolBonusForLower(crewCr: -5, opponentCr: 5))->toBe(3);
    });
});

describe('scripFromGame', function () {
    it('rounds VP scrip up (1 scrip per 3 VP or portion thereof)', function () {
        // Rulebook example: 4 VP → 1 + 1 = 2 scrip from VP
        expect(CampaignRules::scripFromGame(vp: 4, won: false, crewCr: 0, opponentCr: 0))->toBe(2);
        // 6 VP → exactly 2 scrip
        expect(CampaignRules::scripFromGame(vp: 6, won: false, crewCr: 0, opponentCr: 0))->toBe(2);
        // 7 VP → 3 scrip
        expect(CampaignRules::scripFromGame(vp: 7, won: false, crewCr: 0, opponentCr: 0))->toBe(3);
    });

    it('adds 1 scrip for winning', function () {
        // Rulebook example: 4 VP + win → 3 scrip
        expect(CampaignRules::scripFromGame(vp: 4, won: true, crewCr: 0, opponentCr: 0))->toBe(3);
    });

    it('adds the CR difference for the lower-rated crew', function () {
        // 4 VP + win + 2-rating lower → 3 + 2 = 5
        expect(CampaignRules::scripFromGame(vp: 4, won: true, crewCr: 1, opponentCr: 3))->toBe(5);
    });

    it('grants no CR bonus to the higher-rated crew', function () {
        expect(CampaignRules::scripFromGame(vp: 4, won: false, crewCr: 5, opponentCr: 2))->toBe(2);
    });
});

describe('xpFromGame', function () {
    it('always grants +1 for playing', function () {
        expect(CampaignRules::xpFromGame(LeaderTagEnum::Bruiser, false, false, false))->toBe(1);
    });

    it('adds 1 XP when Bruiser kills a non-peon enemy', function () {
        expect(CampaignRules::xpFromGame(LeaderTagEnum::Bruiser, true, false, false))->toBe(2);
    });

    it('does not give Bruiser-kill XP to a Strategist', function () {
        expect(CampaignRules::xpFromGame(LeaderTagEnum::Strategist, true, false, false))->toBe(1);
    });

    it('adds 1 XP when Strategist Interacts in the enemy DZ', function () {
        expect(CampaignRules::xpFromGame(LeaderTagEnum::Strategist, false, true, false))->toBe(2);
    });

    it('adds 1 XP for losing', function () {
        expect(CampaignRules::xpFromGame(LeaderTagEnum::Bruiser, false, false, true))->toBe(2);
    });

    it('caps at 3 XP per game', function () {
        // Bruiser, killed, lost — base 1 + 1 + 1 = 3 (cap)
        expect(CampaignRules::xpFromGame(LeaderTagEnum::Bruiser, true, false, true))->toBe(3);
        // Even if every flag is true, capped at 3
        expect(CampaignRules::xpFromGame(LeaderTagEnum::Bruiser, true, true, true))->toBe(3);
    });
});

describe('newHireScripCost', function () {
    it('returns the model cost for in-keyword non-first hires', function () {
        expect(CampaignRules::newHireScripCost(modelCost: 7, outOfKeywordInFaction: false, isFirstHireOfWeek: false))->toBe(7);
    });

    it('adds 1 scrip for out-of-keyword in-faction hires', function () {
        expect(CampaignRules::newHireScripCost(modelCost: 7, outOfKeywordInFaction: true, isFirstHireOfWeek: false))->toBe(8);
    });

    it('subtracts 5 scrip for the first hire of the week', function () {
        expect(CampaignRules::newHireScripCost(modelCost: 8, outOfKeywordInFaction: false, isFirstHireOfWeek: true))->toBe(3);
    });

    it('clamps to zero when the discount exceeds the cost', function () {
        expect(CampaignRules::newHireScripCost(modelCost: 4, outOfKeywordInFaction: false, isFirstHireOfWeek: true))->toBe(0);
    });

    it('stacks out-of-keyword surcharge with first-hire discount', function () {
        expect(CampaignRules::newHireScripCost(modelCost: 8, outOfKeywordInFaction: true, isFirstHireOfWeek: true))->toBe(4);
    });
});

describe('withdrawalAdjustedVp', function () {
    it('zeroes the withdrawing crew on turn 1 or 2', function () {
        expect(CampaignRules::withdrawalAdjustedVp(withdrewVp: 3, opponentVp: 2, withdrewTurn: 1))
            ->toBe(['withdrew_vp' => 0, 'opponent_vp' => 2]);
        expect(CampaignRules::withdrawalAdjustedVp(withdrewVp: 3, opponentVp: 2, withdrewTurn: 2))
            ->toBe(['withdrew_vp' => 0, 'opponent_vp' => 2]);
    });

    it('on turn 3+, gives opponent +1 over withdrawer when withdrawer leads', function () {
        expect(CampaignRules::withdrawalAdjustedVp(withdrewVp: 4, opponentVp: 3, withdrewTurn: 3))
            ->toBe(['withdrew_vp' => 4, 'opponent_vp' => 5]);
    });

    it('on turn 3+, leaves scores alone when opponent is already ahead', function () {
        expect(CampaignRules::withdrawalAdjustedVp(withdrewVp: 2, opponentVp: 5, withdrewTurn: 4))
            ->toBe(['withdrew_vp' => 2, 'opponent_vp' => 5]);
    });

    it('on turn 3+, ties go to the +1 rule', function () {
        // "as many or more VP" — equal counts
        expect(CampaignRules::withdrawalAdjustedVp(withdrewVp: 3, opponentVp: 3, withdrewTurn: 3))
            ->toBe(['withdrew_vp' => 3, 'opponent_vp' => 4]);
    });
});

describe('aftermathHandSize', function () {
    it('counts 1 for no-withdraw + 1 per scheme completed', function () {
        expect(CampaignRules::aftermathHandSize(completedWithoutWithdrawing: true, schemesCompleted: 0))->toBe(1);
        expect(CampaignRules::aftermathHandSize(completedWithoutWithdrawing: true, schemesCompleted: 2))->toBe(3);
    });

    it('caps schemes at 3 and overall hand at 4', function () {
        expect(CampaignRules::aftermathHandSize(completedWithoutWithdrawing: true, schemesCompleted: 5))->toBe(4);
    });

    it('returns 0 when withdrawing turn 1-2 with no schemes', function () {
        expect(CampaignRules::aftermathHandSize(completedWithoutWithdrawing: false, schemesCompleted: 0))->toBe(0);
    });

    it('counts schemes even when withdrawing late', function () {
        expect(CampaignRules::aftermathHandSize(completedWithoutWithdrawing: false, schemesCompleted: 2))->toBe(2);
    });
});
