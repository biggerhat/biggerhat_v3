<?php

use App\Models\TOS\Ability;
use App\Models\TOS\Action;
use App\Models\TOS\SpecialUnitRule;
use App\Models\TOS\Trigger;

it('renders the abilities index', function () {
    Ability::factory()->general()->count(3)->create();

    $this->get(route('tos.abilities.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/Abilities/Index')->has('abilities', 3));
});

it('renders the actions index with action types', function () {
    Action::factory()->magic()->count(2)->create();
    Action::factory()->melee()->create();

    $this->get(route('tos.actions.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/Actions/Index')
            ->has('actions', 3)
            ->has('action_types', 4)
        );
});

it('renders the triggers index, eager-loading the parent action', function () {
    $action = Action::factory()->melee()->create(['name' => 'Slash']);
    Trigger::factory()->for($action, 'action')->create(['name' => 'Critical', 'suits' => 'R']);

    $this->get(route('tos.triggers.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/Triggers/Index')
            ->has('triggers', 1)
            ->where('triggers.0.action.name', 'Slash')
        );
});

it('renders the special rules index', function () {
    SpecialUnitRule::factory()->count(4)->create();

    $this->get(route('tos.special_rules.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/SpecialRules/Index')->has('rules', 4));
});

it('Trigger belongs to its parent Action and cascades on delete', function () {
    $action = Action::factory()->create();
    $t1 = Trigger::factory()->for($action, 'action')->create();
    $t2 = Trigger::factory()->for($action, 'action')->create();

    expect($action->fresh()->triggers->pluck('id'))->toContain($t1->id, $t2->id);

    $action->delete();

    expect(Trigger::find($t1->id))->toBeNull()
        ->and(Trigger::find($t2->id))->toBeNull();
});
