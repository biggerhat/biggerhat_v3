<?php

namespace Database\Seeders\TOS;

use App\Enums\TOS\EnvoyRestrictionEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Envoy;
use Illuminate\Database\Seeder;

/**
 * Seeds Envoys that prove the Syndicate hireability rule end-to-end. The
 * Court of Two — a Malifaux Syndicate — yields an Envoy with restriction
 * `malifaux`, so it is hireable into Cult of the Burning Man and Gibbering
 * Hordes but NOT into Earth allegiances (King's Empire, Abyssinia).
 */
class EnvoySeeder extends Seeder
{
    public function run(): void
    {
        $courtOfTwo = Allegiance::firstWhere('slug', 'court_of_two');
        if (! $courtOfTwo) {
            $this->command?->warn('Court of Two syndicate missing — run AllegianceSeeder first.');

            return;
        }

        Envoy::updateOrCreate(
            ['slug' => 'court-of-two-envoy'],
            [
                'allegiance_id' => $courtOfTwo->id,
                'name' => 'Court of Two Envoy',
                'keyword' => 'Envoy',
                'restriction' => EnvoyRestrictionEnum::Malifaux,
                'body' => "Court of Two units may be hired into any Malifaux Allegiance. When purchased, treat their hiring cost as though they shared that Allegiance's symbol.",
            ],
        );
    }
}
