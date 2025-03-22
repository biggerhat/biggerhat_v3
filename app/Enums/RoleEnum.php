<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum RoleEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case SuperAdmin = 'super_admin';
    case ContentCreator = 'content_creator';
    case ImageModerator = 'image_moderator';
    case LoreManager = 'lore_manager';
}
