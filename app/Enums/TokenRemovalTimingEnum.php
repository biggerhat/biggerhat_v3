<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * When a game token is automatically removed. Null on the column means the
 * token persists / is removed manually. Only EndOfTurn is auto-handled by the
 * Game Tracker today (on turn advance).
 */
enum TokenRemovalTimingEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case EndOfTurn = 'end_of_turn';
    case EndOfActivation = 'end_of_activation';
}
