<?php

namespace Database\Seeders;

use App\Enums\PermissionEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Seed the permissions table
     */
    public function run(): void
    {
        foreach (PermissionEnum::cases() as $permission) {
            Permission::updateOrCreate(['name' => $permission]);
        }

        Permission::whereNotIn('name', PermissionEnum::cases())->delete();

        // Assign all permissions to super_admin
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->syncPermissions(Permission::all());

        // Tournament organizer: can create new tournaments. Per-tournament management
        // is gated by the tournament_organizers pivot (creator + invited organizers).
        // The manage_tournaments permission is reserved for super_admin (manage any).
        $tournamentOrganizer = Role::firstOrCreate(['name' => 'tournament_organizer']);
        $tournamentOrganizer->syncPermissions([
            PermissionEnum::CreateTournaments->value,
        ]);
    }
}
