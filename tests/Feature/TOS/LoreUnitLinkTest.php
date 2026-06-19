<?php

use App\Enums\PermissionEnum;
use App\Models\Lore;
use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all());
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

it('links a Lore to TOS Units through the admin form (by slug)', function () {
    $unitA = Unit::factory()->create();
    $unitB = Unit::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.lores.store'), [
            'name' => 'The Silent Catalogue',
            'tos_units' => [$unitA->slug, $unitB->slug],
        ])
        ->assertRedirect();

    $lore = Lore::firstWhere('name', 'The Silent Catalogue');
    expect($lore)->not->toBeNull();
    expect($lore->tosUnits()->pluck('tos_units.id')->all())->toEqualCanonicalizing([$unitA->id, $unitB->id]);
});

it('re-syncs TOS Unit links on update', function () {
    $unitA = Unit::factory()->create();
    $unitB = Unit::factory()->create();
    $lore = Lore::factory()->create(['name' => 'Records']);
    $lore->tosUnits()->sync([$unitA->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.lores.update', $lore->id), [
            'name' => 'Records',
            'tos_units' => [$unitB->slug],
        ])
        ->assertRedirect();

    expect($lore->fresh()->tosUnits()->pluck('tos_units.id')->all())->toEqual([$unitB->id]);
});

it('exposes linked Lore on the public TOS Unit view', function () {
    $unit = Unit::factory()->create();
    $sculpt = UnitSculpt::factory()->create(['unit_id' => $unit->id]);
    $lore = Lore::factory()->create(['name' => 'Origins of the Kin']);
    $unit->lores()->attach($lore);

    $this->get(route('tos.units.view', $sculpt->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('TOS/Units/View')
            ->where('unit.lores.0.name', 'Origins of the Kin')
        );
});
