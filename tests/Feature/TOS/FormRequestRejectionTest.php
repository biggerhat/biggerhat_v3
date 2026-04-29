<?php

use App\Models\TOS\Ability;
use App\Models\TOS\Action;
use App\Models\TOS\Allegiance;
use App\Models\TOS\AllegianceCard;
use App\Models\TOS\Asset;
use App\Models\TOS\SpecialUnitRule;
use App\Models\TOS\Stratagem;
use App\Models\TOS\Trigger;
use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * One rejection scenario per FormRequest class — every TOS Update*Request
 * plus StoreSculptRequest, none of which were covered by the per-controller
 * test suites. The Plan-spec demand of ≥1 rejection-per-FormRequest lives
 * here so future schema/rule changes break a single, predictable file.
 */
beforeEach(function () {
    Permission::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    foreach (\App\Enums\PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'super_admin']);
    $role->syncPermissions(Permission::all());
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

it('StoreSculptRequest rejects missing unit_id', function () {
    $this->actingAs($this->admin)->postJson(route('admin.tos.sculpts.store'), [
        'name' => 'Orphan sculpt',
    ])->assertStatus(422)->assertJsonValidationErrors(['unit_id']);
});

it('UpdateAbilityRequest rejects missing name', function () {
    $a = Ability::factory()->general()->create();

    $this->actingAs($this->admin)->postJson(route('admin.tos.abilities.update', $a->slug), [
        'is_general' => true,
    ])->assertStatus(422)->assertJsonValidationErrors(['name']);
});

it('UpdateActionRequest rejects missing types', function () {
    $a = Action::factory()->create();

    $this->actingAs($this->admin)->postJson(route('admin.tos.actions.update', $a->slug), [
        'name' => 'Renamed',
    ])->assertStatus(422)->assertJsonValidationErrors(['types']);
});

it('UpdateAllegianceRequest rejects an invalid type enum', function () {
    $a = Allegiance::factory()->create();

    $this->actingAs($this->admin)->postJson(route('admin.tos.allegiances.update', $a->slug), [
        'name' => 'Renamed',
        'type' => 'not-a-real-type',
        'is_syndicate' => false,
    ])->assertStatus(422)->assertJsonValidationErrors(['type']);
});

it('UpdateAllegianceCardRequest rejects missing allegiance_id', function () {
    $a = Allegiance::factory()->earth()->create();
    $card = AllegianceCard::factory()->forAllegiance($a)->create();

    $this->actingAs($this->admin)->postJson(route('admin.tos.allegiance_cards.update', $card->slug), [
        'name' => 'Renamed',
        'type' => 'earth',
    ])->assertStatus(422)->assertJsonValidationErrors(['allegiance_id']);
});

it('UpdateAssetRequest rejects an invalid limit_type', function () {
    $asset = Asset::factory()->create();

    $this->actingAs($this->admin)->postJson(route('admin.tos.assets.update', $asset->slug), [
        'name' => 'Renamed',
        'scrip_cost' => 1,
        'limits' => [
            ['limit_type' => 'made-up-limit'],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors(['limits.0.limit_type']);
});

it('UpdateSculptRequest rejects a non-existent unit_id', function () {
    $unit = Unit::factory()->withSides()->create();
    $sculpt = UnitSculpt::factory()->forUnit($unit)->create();

    $this->actingAs($this->admin)->postJson(route('admin.tos.sculpts.update', $sculpt->slug), [
        'unit_id' => 999_999,
        'name' => $sculpt->name,
    ])->assertStatus(422)->assertJsonValidationErrors(['unit_id']);
});

it('UpdateSpecialUnitRuleRequest rejects missing name', function () {
    $rule = SpecialUnitRule::factory()->create();

    $this->actingAs($this->admin)->postJson(route('admin.tos.special_rules.update', $rule->slug), [
        'description' => 'Updated description',
    ])->assertStatus(422)->assertJsonValidationErrors(['name']);
});

it('UpdateStratagemRequest rejects missing tactical_cost', function () {
    $s = Stratagem::factory()->create();

    $this->actingAs($this->admin)->postJson(route('admin.tos.stratagems.update', $s->slug), [
        'name' => 'Renamed',
    ])->assertStatus(422)->assertJsonValidationErrors(['tactical_cost']);
});

it('UpdateTriggerRequest rejects setting both suits and margin_cost (prohibits)', function () {
    $action = Action::factory()->create();
    $t = Trigger::factory()->create();
    $t->actions()->attach($action->id, ['sort_order' => 0]);

    $this->actingAs($this->admin)->postJson(route('admin.tos.triggers.update', $t->slug), [
        'action_ids' => [$action->id],
        'name' => 'Conflict',
        'suits' => 'R',
        'margin_cost' => 4,
    ])->assertStatus(422)->assertJsonValidationErrors(['suits', 'margin_cost']);
});

it('UpdateUnitRequest rejects when neither restriction nor allegiance_ids is provided', function () {
    $a = Allegiance::factory()->earth()->create();
    $unit = Unit::factory()->withSides()->create();
    $unit->allegiances()->sync([$a->id]);

    $payload = [
        'name' => 'Renamed',
        'scrip' => 5,
        'sides' => [
            ['side' => 'standard', 'speed' => 5, 'defense' => 4, 'willpower' => 4, 'armor' => 0],
            ['side' => 'glory', 'speed' => 5, 'defense' => 5, 'willpower' => 5, 'armor' => 0],
        ],
    ];

    $this->actingAs($this->admin)->postJson(route('admin.tos.units.update', $unit->slug), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['allegiance_ids', 'restriction']);
});
