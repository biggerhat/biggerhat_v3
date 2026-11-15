<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use Spatie\Permission\Models\Permission;

it('hides the tabletop until its feature flag is on', function () {
    $this->get(route('tools.tabletop'))->assertNotFound();
});

it('renders the tabletop page when the flag is on', function () {
    Feature::for(null)->activate('experiment-tabletop');

    $this->get(route('tools.tabletop'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Tools/Tabletop'));
});

it('lets a beta tester open the tabletop while the flag is off, and nobody else', function () {
    $tester = User::factory()->create();
    $tester->givePermissionTo(PermissionEnum::UseTabletopBeta->value);

    $this->actingAs(User::factory()->create())->get(route('tools.tabletop'))->assertNotFound();
    $this->actingAs($tester)->get(route('tools.tabletop'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Tools/Tabletop'));
});

it('gives super admins beta access without the flag', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::SuperAdmin->value);

    $this->actingAs($admin)->get(route('tools.tabletop'))->assertOk();
});

it('has the beta permission after migrating', function () {
    expect(Permission::where('name', 'use_tabletop_beta')->exists())->toBeTrue();
});
