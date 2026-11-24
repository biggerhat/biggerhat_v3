<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * How far along one physical model in a collection is. The stages are in order: a model that is painted counts as built, and one
 * that is based counts as painted. (The collection's "built" and "painted" figures are read from this.)
 */
enum ModelStageEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Unbuilt = 'unbuilt';
    case Built = 'built';
    case Primed = 'primed';
    case Painted = 'painted';
    case Based = 'based';

    public function label(): string
    {
        return match ($this) {
            self::Unbuilt => 'Unbuilt',
            self::Built => 'Built',
            self::Primed => 'Primed',
            self::Painted => 'Painted',
            self::Based => 'Based (table ready)',
        };
    }

    /** Position in the order, from 0 (unbuilt). */
    public function rank(): int
    {
        return match ($this) {
            self::Unbuilt => 0,
            self::Built => 1,
            self::Primed => 2,
            self::Painted => 3,
            self::Based => 4,
        };
    }

    public function isBuilt(): bool
    {
        return $this->rank() >= self::Built->rank();
    }

    public function isPainted(): bool
    {
        return $this->rank() >= self::Painted->rank();
    }

    /**
     * The stages that count as built (or painted), for queries.
     *
     * @return array<int, string>
     */
    public static function builtValues(): array
    {
        return array_map(fn (self $s) => $s->value, array_filter(self::cases(), fn (self $s) => $s->isBuilt()));
    }

    /**
     * @return array<int, string>
     */
    public static function paintedValues(): array
    {
        return array_map(fn (self $s) => $s->value, array_filter(self::cases(), fn (self $s) => $s->isPainted()));
    }
}
