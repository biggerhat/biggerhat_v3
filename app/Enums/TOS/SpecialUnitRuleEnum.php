<?php

namespace App\Enums\TOS;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * Canonical Special Unit Rules from the rulebook (p. 10–11). These are tags
 * applied to a Unit via the `tos_unit_special_rule` pivot, which carries an
 * optional JSON `parameters` column for rule-specific data:
 *   - Fireteam:      { base_mm, models_per_team, model_size_mm }
 *   - Squad:         { fireteam_count }
 *   - Combined Arms: no parameters — the embedded child card lives on
 *                    `tos_units.combined_arms_child_id` (self-FK) so
 *                    there's a single source of truth.
 *   - Reserves:      { x }
 *   - Adjunct:       { size_mm }   (for Asset attachment, future Phase)
 *   - Unique:        no parameters
 *   - Titan:         no parameters
 *   - Champion:      no parameters
 *   - Commander:     no parameters
 */
enum SpecialUnitRuleEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Unique = 'unique';
    case Fireteam = 'fireteam';
    case Titan = 'titan';
    case Commander = 'commander';
    case Squad = 'squad';
    case Champion = 'champion';
    case CombinedArms = 'combined_arms';
    case Reserves = 'reserves';

    public function label(): string
    {
        return match ($this) {
            self::CombinedArms => 'Combined Arms',
            default => match ($this) {
                self::Unique => 'Unique',
                self::Fireteam => 'Fireteam',
                self::Titan => 'Titan',
                self::Commander => 'Commander',
                self::Squad => 'Squad',
                self::Champion => 'Champion',
                self::Reserves => 'Reserves',
                self::CombinedArms => 'Combined Arms',
            },
        };
    }
}
