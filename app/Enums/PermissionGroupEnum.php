<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum PermissionGroupEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case User = 'user';
    case Role = 'role';
    case Blog = 'blog';
    case Keyword = 'keyword';
    case Characteristic = 'characteristic';
    case Character = 'character';
    case Action = 'action';
    case Ability = 'ability';
    case Trigger = 'trigger';
    case Miniature = 'miniature';
    case Upgrade = 'upgrade';
    case Crew = 'crew';
    case Token = 'token';
    case Marker = 'marker';
    case Scheme = 'scheme';
    case Strategy = 'strategy';
    case Package = 'package';
    case Lore = 'lore';
    case Blueprint = 'blueprint';
    case Channel = 'channel';
    case PodLink = 'pod_link';
    case Tournament = 'tournament';
    case Feedback = 'feedback';
    case Tos = 'tos';
    case TosAllegiance = 'tos_allegiance';
    case TosAllegianceCard = 'tos_allegiance_card';
    case TosUnit = 'tos_unit';
    case TosSculpt = 'tos_sculpt';
    case TosSpecialUnitRule = 'tos_special_unit_rule';
    case TosAbility = 'tos_ability';
    case TosAction = 'tos_action';
    case TosTrigger = 'tos_trigger';
    case TosAsset = 'tos_asset';
    case TosStratagem = 'tos_stratagem';
}
