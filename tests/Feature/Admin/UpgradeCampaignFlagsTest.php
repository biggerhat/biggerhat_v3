<?php

use App\Enums\PermissionEnum;
use App\Models\Upgrade;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Guards the admin Upgrade campaign checkboxes (e.g. Traitor). The columns must
 * be cast to boolean: otherwise they reach the edit form as ints (0/1), and
 * binding :checked="1" (a number) to the radix checkbox breaks its state, so a
 * saved flag looks unchecked on reload.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('serializes campaign boolean flags to the edit form as booleans', function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all()));

    $upgrade = Upgrade::factory()->campaignInjury()->create(['campaign_is_traitor' => true]);

    $value = $this->actingAs($admin)
        ->get(route('admin.upgrades.edit', $upgrade->slug))
        ->viewData('page')['props']['upgrade']['campaign_is_traitor'];

    expect($value)->toBeTrue()->toBeBool();
});
