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
 *   - RemovedAndReflip (flip 9, "How many fingers do you need?"): the targeted
 *     injury is annihilated, then the model reflips on the injury chart for a
 *     fresh injury (pg 33)
 *   - AddedInjury ("Oops"): the original stays and the doctor inflicts a NEW
 *     injury upgrade on top
 *   - GainedUndead / GainedConstruct: injury removed + model gains a
 *     characteristic (mark on arsenal sheet)
 *   - LuckyMissReflip: red joker — annihilate + reflip on the Lucky Miss table
 */
enum BackAlleyDoctorOutcomeEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case NoEffect = 'no_effect';
    case Removed = 'removed';
    case RemovedAndReflip = 'removed_and_reflip';
    case AddedInjury = 'added_injury';
    case GainedUndead = 'gained_undead';
    case GainedConstruct = 'gained_construct';
    case LuckyMissReflip = 'lucky_miss_reflip';
}
