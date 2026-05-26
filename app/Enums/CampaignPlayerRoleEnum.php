<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * A user's role in a Campaign. Organizer additionally gates campaign-edit
 * actions (status transitions, settings, removing players). A campaign always
 * has exactly one organizer at creation; co-organizers can be added later.
 */
enum CampaignPlayerRoleEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Player = 'player';
    case Organizer = 'organizer';
}
