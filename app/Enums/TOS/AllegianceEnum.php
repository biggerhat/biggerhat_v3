<?php

namespace App\Enums\TOS;

use App\Interfaces\HasDefaultEnumMethods;
use App\Models\TOS\Allegiance;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;
use Illuminate\Support\Facades\Schema;

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
     * Shared-data map of every allegiance keyed by slug. Merges the canonical
     * enum data (short_name, color, fallback logo) with DB overrides so
     * admin-uploaded logos actually show up — and admin-created allegiances
     * (outside the enum) are included too.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function buildDetails(): array
    {
        $details = [];

        // Canonical enum rows first — these provide fallback short_name/color/logo
        // for any DB row that hasn't customised those fields yet.
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

        // Merge DB rows. Guard on Schema::hasTable so console commands that run
        // before migrations (fresh installs, artisan config:cache) don't fail.
        if (Schema::hasTable('tos_allegiances')) {
            foreach (Allegiance::all() as $allegiance) {
                $existing = $details[$allegiance->slug] ?? null;
                $dbLogo = self::resolveLogoUrl($allegiance->logo_path);
                $details[$allegiance->slug] = [
                    'slug' => $allegiance->slug,
                    'name' => $allegiance->name,
                    'short_name' => $allegiance->short_name ?? $existing['short_name'] ?? '',
                    'type' => $allegiance->type->value,
                    'is_syndicate' => (bool) $allegiance->is_syndicate,
                    'color' => $allegiance->color_slug ?? $existing['color'] ?? 'default',
                    'logo' => $dbLogo ?? $existing['logo'] ?? '',
                ];
            }
        }

        return $details;
    }

    /**
     * Normalise a stored image path into a URL the browser can fetch.
     * Absolute URLs and rooted paths pass through; disk-relative paths
     * ("tos/allegiances/foo.png") get the /storage/ prefix.
     */
    private static function resolveLogoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, '/') || str_starts_with($path, 'http')) {
            return $path;
        }

        return '/storage/'.$path;
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
