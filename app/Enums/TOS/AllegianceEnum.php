<?php

namespace App\Enums\TOS;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum AllegianceEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case KingsEmpire = 'kings_empire';
    case Abyssinia = 'abyssinia';
    case CultOfTheBurningMan = 'cult_of_the_burning_man';
    case GibberingHordes = 'gibbering_hordes';
    case CourtOfTwo = 'court_of_two';

    /**
     * Static derivative of all enum cases — called from the Inertia shared
     * data on every request, so memoize per-process to skip the loop.
     * Enums can't carry class-level properties, so we memoize via a static
     * local instead.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function buildDetails(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $details = [];
        foreach (self::cases() as $case) {
            $details[$case->value] = [
                'slug' => $case->value,
                'name' => $case->label(),
                'short_name' => $case->shortName(),
                'type' => $case->type()->value,
                'is_syndicate' => $case->isSyndicate(),
                'color' => $case->color(),
                'logo' => $case->logo(),
            ];
        }

        return $cache = $details;
    }

    public function label(): string
    {
        return match ($this) {
            self::KingsEmpire => "King's Empire",
            self::Abyssinia => 'Abyssinia',
            self::CultOfTheBurningMan => 'Cult of the Burning Man',
            self::GibberingHordes => 'Gibbering Hordes',
            self::CourtOfTwo => 'Court of Two',
        };
    }

    public function shortName(): string
    {
        return match ($this) {
            self::KingsEmpire => 'KE',
            self::Abyssinia => 'Aby',
            self::CultOfTheBurningMan => 'Cult',
            self::GibberingHordes => 'Hordes',
            self::CourtOfTwo => 'Court',
        };
    }

    public function type(): AllegianceTypeEnum
    {
        return match ($this) {
            self::KingsEmpire, self::Abyssinia => AllegianceTypeEnum::Earth,
            self::CultOfTheBurningMan, self::GibberingHordes, self::CourtOfTwo => AllegianceTypeEnum::Malifaux,
        };
    }

    public function isSyndicate(): bool
    {
        return match ($this) {
            self::CourtOfTwo => true,
            default => false,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::KingsEmpire => 'kingsempire',
            self::Abyssinia => 'abyssinia',
            self::CultOfTheBurningMan => 'cult',
            self::GibberingHordes => 'hordes',
            self::CourtOfTwo => 'courtoftwo',
        };
    }

    public function logo(): string
    {
        return match ($this) {
            self::KingsEmpire => '/images/TOS/Allegiances/kings-empire.png',
            self::Abyssinia => '/images/TOS/Allegiances/abyssinia.png',
            self::CultOfTheBurningMan => '/images/TOS/Allegiances/cult-of-the-burning-man.png',
            self::GibberingHordes => '/images/TOS/Allegiances/gibbering-hordes.png',
            self::CourtOfTwo => '/images/TOS/Allegiances/court-of-two.png',
        };
    }
}
