<?php

namespace Database\Seeders\TOS;

use App\Models\TOS\Ability;
use Illuminate\Database\Seeder;

class AbilitySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'slug' => 'fast',
                'name' => 'Fast',
                'body' => 'When this Fireteam is moved by an Advance or Rush Order, it may move an additional 1".',
            ],
            [
                'slug' => 'tough',
                'name' => 'Tough',
                'body' => 'Reduce all damage suffered by this Fireteam by 1, to a minimum of 1.',
            ],
            [
                'slug' => 'reposition',
                'name' => 'Reposition',
                'body' => 'After this Fireteam takes an Action, it may move up to 2".',
            ],
            [
                'slug' => 'cover',
                'name' => 'Cover',
                'body' => 'When this Fireteam would suffer damage from a Missile Action, increase its Armor by +1 against the Action.',
            ],
        ];

        foreach ($rows as $row) {
            Ability::updateOrCreate(
                ['slug' => $row['slug']],
                array_merge($row, ['is_general' => true]),
            );
        }
    }
}
