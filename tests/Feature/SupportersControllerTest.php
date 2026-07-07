<?php

use App\Enums\RoleEnum;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => RoleEnum::Supporter->value, 'guard_name' => 'web']);
});

it('lists supporters who opted in', function () {
    $optedIn = User::factory()->create(['show_on_supporters_page' => true, 'supporter_since' => '2026-03-04']);
    $optedIn->assignRole(RoleEnum::Supporter->value);

    $this->get(route('supporters.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Supporters/Index')
            ->has('supporters', 1)
            ->where('supporters.0.id', $optedIn->id)
        );
});

it('excludes a supporter who has not opted in', function () {
    $notOptedIn = User::factory()->create(['show_on_supporters_page' => false]);
    $notOptedIn->assignRole(RoleEnum::Supporter->value);

    $this->get(route('supporters.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('supporters', 0));
});

it('excludes a non-supporter who somehow has the opt-in flag set', function () {
    User::factory()->create(['show_on_supporters_page' => true]);

    $this->get(route('supporters.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('supporters', 0));
});
