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
}
