<?php

namespace Database\Seeders\TOS;

use App\Models\TOS\Deployment;
use Illuminate\Database\Seeder;

/**
 * The four Deployments in the Fields of Glory v3.0 packet, keyed by the suit the Defender flips
 * (Ram → Confrontation, Crow → Corners, Tome → Sideways, Mask → Killing Field; a Joker counts as Ram).
 * Descriptions are summaries of the packet's diagrams (p. 8); admins can attach the diagram image.
 * Idempotent: re-running updates rows by slug.
 */
class DeploymentSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['slug' => 'confrontation', 'name' => 'Confrontation', 'suit' => 'R', 'description' => 'Two opposed Deployment Zones, each 12" deep. A flipped Joker is treated as this Deployment, but Commanders must be deployed before any other units.'],
            ['slug' => 'corners', 'name' => 'Corners', 'suit' => 'C', 'description' => 'Deployment Zones in opposite corners of the table.'],
            ['slug' => 'sideways', 'name' => 'Sideways', 'suit' => 'T', 'description' => 'Deployment Zones halfway along the board edges between the opponents.'],
            ['slug' => 'killing-field', 'name' => 'Killing Field', 'suit' => 'M', 'description' => 'Two Deployment Zones, each 12" wide, on opposite sides of the table.'],
        ];

        foreach ($rows as $i => $row) {
            Deployment::updateOrCreate(['slug' => $row['slug']], [...$row, 'sort_order' => $i]);
        }
    }
}
