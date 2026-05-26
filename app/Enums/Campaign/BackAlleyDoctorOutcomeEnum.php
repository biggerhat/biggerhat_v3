<?php

namespace App\Enums\Campaign;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * Outcomes for an Aftermath Phase 5 Back-Alley Doctor flip (Index of the
 * Untold, pg 33). Drives the post-flip resolution in the Aftermath wizard:
 *
 *   - NoEffect: doctor pockets the scrip, injury stays attached
 *   - Removed: clean removal of the targeted injury pivot row
 *   - AddedInjury ("Oops"): doctor inflicts a new injury upgrade instead
 *   - GainedUndead / GainedConstruct: injury removed + model gains a
 *     characteristic (mark on arsenal sheet)
 *   - LuckyMissReflip: red joker — annihilate + reflip on the Lucky Miss
 *     table (handled separately)
 */
enum BackAlleyDoctorOutcomeEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case NoEffect = 'no_effect';
    case Removed = 'removed';
    case AddedInjury = 'added_injury';
    case GainedUndead = 'gained_undead';
    case GainedConstruct = 'gained_construct';
    case LuckyMissReflip = 'lucky_miss_reflip';
}
