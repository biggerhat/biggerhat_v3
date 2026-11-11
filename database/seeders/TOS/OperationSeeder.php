<?php

namespace Database\Seeders\TOS;

use App\Models\TOS\Operation;
use Illuminate\Database\Seeder;

/**
 * The four Operations printed in the Fields of Glory v3.0 tournament packet. Fields of Glory
 * events also use the Core Rulebook Operations, which admins add through the admin UI.
 * Idempotent: re-running updates rows by slug.
 */
class OperationSeeder extends Seeder
{
    public function run(): void
    {
        $commanders = 'When a Commander unit is Killed, the opposing Company scores 2 Victory Points.';

        $rows = [
            [
                'slug' => 'deep-recovery',
                'name' => 'Deep Recovery',
                'flavor' => 'High Command has identified several key pieces of intel in enemy territory that would be instrumental in tipping the scales. Get in, grab the goods, and get out, preferably with your head still attached.',
                'additional_setup' => 'Players alternate placing a total of four variable Objective Markers centered on the centerline. These Markers must be placed at least 8" from another Objective Marker if possible.',
                'special_rules' => "Objective Tokens in this Operation are gained and held by specific Fireteams. They are not shared across a unit, and must remain with the Fireteam that gained them.\n\nUnengaged Fireteams gain the following Action:\n\nCapture: Discard a Tactics Token to remove target Objective Marker within LoS and 3\" that is on the enemy table half. This Fireteam gains an Objective Token.\n\nAfter a Fireteam with an Objective Token is Killed, one enemy Fireteam within 2\" of it may discard a Tactics Token to gain an Objective Token.",
                'victory_conditions' => "{$commanders}\n\nOnce per Turn, a Company scores 1 VP after an enemy Fireteam with an Objective Token is Killed.\n\nAt the end of the Turn, a Company may discard an Objective Token from one friendly Fireteam to gain VP in the following ways:\n• 1 VP if the Fireteam is on the enemy table half.\n• 2 VP if the Fireteam is on the friendly table half.\n• 3 VP if the Fireteam is in your Deployment Zone.\n\nAt the end of the Turn, each Company alternates placing a variable Objective Marker completely on their table half, each Marker within 12\" of the centerline. These Markers must be placed at least 8\" from another Objective Marker if possible.",
            ],
            [
                'slug' => 'build-fortifications',
                'name' => 'Build Fortifications',
                'flavor' => 'Command needs your Company to break through the enemy\'s frontlines, then hold and fortify your position so that supply chains can be established for future operations.',
                'additional_setup' => null,
                'special_rules' => "Unengaged Fireteams gain the following Actions:\n\nDeploy Depot: Once per Activation. Discard a Tactics Token to place an Objective Marker within 2\" and completely on the enemy table half. Objective Markers may not be placed within 5\" of any other Objective Marker.\n\nDespoil Depot: Discard a Tactics Token to remove target enemy Objective Marker within 5\" and score 1 VP.",
                'victory_conditions' => "{$commanders}\n\nAt the end of every Turn, Companies score 1 Victory Point for each Objective Marker on the enemy table half.",
            ],
            [
                'slug' => 'target-sighted',
                'name' => 'Target Sighted',
                'flavor' => 'Wars are won not by having the biggest guns, but by identifying the correct target to aim at.',
                'additional_setup' => 'During Scouting, each Company places a variable Objective Marker within 3" of their Deployment Zone, and one on the centerline at least 8" away from any other Objective Markers.',
                'special_rules' => "Unengaged Fireteams gain the following Action:\n\nHeave Ho!: Discard a Tactics Token to move target Objective Marker within 2\" up to 5\".",
                'victory_conditions' => "{$commanders}\n\nOnce per Turn, a Company may score 1 VP after one of your Fireteams Kills an enemy Fireteam within 6\" of a friendly Objective Marker.\n\nAt the end of the Turn, a Company scores 1 VP if a friendly Objective Marker is on the enemy table half, and an additional 2 VP if it is in their Deployment Zone.\n\nDuring Upkeep, each Company may discard a Tactics Token to place an Objective Marker within 3\" of their Deployment Zone.",
            ],
            [
                'slug' => 'deliver-payload',
                'name' => 'Deliver Payload',
                'flavor' => 'Enemies have amassed across the battlefield. Your Company has been ordered to prime an explosive, escort it into position, and hit as many enemies as possible. Bonus points for hitting an enemy Commander!',
                'additional_setup' => null,
                'special_rules' => "All Objective Markers gain the Difficult Terrain Trait.\n\nUnengaged Fireteams gain the following Actions:\n\nPrime Explosive: Once per Activation. This Action can only be taken if this Fireteam is on the centerline. Discard a Tactics Token to place an Objective Marker within 6\".\n\nEscort: Discard a Tactics Token to move target friendly Objective Marker within 2\" up to 6\". If this Marker is moved into base contact with at least one enemy Fireteam, score 1 VP. If it moved into base contact with an enemy Commander, score an additional VP.",
                'victory_conditions' => "{$commanders}\n\nAt the end of the Turn, if at least one enemy Fireteam is within 3\" of a friendly Objective Marker, remove that Marker and score 1 VP. If three or more enemy Fireteams are within range of the removed Marker, score an additional 2 VP.",
            ],
        ];

        foreach ($rows as $i => $row) {
            Operation::updateOrCreate(
                ['slug' => $row['slug']],
                [...$row, 'source' => 'Fields of Glory v3.0', 'sort_order' => $i],
            );
        }
    }
}
