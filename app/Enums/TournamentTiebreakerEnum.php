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

    /**
     * Ordered standings keys applied after TP (see config/tournaments.php).
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return match ($this) {
            self::DiffVp => ['diff', 'vp'],
            self::Sos => ['sos', 'diff', 'vp'],
        };
    }
}
