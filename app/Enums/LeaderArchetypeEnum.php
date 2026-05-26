<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * The five Leader archetypes for M4E Campaign Mode (Index of the Untold, pg 17).
 * Each archetype fixes the Leader's base Df/Wp/Sp/Health and constrains what
 * actions/abilities can be picked during Leader Build. Stat baselines live on
 * the `leader_archetypes` catalog table; this enum is just the identifier so
 * we can typed-bind the archetype column on `custom_characters`.
 */
enum LeaderArchetypeEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case LuckyUpstart = 'lucky_upstart';
    case Generalist = 'generalist';
    case HeavyHitter = 'heavy_hitter';
    case Schemer = 'schemer';
    case TalentedIndividual = 'talented_individual';
}
