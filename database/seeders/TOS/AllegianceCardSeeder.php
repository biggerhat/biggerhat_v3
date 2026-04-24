<?php

namespace Database\Seeders\TOS;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Models\TOS\Ability;
use App\Models\TOS\Allegiance;
use App\Models\TOS\AllegianceCard;
use Illuminate\Database\Seeder;

/**
 * Lands at least one Allegiance Card per main Allegiance so the database
 * browser shows rules context immediately after a fresh seed.
 */
class AllegianceCardSeeder extends Seeder
{
    public function run(): void
    {
        $mains = Allegiance::query()->mainAllegiances()->get()->keyBy('slug');

        $rows = [
            [
                'slug' => 'kings-empire-card',
                'allegiance' => $mains['kings_empire'] ?? null,
                'name' => "King's Empire",
                'body' => 'The industrial might of a global empire, fielding disciplined rifle corps and steam-driven titans.',
            ],
            [
                'slug' => 'abyssinia-card',
                'allegiance' => $mains['abyssinia'] ?? null,
                'name' => 'Abyssinia',
                'body' => 'An advanced African empire wielding cutting-edge technology and aether-powered war machines.',
            ],
            [
                'slug' => 'cult-of-the-burning-man-card',
                'allegiance' => $mains['cult_of_the_burning_man'] ?? null,
                'name' => 'Cult of the Burning Man',
                'body' => 'A fanatical Malifaux-born cult that summons the dead and the damned to its cause.',
            ],
            [
                'slug' => 'gibbering-hordes-card',
                'allegiance' => $mains['gibbering_hordes'] ?? null,
                'name' => 'Gibbering Hordes',
                'body' => 'Unrelenting tides of twisted creatures boiling up from the depths of Malifaux\'s oceans.',
            ],
        ];

        $fast = Ability::firstWhere('slug', 'fast');
        $tough = Ability::firstWhere('slug', 'tough');

        foreach ($rows as $row) {
            if (! $row['allegiance']) {
                continue;
            }
            $card = AllegianceCard::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'allegiance_id' => $row['allegiance']->id,
                    'name' => $row['name'],
                    'type' => $row['allegiance']->type ?? AllegianceTypeEnum::Earth,
                    'body' => $row['body'],
                ],
            );
            if ($fast && $tough) {
                $card->abilities()->sync([
                    $fast->id => ['sort_order' => 0],
                    $tough->id => ['sort_order' => 1],
                ]);
            }
        }
    }
}
