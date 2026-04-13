<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum TournamentTiebreakerEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    /** Standard Malifaux GG tiebreakers: TP → DIFF → VP */
    case DiffVp = 'diff_vp';

    /** Swiss-style: TP → SoS (sum of opponents' TP, Buchholz) → DIFF → VP */
    case Sos = 'sos';

    public function label(): string
    {
        return match ($this) {
            self::DiffVp => 'Differential / VP (default)',
            self::Sos => 'Strength of Schedule',
        };
    }
}
