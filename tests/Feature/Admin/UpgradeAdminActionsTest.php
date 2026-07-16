<?php

use App\Enums\GameModeTypeEnum;
use App\Enums\PermissionEnum;
use App\Models\Action;
use App\Models\Upgrade;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Mirrors CharacterAdminActionsTest — the Character-domain Upgrade admin form
 * shares the exact same two bugs (composite-string action ids, and a
 * duplicate pivot row when an action is marked signature). See that file's
 * docblock for the full explanation.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole(Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all()));
});

it('exposes the action id in the admin actions options label', function () {
    $action = Action::factory()->create(['name' => 'Cast Fireball']);

    $response = $this->actingAs($this->admin)->get(route('admin.upgrades.create'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('actions', fn ($actions) => collect($actions)->contains(
                fn ($a) => $a['id'] === $action->id && str_contains($a['name'], "(#{$action->id})")
            ))
        );
});

it('links actions and signature actions by plain numeric id with no duplicate pivot row', function () {
    $a1 = Action::factory()->create();
    $a2 = Action::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.upgrades.store'), [
            'name' => 'Test Upgrade',
            'game_mode_type' => GameModeTypeEnum::Standard->value,
            'front_image' => null,
            'back_image' => null,
            'actions' => [$a1->id, $a2->id],
            'signature_actions' => [$a2->id],
        ])
        ->assertRedirect(route('admin.upgrades.index'));

    $upgrade = Upgrade::where('name', 'Test Upgrade')->first();
    expect($upgrade)->not->toBeNull();
    // a2 is in both lists — assert exactly 2 rows (no duplicate pivot row).
    expect($upgrade->actions()->count())->toBe(2);
    expect($upgrade->actions()->pluck('actions.id')->all())->toEqualCanonicalizing([$a1->id, $a2->id]);
    expect($upgrade->actions()->wherePivot('is_signature_action', true)->pluck('actions.id')->all())->toEqual([$a2->id]);
});
