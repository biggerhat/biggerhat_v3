<?php

use App\Enums\FactionEnum;
use App\Enums\SculptVersionEnum;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Package;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Create permissions and super_admin role
    foreach (['view_package', 'edit_package', 'delete_package'] as $perm) {
        Permission::findOrCreate($perm);
    }

    $role = Role::findOrCreate('super_admin');
    $role->syncPermissions(Permission::all());

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

it('requires authentication for admin package routes', function () {
    $this->get(route('admin.packages.index'))->assertRedirect('/login');
    $this->get(route('admin.packages.create'))->assertRedirect('/login');
    $this->post(route('admin.packages.store'))->assertRedirect('/login');
});

it('requires permission to view packages index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.packages.index'))
        ->assertForbidden();
});

it('displays packages index for admin', function () {
    Package::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.packages.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Packages/Index')
            ->has('packages', 3)
        );
});

it('displays create package form for admin', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.packages.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Packages/PackageForm')
            ->has('factions')
            ->has('sculpt_versions')
            ->has('characters')
            ->has('miniatures')
            ->has('keywords')
        );
});

it('stores a new package', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.packages.store'), [
            'name' => 'Lady Justice Core Box',
            'sku' => 'WYR23-101',
            'factions' => [FactionEnum::Guild->value],
            'sculpt_version' => SculptVersionEnum::FourthEdition->value,
            'is_preassembled' => false,
        ])
        ->assertRedirect(route('admin.packages.index'));

    $package = Package::where('name', 'Lady Justice Core Box')->first();

    expect($package)->not->toBeNull()
        ->and($package->slug)->toBe('lady-justice-core-box')
        ->and($package->sku)->toBe('WYR23-101')
        ->and($package->factions)->toBe([FactionEnum::Guild->value]);
});

it('stores a package with relationships', function () {
    $character = Character::factory()->create();
    $keyword = Keyword::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.packages.store'), [
            'name' => 'Test Package With Relations',
            'characters' => [$character->display_name],
            'keywords' => [$keyword->name],
        ])
        ->assertRedirect(route('admin.packages.index'));

    $package = Package::where('name', 'Test Package With Relations')->first();

    expect($package->characters)->toHaveCount(1)
        ->and($package->keywords)->toHaveCount(1);
});

it('validates name is required when storing', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.packages.store'), [
            'name' => '',
        ])
        ->assertSessionHasErrors('name');
});

it('displays edit form with package data', function () {
    $package = Package::factory()->create();
    $character = Character::factory()->create();
    $package->characters()->attach($character);

    $this->actingAs($this->admin)
        ->get(route('admin.packages.edit', $package))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Packages/PackageForm')
            ->has('package', fn ($pkg) => $pkg
                ->where('id', $package->id)
                ->where('name', $package->name)
                ->has('characters', 1)
                ->etc()
            )
            ->has('factions')
            ->has('sculpt_versions')
        );
});

it('updates an existing package', function () {
    $package = Package::factory()->create(['name' => 'Old Name']);

    $this->actingAs($this->admin)
        ->post(route('admin.packages.update', $package), [
            'name' => 'New Name',
            'sku' => 'WYR99-999',
        ])
        ->assertRedirect(route('admin.packages.index'));

    $package->refresh();

    expect($package->name)->toBe('New Name')
        ->and($package->slug)->toBe('new-name')
        ->and($package->sku)->toBe('WYR99-999');
});

it('syncs relationships on update', function () {
    $package = Package::factory()->create();
    $oldCharacter = Character::factory()->create();
    $newCharacter = Character::factory()->create();
    $package->characters()->attach($oldCharacter);

    $this->actingAs($this->admin)
        ->post(route('admin.packages.update', $package), [
            'name' => $package->name,
            'characters' => [$newCharacter->display_name],
        ])
        ->assertRedirect(route('admin.packages.index'));

    $package->refresh();

    expect($package->characters)->toHaveCount(1)
        ->and($package->characters->first()->id)->toBe($newCharacter->id);
});

it('deletes a package', function () {
    $package = Package::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.packages.delete', $package))
        ->assertRedirect(route('admin.packages.index'));

    expect(Package::find($package->id))->toBeNull();
});

it('requires delete permission to delete a package', function () {
    // Create user with only view permission
    $viewOnlyUser = User::factory()->create();
    $viewRole = Role::findOrCreate('viewer');
    Permission::findOrCreate('view_package');
    $viewRole->syncPermissions(['view_package']);
    $viewOnlyUser->assignRole('viewer');

    $package = Package::factory()->create();

    $this->actingAs($viewOnlyUser)
        ->post(route('admin.packages.delete', $package))
        ->assertForbidden();

    expect(Package::find($package->id))->not->toBeNull();
});
