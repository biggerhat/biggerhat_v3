<?php

namespace Database\Seeders;

use App\Models\Character;
use App\Models\Upgrade;
use Illuminate\Database\Seeder;

class HiringRulesSeeder extends Seeder
{
    public function run(): void
    {
        // On Tour (Upgrade #109) — Fixed Crossroads crew led by Wrath
        $onTour = Upgrade::find(109);
        if ($onTour) {
            $onTour->update([
                'hiring_rules' => [
                    'alternate_leader_id' => 782,
                    'any_faction' => true,
                    'fixed_crew_keyword' => 'crossroads',
                    'fixed_cache' => 6,
                ],
            ]);

            // Link upgrade to Wrath via upgradeables pivot
            $wrath = Character::find(782);
            if ($wrath && ! $onTour->characters()->where('upgradeable_id', 782)->exists()) {
                $onTour->characters()->attach($wrath->id);
            }
        }

        // Riders of Fate (Upgrade #125) — Required Horseman hires
        $ridersOfFate = Upgrade::find(125);
        if ($ridersOfFate) {
            $ridersOfFate->update([
                'hiring_rules' => [
                    'required_characteristic' => 'horseman',
                    'required_count' => 4,
                ],
            ]);
        }
    }
}
