<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum TournamentStatusEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Draft = 'draft';
    case Registration = 'registration';
    case Active = 'active';
    case Completed = 'completed';

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Registration]);
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function isFinished(): bool
    {
        return $this === self::Completed;
    }
}
