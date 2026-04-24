<?php

namespace Database\Seeders;

use Database\Seeders\TOS\AbilitySeeder as TosAbilitySeeder;
use Database\Seeders\TOS\AllegianceCardSeeder as TosAllegianceCardSeeder;
use Database\Seeders\TOS\AllegianceSeeder as TosAllegianceSeeder;
use Database\Seeders\TOS\AssetSeeder as TosAssetSeeder;
use Database\Seeders\TOS\EnvoySeeder as TosEnvoySeeder;
use Database\Seeders\TOS\SpecialUnitRuleSeeder as TosSpecialUnitRuleSeeder;
use Database\Seeders\TOS\StratagemSeeder as TosStratagemSeeder;
use Database\Seeders\TOS\UnitSeeder as TosUnitSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            TosAllegianceSeeder::class,
            TosSpecialUnitRuleSeeder::class,
            TosAbilitySeeder::class,
            TosUnitSeeder::class,
            TosAllegianceCardSeeder::class,
            TosEnvoySeeder::class,
            TosAssetSeeder::class,
            TosStratagemSeeder::class,
        ]);

        $this->command->info('Seeding from production API...');
        Artisan::call('app:seed-from-prod', ['--skip-images' => false], $this->command->getOutput());
    }
}
