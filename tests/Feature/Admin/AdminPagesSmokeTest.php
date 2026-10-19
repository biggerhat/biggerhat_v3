<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
 * Every parameter-less admin GET page must render for a super admin on an
 * empty database. Catches 500s on index/create pages (bad relations, missing
 * columns, undefined props) without needing per-page fixtures.
 */
it('renders every parameter-less admin page for a super admin', function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }
    foreach (RoleEnum::cases() as $role) {
        Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
    }
    Role::findByName(RoleEnum::SuperAdmin->value)->syncPermissions(Permission::all());
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::SuperAdmin->value);

    $failures = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $r) => str_starts_with((string) $r->getName(), 'admin.') && in_array('GET', $r->methods(), true) && $r->parameterNames() === [])
        ->reject(fn (LaravelRoute $r) => in_array($r->getName(), ['admin.search'], true))
        ->mapWithKeys(fn (LaravelRoute $r) => [$r->getName() => $this->actingAs($admin)->get(route($r->getName()))->getStatusCode()])
        ->reject(fn (int $status) => in_array($status, [200, 302], true))
        ->all();

    expect($failures)->toBe([]);
});
