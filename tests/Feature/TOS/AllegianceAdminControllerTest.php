<?php

use App\Enums\PermissionEnum;
use App\Enums\TOS\AllegianceTypeEnum;
use App\Models\TOS\Allegiance;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach ([
        PermissionEnum::ViewTosAllegiance,
        PermissionEnum::EditTosAllegiance,
        PermissionEnum::DeleteTosAllegiance,
    ] as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }

    $this->admin = User::factory()->create();
    $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
    $superAdmin->syncPermissions(Permission::all());
    $this->admin->assignRole('super_admin');

    $this->viewer = User::factory()->create();
    $this->viewer->givePermissionTo(PermissionEnum::ViewTosAllegiance->value);

    $this->stranger = User::factory()->create();
});

it('admin index renders for users with view permission', function () {
    Allegiance::factory()->count(2)->create();

    $this->actingAs($this->viewer)
        ->get(route('admin.tos.allegiances.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('Admin/TOS/Allegiances/Index')
            ->has('allegiances', 2)
        );
});

it('admin index denies users without view_tos_allegiance', function () {
    $this->actingAs($this->stranger)
        ->get(route('admin.tos.allegiances.index'))
        ->assertForbidden();
});

it('store creates an allegiance with super_admin', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.tos.allegiances.store'), [
            'name' => 'New Allegiance',
            'short_name' => 'New',
            'type' => AllegianceTypeEnum::Earth->value,
            'is_syndicate' => false,
            'description' => null,
            'logo_path' => null,
            'color_slug' => null,
            'sort_order' => 0,
        ])
        ->assertRedirect(route('admin.tos.allegiances.index'));

    expect(Allegiance::where('name', 'New Allegiance')->exists())->toBeTrue();
});

it('store denies users without edit_tos_allegiance', function () {
    $this->actingAs($this->viewer)
        ->post(route('admin.tos.allegiances.store'), [
            'name' => 'X',
            'type' => 'earth',
            'is_syndicate' => false,
        ])
        ->assertForbidden();

    expect(Allegiance::where('name', 'X')->exists())->toBeFalse();
});

it('update modifies an allegiance', function () {
    $a = Allegiance::factory()->create(['name' => 'Old Name']);

    $this->actingAs($this->admin)
        ->post(route('admin.tos.allegiances.update', $a->slug), [
            'name' => 'New Name',
            'short_name' => 'NN',
            'type' => AllegianceTypeEnum::Malifaux->value,
            'is_syndicate' => true,
            'description' => 'Updated',
            'logo_path' => null,
            'color_slug' => null,
            'sort_order' => 5,
        ])
        ->assertRedirect(route('admin.tos.allegiances.index'));

    $a->refresh();
    expect($a->name)->toBe('New Name')
        ->and($a->is_syndicate)->toBeTrue()
        ->and($a->type)->toBe(AllegianceTypeEnum::Malifaux);
});

it('delete removes an allegiance', function () {
    $a = Allegiance::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.tos.allegiances.delete', $a->slug))
        ->assertRedirect(route('admin.tos.allegiances.index'));

    expect(Allegiance::find($a->id))->toBeNull();
});

it('delete denies users without delete_tos_allegiance', function () {
    $a = Allegiance::factory()->create();

    $this->actingAs($this->viewer)
        ->post(route('admin.tos.allegiances.delete', $a->slug))
        ->assertForbidden();

    expect(Allegiance::find($a->id))->not->toBeNull();
});

it('store rejects invalid type values', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.tos.allegiances.store'), [
            'name' => 'X',
            'type' => 'not-a-type',
            'is_syndicate' => false,
        ])
        ->assertStatus(422);
});

it('store rejects missing name', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.tos.allegiances.store'), [
            'type' => 'earth',
            'is_syndicate' => false,
        ])
        ->assertStatus(422);
});
