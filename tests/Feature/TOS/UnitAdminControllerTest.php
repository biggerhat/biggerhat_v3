<?php

use App\Enums\PermissionEnum;
use App\Enums\TOS\SpecialUnitRuleEnum;
use App\Enums\TOS\UnitSideEnum;
use App\Models\TOS\Ability;
use App\Models\TOS\Action;
use App\Models\TOS\Allegiance;
use App\Models\TOS\SpecialUnitRule;
use App\Models\TOS\Unit;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach ([
        PermissionEnum::ViewTosUnit, PermissionEnum::EditTosUnit, PermissionEnum::DeleteTosUnit,
    ] as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }

    $this->admin = User::factory()->create();
    $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
    $superAdmin->syncPermissions(Permission::all());
    $this->admin->assignRole('super_admin');

    $this->stranger = User::factory()->create();

    $this->allegiance = Allegiance::factory()->create();
    $this->commanderRule = SpecialUnitRule::factory()->forCanonical(SpecialUnitRuleEnum::Commander)->create();
    $this->fireteamRule = SpecialUnitRule::factory()->forCanonical(SpecialUnitRuleEnum::Fireteam)->create();
});

it('admin index denies users without view_tos_unit', function () {
    $this->actingAs($this->stranger)->get(route('admin.tos.units.index'))->assertForbidden();
});

it('admin store creates unit + both sides + pivots in one shot', function () {
    $ability = Ability::factory()->general()->create();
    $action = Action::factory()->melee()->create();

    $this->actingAs($this->admin)->post(route('admin.tos.units.store'), [
        'name' => 'Test Commander',
        'title' => 'The Test',
        'scrip' => 8,
        'tactics' => '2',
        'description' => 'Seeded by feature test.',
        'allegiance_ids' => [$this->allegiance->id],
        'sides' => [
            ['side' => UnitSideEnum::Standard->value, 'speed' => 5, 'defense' => 5, 'willpower' => 6, 'armor' => 2, 'ability_ids' => [$ability->id], 'action_ids' => [$action->id]],
            ['side' => UnitSideEnum::Glory->value, 'speed' => 5, 'defense' => 6, 'willpower' => 7, 'armor' => 3, 'ability_ids' => [], 'action_ids' => []],
        ],
        'special_rules' => [
            ['special_unit_rule_id' => $this->commanderRule->id, 'parameters' => null],
        ],
    ])->assertRedirect(route('admin.tos.units.index'));

    $unit = Unit::with(['sides.abilities', 'sides.actions', 'allegiances', 'specialUnitRules'])->where('name', 'Test Commander')->first();

    expect($unit)->not->toBeNull()
        ->and($unit->sides()->count())->toBe(2)
        ->and($unit->allegiances->pluck('id'))->toContain($this->allegiance->id)
        ->and($unit->specialUnitRules->pluck('id'))->toContain($this->commanderRule->id)
        ->and($unit->standardSide()->abilities->pluck('id'))->toContain($ability->id)
        ->and($unit->standardSide()->actions->pluck('id'))->toContain($action->id)
        ->and($unit->glorySide()->abilities->count())->toBe(0);
});

it('admin store persists JSON parameters on the special-rule pivot', function () {
    $this->actingAs($this->admin)->post(route('admin.tos.units.store'), [
        'name' => 'Riflemen',
        'scrip' => 4,
        'allegiance_ids' => [$this->allegiance->id],
        'sides' => [
            ['side' => 'standard', 'speed' => 5, 'defense' => 4, 'willpower' => 4, 'armor' => 1],
            ['side' => 'glory', 'speed' => 5, 'defense' => 5, 'willpower' => 5, 'armor' => 2],
        ],
        'special_rules' => [
            ['special_unit_rule_id' => $this->fireteamRule->id, 'parameters' => ['base_mm' => 30, 'models_per_team' => 3, 'model_size_mm' => 30]],
        ],
    ])->assertRedirect();

    $unit = Unit::with('specialUnitRules')->where('name', 'Riflemen')->first();

    expect($unit->specialUnitRules->firstWhere('id', $this->fireteamRule->id)->pivot->parameters)
        ->toBe(['base_mm' => 30, 'models_per_team' => 3, 'model_size_mm' => 30]);
});

it('admin store validation rejects when sides count is not exactly 2', function () {
    $this->actingAs($this->admin)->postJson(route('admin.tos.units.store'), [
        'name' => 'Bad',
        'scrip' => 4,
        'allegiance_ids' => [$this->allegiance->id],
        'sides' => [
            ['side' => 'standard', 'speed' => 5, 'defense' => 4, 'willpower' => 4, 'armor' => 1],
        ],
    ])->assertStatus(422);
});

it('admin store validation requires at least one allegiance', function () {
    $this->actingAs($this->admin)->postJson(route('admin.tos.units.store'), [
        'name' => 'Bad',
        'scrip' => 4,
        'allegiance_ids' => [],
        'sides' => [
            ['side' => 'standard', 'speed' => 5, 'defense' => 4, 'willpower' => 4, 'armor' => 1],
            ['side' => 'glory', 'speed' => 5, 'defense' => 5, 'willpower' => 5, 'armor' => 2],
        ],
    ])->assertStatus(422);
});

it('admin update modifies only the targeted side without touching the other', function () {
    $unit = Unit::factory()->withSides()->commander()->create(['name' => 'Existing']);
    $unit->allegiances()->attach($this->allegiance->id);

    $originalGlory = $unit->fresh()->glorySide();

    $this->actingAs($this->admin)->post(route('admin.tos.units.update', $unit->slug), [
        'name' => $unit->name,
        'scrip' => $unit->scrip,
        'allegiance_ids' => [$this->allegiance->id],
        'sides' => [
            ['side' => 'standard', 'speed' => 9, 'defense' => 9, 'willpower' => 9, 'armor' => 9],
            ['side' => 'glory', 'speed' => $originalGlory->speed, 'defense' => $originalGlory->defense, 'willpower' => $originalGlory->willpower, 'armor' => $originalGlory->armor],
        ],
        'special_rules' => [['special_unit_rule_id' => $this->commanderRule->id, 'parameters' => null]],
    ])->assertRedirect();

    $unit->refresh();
    expect($unit->standardSide()->speed)->toBe(9)
        ->and($unit->glorySide()->speed)->toBe($originalGlory->speed);
});

it('admin store denies users without edit_tos_unit', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosUnit->value);

    $this->actingAs($viewer)->post(route('admin.tos.units.store'), [
        'name' => 'X', 'scrip' => 4, 'allegiance_ids' => [$this->allegiance->id], 'sides' => [
            ['side' => 'standard', 'speed' => 5, 'defense' => 4, 'willpower' => 4, 'armor' => 1],
            ['side' => 'glory', 'speed' => 5, 'defense' => 5, 'willpower' => 5, 'armor' => 2],
        ],
    ])->assertForbidden();

    expect(Unit::where('name', 'X')->exists())->toBeFalse();
});

it('admin delete cascades sides and pivots', function () {
    $unit = Unit::factory()->withSides()->commander()->create();
    $unit->allegiances()->attach($this->allegiance->id);
    $unitId = $unit->id;

    $this->actingAs($this->admin)
        ->post(route('admin.tos.units.delete', $unit->slug))
        ->assertRedirect(route('admin.tos.units.index'));

    expect(Unit::find($unitId))->toBeNull()
        ->and(\DB::table('tos_unit_sides')->where('unit_id', $unitId)->count())->toBe(0)
        ->and(\DB::table('tos_unit_special_rule')->where('unit_id', $unitId)->count())->toBe(0);
});
