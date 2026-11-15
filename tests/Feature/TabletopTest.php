<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\Character;
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

it('searches character cards for the sandbox with base, Sz and Sp', function () {
    Feature::for(null)->activate('experiment-tabletop');
    Character::factory()->create(['name' => 'Pandora', 'title' => 'Gifted Lady', 'base' => 40, 'size' => 2, 'speed' => 6]);
    Character::factory()->create(['name' => 'Lady Justice', 'title' => null, 'base' => 50, 'size' => 3, 'speed' => 5]);
    Character::factory()->create(['name' => 'Silurid', 'title' => null, 'base' => 30]);
    Character::factory()->create(['name' => 'Hidden Lady', 'title' => null, 'is_hidden' => true]);

    $this->getJson(route('tools.tabletop.characters', ['q' => 'lady']))
        ->assertOk()
        ->assertJsonCount(2, 'characters')
        ->assertJsonPath('characters.0.name', 'Lady Justice')
        ->assertJsonPath('characters.0.base_mm', 50)
        ->assertJsonPath('characters.0.sz', 3)
        ->assertJsonPath('characters.0.sp', 5)
        ->assertJsonPath('characters.1.name', 'Pandora, Gifted Lady');
    $this->getJson(route('tools.tabletop.characters', ['q' => 'gifted pandora']))->assertJsonCount(1, 'characters');
    $this->getJson(route('tools.tabletop.characters', ['q' => 'nobody']))->assertJsonCount(0, 'characters');
});

it('refuses short or missing search text', function () {
    Feature::for(null)->activate('experiment-tabletop');

    $this->getJson(route('tools.tabletop.characters', ['q' => 'a']))->assertUnprocessable();
    $this->getJson(route('tools.tabletop.characters'))->assertUnprocessable();
});

it('hides the character search until the flag is on, or for beta testers', function () {
    $this->getJson(route('tools.tabletop.characters', ['q' => 'lady']))->assertNotFound();

    $tester = User::factory()->create();
    $tester->givePermissionTo(PermissionEnum::UseTabletopBeta->value);
    $this->actingAs($tester)->getJson(route('tools.tabletop.characters', ['q' => 'lady']))->assertOk();
});
