<?php

namespace Database\Seeders\TOS;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Stratagem;
use Illuminate\Database\Seeder;

/**
 * Lands Stratagems covering:
 *   • a specific Earth Allegiance (King's Empire)
 *   • any Malifaux-type Allegiance (allegiance_type = malifaux, allegiance_id = null)
 *   • a specific Malifaux Allegiance (Cult of the Burning Man)
 * Tactical costs land in the 1–3 range per rulebook guidance.
 */
class StratagemSeeder extends Seeder
{
    public function run(): void
    {
        $kingsEmpire = Allegiance::firstWhere('slug', 'kings_empire');
        $cult = Allegiance::firstWhere('slug', 'cult_of_the_burning_man');

        $rows = [
            [
                'slug' => 'volley-fire',
                'name' => 'Volley Fire',
                'allegiance' => $kingsEmpire,
                'type' => $kingsEmpire?->type,
                'tactical_cost' => 1,
                'effect' => "Choose a friendly King's Empire Fireteam. It may immediately take a Missile Action.",
            ],
            [
                'slug' => 'malifaux-tide',
                'name' => 'Malifaux Tide',
                'allegiance' => null,
                'type' => AllegianceTypeEnum::Malifaux,
                'tactical_cost' => 2,
                'effect' => 'Any Malifaux Allegiance may draw this. A friendly Fireteam gains +1 Speed this turn.',
            ],
            [
                'slug' => 'burning-ritual',
                'name' => 'Burning Ritual',
                'allegiance' => $cult,
                'type' => $cult?->type,
                'tactical_cost' => 3,
                'effect' => 'Discard two Fate cards. A friendly Cult Fireteam regenerates one Killed model.',
            ],
        ];

        foreach ($rows as $row) {
            Stratagem::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'allegiance_id' => $row['allegiance']?->id,
                    'allegiance_type' => $row['type']?->value,
                    'tactical_cost' => $row['tactical_cost'],
                    'effect' => $row['effect'],
                ],
            );
        }
    }
}
