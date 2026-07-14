<?php

use App\Enums\PermissionEnum;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Upgrade;
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

it('edit exposes each action/ability\'s Tier-4 borrow exclusion via upgradeable_rows', function () {
    $upgrade = Upgrade::factory()->create(['domain' => \App\Enums\UpgradeDomainTypeEnum::Crew]);
    $action = Action::factory()->create();
    $ability = Ability::factory()->create();
    $upgrade->actions()->attach($action->id, ['is_signature_action' => false, 'borrow_exclusion' => 'power_bar']);
    $upgrade->abilities()->attach($ability->id, ['borrow_exclusion' => 'card_swap']);

    $this->actingAs($this->admin)
        ->get(route('admin.crews.edit', $upgrade->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('upgradeable_rows', function ($rows) use ($action, $ability) {
            $rows = collect($rows);
            $actionRow = $rows->firstWhere(fn ($r) => $r['type'] === 'action' && $r['id'] === $action->id);
            $abilityRow = $rows->firstWhere(fn ($r) => $r['type'] === 'ability' && $r['id'] === $ability->id);

            return $actionRow['borrow_exclusion'] === 'power_bar' && $abilityRow['borrow_exclusion'] === 'card_swap';
        }));
});

it('store persists a Tier-4 borrow exclusion reason on an action, ability, and trigger', function () {
    $action = Action::factory()->create();
    $ability = Ability::factory()->create();
    $trigger = \App\Models\Trigger::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.crews.store'), [
            'name' => 'Excluded Effects',
            'game_mode_type' => 'standard',
            'front_image' => null,
            'back_image' => null,
            'upgradeable_rows' => [
                ['type' => 'action', 'id' => $action->id, 'is_signature' => false, 'borrow_exclusion' => 'power_bar'],
                ['type' => 'ability', 'id' => $ability->id, 'borrow_exclusion' => 'card_swap'],
                ['type' => 'trigger', 'id' => $trigger->id, 'borrow_exclusion' => 'other'],
            ],
        ])
        ->assertRedirect();

    $upgrade = Upgrade::firstWhere('name', 'Excluded Effects');
    expect($upgrade->actions->first()->pivot->borrow_exclusion)->toBe('power_bar');
    expect($upgrade->abilities->first()->pivot->borrow_exclusion)->toBe('card_swap');
    expect($upgrade->triggers->first()->pivot->borrow_exclusion)->toBe('other');
});

it('store rejects an invalid borrow_exclusion value', function () {
    $action = Action::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.crews.store'), [
            'name' => 'Bad Exclusion',
            'game_mode_type' => 'standard',
            'upgradeable_rows' => [
                ['type' => 'action', 'id' => $action->id, 'is_signature' => false, 'borrow_exclusion' => 'not_a_real_reason'],
            ],
        ])
        ->assertSessionHasErrors('upgradeable_rows.0.borrow_exclusion');
});

it('update clears a borrow_exclusion back to eligible', function () {
    $upgrade = Upgrade::factory()->create(['domain' => \App\Enums\UpgradeDomainTypeEnum::Crew, 'front_image' => null, 'back_image' => null]);
    $action = Action::factory()->create();
    $upgrade->actions()->attach($action->id, ['is_signature_action' => false, 'borrow_exclusion' => 'power_bar']);

    $this->actingAs($this->admin)
        ->post(route('admin.crews.update', $upgrade->slug), [
            'name' => $upgrade->name,
            'game_mode_type' => 'standard',
            'front_image' => null,
            'back_image' => null,
            'upgradeable_rows' => [
                ['type' => 'action', 'id' => $action->id, 'is_signature' => false, 'borrow_exclusion' => null],
            ],
        ])
        ->assertRedirect();

    expect($upgrade->fresh()->actions->first()->pivot->borrow_exclusion)->toBeNull();
});

it('store denies users without edit_crew', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewCrew->value);

    $this->actingAs($viewer)
        ->post(route('admin.crews.store'), ['name' => 'Nope', 'game_mode_type' => 'standard'])
        ->assertForbidden();
});
