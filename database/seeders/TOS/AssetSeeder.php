<?php

namespace Database\Seeders\TOS;

use App\Enums\TOS\AssetLimitParameterTypeEnum;
use App\Enums\TOS\AssetLimitTypeEnum;
use App\Models\TOS\Ability;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Asset;
use App\Models\TOS\AssetLimit;
use App\Models\TOS\Unit;
use Illuminate\Database\Seeder;

/**
 * Lands at least one Asset per Limit type so the database browser surfaces
 * the full limit matrix immediately after a fresh seed, plus one Asset that
 * combines Restricted + Slot to exercise multi-limit rows.
 */
class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $kingsEmpire = Allegiance::firstWhere('slug', 'kings_empire');
        $cult = Allegiance::firstWhere('slug', 'cult_of_the_burning_man');
        $earlBurns = Unit::firstWhere('slug', 'earl-burns');
        $royalRifleCorps = Unit::firstWhere('slug', 'royal-rifle-corps');

        $tough = Ability::firstWhere('slug', 'tough');
        $fast = Ability::firstWhere('slug', 'fast');

        // 1. Restricted by Unit Name — Earl Burns only.
        $this->asset('earls-saber', "Earl's Saber", 2, 'A ceremonial blade blessed for command.', [
            ['type' => AssetLimitTypeEnum::Restricted, 'param_type' => AssetLimitParameterTypeEnum::UnitName, 'value' => $earlBurns?->slug, 'unit_id' => $earlBurns?->id],
        ], allegiance: $kingsEmpire, abilities: [$tough]);

        // 2. Restricted by Unit Type (Special Unit Rule slug) — Commanders only.
        $this->asset('officers-insignia', "Officer's Insignia", 2, 'Marks a commander so their Company rallies on sight.', [
            ['type' => AssetLimitTypeEnum::Restricted, 'param_type' => AssetLimitParameterTypeEnum::UnitType, 'value' => 'commander'],
        ], allegiance: $kingsEmpire, abilities: [$fast]);

        // 3. Restricted by Allegiance — King's Empire only.
        $this->asset('empire-colors', "Empire's Colors", 1, 'A rallying banner of the King\'s forces.', [
            ['type' => AssetLimitTypeEnum::Restricted, 'param_type' => AssetLimitParameterTypeEnum::Allegiance, 'value' => $kingsEmpire?->slug, 'allegiance_id' => $kingsEmpire?->id],
        ], allegiance: $kingsEmpire);

        // 4. Slot by Location.
        $this->asset('head-mounted-scope', 'Head-Mounted Scope', 3, 'A prized optic — no unit may carry two head mounts.', [
            ['type' => AssetLimitTypeEnum::Slot, 'param_type' => AssetLimitParameterTypeEnum::Location, 'value' => 'Head'],
        ]);

        // 5. Unique (Company-scope).
        $this->asset('the-soul-forge', 'The Soul Forge', 4, 'A one-of-a-kind relic; a Company may include only one.', [
            ['type' => AssetLimitTypeEnum::Unique],
        ], allegiance: $cult);

        // 6. Adjunct by size — 30mm.
        $this->asset('aetheric-commissar', 'Aetheric Commissar', 3, 'A replacement officer that walks into the ranks of a 30mm Squad.', [
            ['type' => AssetLimitTypeEnum::Adjunct, 'param_type' => AssetLimitParameterTypeEnum::SizeMm, 'value' => '30'],
        ]);

        // 7. Combined — Restricted by Unit + Slot.
        $this->asset('royal-warhorn', 'Royal Warhorn', 3, 'Signature gear: one per Royal Rifle Corps, one Horn slot per Company.', [
            ['type' => AssetLimitTypeEnum::Restricted, 'param_type' => AssetLimitParameterTypeEnum::UnitName, 'value' => $royalRifleCorps?->slug, 'unit_id' => $royalRifleCorps?->id],
            ['type' => AssetLimitTypeEnum::Slot, 'param_type' => AssetLimitParameterTypeEnum::Location, 'value' => 'Horn'],
        ], allegiance: $kingsEmpire);
    }

    /**
     * @param  array<int, array{type: AssetLimitTypeEnum, param_type?: AssetLimitParameterTypeEnum, value?: string|null, unit_id?: int|null, allegiance_id?: int|null}>  $limits
     * @param  array<int, Ability|null>  $abilities
     */
    private function asset(string $slug, string $name, int $cost, string $body, array $limits, ?Allegiance $allegiance = null, array $abilities = []): Asset
    {
        $asset = Asset::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'scrip_cost' => $cost,
                'body' => $body,
            ],
        );

        if ($allegiance) {
            $asset->allegiances()->syncWithoutDetaching([$allegiance->id]);
        }

        foreach ($abilities as $i => $ability) {
            if ($ability) {
                $asset->abilities()->syncWithoutDetaching([$ability->id => ['sort_order' => $i]]);
            }
        }

        // Reseed limits deterministically.
        $asset->limits()->delete();
        foreach ($limits as $l) {
            AssetLimit::create([
                'asset_id' => $asset->id,
                'limit_type' => $l['type'],
                'parameter_type' => $l['param_type'] ?? null,
                'parameter_value' => $l['value'] ?? null,
                'parameter_unit_id' => $l['unit_id'] ?? null,
                'parameter_allegiance_id' => $l['allegiance_id'] ?? null,
            ]);
        }

        return $asset;
    }
}
