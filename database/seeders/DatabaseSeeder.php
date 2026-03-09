<?php

namespace Database\Seeders;

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
        ]);

        $this->command->info('Seeding from production API...');
        Artisan::call('app:seed-from-prod', ['--skip-images' => false], $this->command->getOutput());
    }
}
