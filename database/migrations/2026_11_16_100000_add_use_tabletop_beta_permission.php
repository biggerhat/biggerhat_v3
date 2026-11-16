<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Beta access to the virtual tabletop while its feature flag is off. Created here (as well as by PermissionSeeder) so a deploy
 * has it without anyone re-running the seeders. Super admins get it like every other permission.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable(config('permission.table_names.permissions', 'permissions'))) {
            return;
        }

        $permission = Permission::firstOrCreate(['name' => 'use_tabletop_beta', 'guard_name' => 'web']);
        Role::where('name', 'super_admin')->where('guard_name', 'web')->first()?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable(config('permission.table_names.permissions', 'permissions'))) {
            return;
        }

        Permission::where('name', 'use_tabletop_beta')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
