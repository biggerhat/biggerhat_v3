<?php

use App\Enums\PermissionEnum;
use App\Models\Action;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Data entry asked for the Action id to be visible alongside the name on
 * every admin dropdown/multiselect that lists Actions, since Action.name is
 * not unique (the same name can belong to different characters). Character
 * and Character-Upgrade forms get their own dedicated test files because
 * they also required a structural fix (see CharacterAdminActionsTest); the
 * remaining forms only needed their label reformatted, so a single prop
 * assertion per route is enough to guard the format.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole(Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all()));
});

it('includes the action id in the label on the Crew Upgrade admin form', function () {
    $action = Action::factory()->create(['name' => 'Cast Fireball']);

    $this->actingAs($this->admin)
        ->get(route('admin.crews.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('actions', fn ($actions) => collect($actions)->contains(
                fn ($a) => $a['id'] === $action->id && str_contains($a['name'], "(#{$action->id})")
            ))
        );
});

it('includes the action id in the label on the Campaign Crew Card admin form', function () {
    $action = Action::factory()->create(['name' => 'Cast Fireball']);

    $this->actingAs($this->admin)
        ->get(route('admin.campaign.crew-cards.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('all_actions', fn ($actions) => collect($actions)->contains(
                fn ($a) => $a['id'] === $action->id && str_contains($a['name'], "(#{$action->id})")
            ))
        );
});

it('includes the action id in the label on the Campaign Totem Template admin form', function () {
    $action = Action::factory()->create(['name' => 'Cast Fireball']);

    $this->actingAs($this->admin)
        ->get(route('admin.campaign.totem-templates.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('all_actions', fn ($actions) => collect($actions)->contains(
                fn ($a) => $a['id'] === $action->id && str_contains($a['name'], "(#{$action->id})")
            ))
        );
});

it('includes the action id in the label on the Campaign Advancement Action admin form', function () {
    $action = Action::factory()->create(['name' => 'Cast Fireball']);

    $this->actingAs($this->admin)
        ->get(route('admin.campaign.advancement-action.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('actions', fn ($actions) => collect($actions)->contains(
                fn ($a) => $a['value'] === $action->id && str_contains($a['name'], "(#{$action->id})")
            ))
        );
});
