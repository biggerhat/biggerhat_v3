<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/** State of a player-submitted result that hasn't become official yet. */
enum TournamentReportStatusEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    /** Submitted by one player; waiting for the opponent (or the organizer) to confirm. */
    case Pending = 'pending';
    /** The opponent disagrees; the organizer decides. */
    case Disputed = 'disputed';
}
