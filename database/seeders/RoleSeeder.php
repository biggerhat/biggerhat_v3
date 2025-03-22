<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Seed the permissions table
     */
    public function run(): void
    {
        foreach (RoleEnum::cases() as $role) {
            Role::updateOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }

        Role::whereNotIn('name', RoleEnum::cases())->delete();
    }
}
