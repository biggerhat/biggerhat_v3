<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * Where a battle report stands on being public. Only an approved report is shown to anyone but its writer: a moderator
 * (a user with the approve_battle_reports permission, or a super admin) reads it first.
 */
enum BattleReportStatusEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    /** Only the writer can see it (the writer has not asked for it to be public). */
    case Private = 'private';
    /** The writer asked for it to be public; waiting for a moderator. */
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Private',
            self::Pending => 'Waiting for a moderator',
            self::Approved => 'Public',
            self::Rejected => 'Not approved',
        };
    }
}
