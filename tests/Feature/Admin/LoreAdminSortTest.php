<?php

use App\Models\Lore;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'view_lore', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all());
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

it('defaults the lore admin index to name ascending', function () {
    Lore::factory()->create(['name' => 'Charlie']);
    Lore::factory()->create(['name' => 'Alpha']);
    Lore::factory()->create(['name' => 'Bravo']);

    $this->actingAs($this->admin)->get(route('admin.lores.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Admin/Lore/Index')
            ->where('sort', 'name')->where('direction', 'asc')
            ->where('lores.0.name', 'Alpha')
            ->where('lores.2.name', 'Charlie'));
});

it('sorts the lore admin index by newest and oldest (creation order)', function () {
    Lore::factory()->create(['name' => 'Charlie']); // id 1 — oldest
    Lore::factory()->create(['name' => 'Alpha']);   // id 2
    Lore::factory()->create(['name' => 'Bravo']);   // id 3 — newest

    $this->actingAs($this->admin)->get(route('admin.lores.index', ['sort' => 'created_at', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('direction', 'desc')
            ->where('lores.0.name', 'Bravo')
            ->where('lores.2.name', 'Charlie'));

    $this->actingAs($this->admin)->get(route('admin.lores.index', ['sort' => 'created_at', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('lores.0.name', 'Charlie')
            ->where('lores.2.name', 'Bravo'));
});
