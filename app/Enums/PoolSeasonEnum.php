<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use Illuminate\Support\Str;

enum PoolSeasonEnum: string implements HasDefaultEnumMethods
{
    case GainingGrounds0 = 'core';
    case GainingGrounds1 = 'gaining_grounds_1';

    public function label(): string
    {
        return match ($this) {
            self::GainingGrounds0 => 'Gaining Grounds 0',
            self::GainingGrounds1 => 'Gaining Grounds 1',
            default => Str::headline($this->name),
        };
    }

    /**
     * The current season — always the most recently added case. New game
     * creation, campaign scenario generation, and tournament setup all seed
     * from this, so a newly added case must have its strategies/schemes
     * seeded before (or immediately after) it lands here.
     */
    public static function defaultSeason(): self
    {
        return self::casesNewestFirst()[0];
    }

    /**
     * Cases ordered most-recently-added first — use for any UI season list
     * (dropdowns, browse pages) so a new season always surfaces at the top.
     * Declaration order in this enum must stay chronological (oldest first)
     * for this to work, and to keep `defaultSeason()` unambiguous.
     *
     * @return array<int, self>
     */
    public static function casesNewestFirst(): array
    {
        return array_reverse(self::cases());
    }

    /** @return array<int, array{name: string, value: string}> */
    public static function toSelectOptions(): array
    {
        return collect(self::casesNewestFirst())->map(fn (self $season) => ['name' => $season->label(), 'value' => $season->value])->toArray();
    }
}
