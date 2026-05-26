<?php

namespace App\Enums\Campaign;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * Lifecycle states for a Campaign. Planning = settings + invitations editable;
 * Active = play has started, certain settings lock; Ended = retired arsenals,
 * read-only stats / history view.
 */
enum CampaignStatusEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Planning = 'planning';
    case Active = 'active';
    case Ended = 'ended';
}
