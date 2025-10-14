<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum UpgradeTypeEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case PhasesOfTheMoon = 'phases_of_the_moon';
    case Aspect = 'aspect';
    case Mutation = 'mutation';
    case LuxuryAsset = 'luxury_asset';
    case Equipment = 'equipment';
    case Waterlogged = 'waterlogged';
    case Loot = 'loot';
    case Wreathed = 'wreathed';
    case FightingStyle = 'fighting_style';
    case ImprovisedEnhancement = 'improvised_enhancement';
    case Trinket = 'trinket';
    case HermanosDeArmas = 'hermanos_de_armas';
    case Reliquary = 'reliquary';
    case PetalAndBlood = 'petal_and_blood';
    case Artifact = 'artifact';
    case Mimicry = 'mimicry';
    case Injustice = 'injustice';
    case ElementalBinds = 'elemental_binds';
}
