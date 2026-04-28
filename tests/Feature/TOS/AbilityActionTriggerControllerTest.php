<?php

use App\Models\TOS\Ability;
use App\Models\TOS\Action;
use App\Models\TOS\SpecialUnitRule;
use App\Models\TOS\Trigger;

it('renders the abilities index', function () {
    Ability::factory()->general()->count(3)->create();

    $this->get(route('tos.abilities.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/Abilities/Index')->has('abilities.data', 3));
});

it('renders the actions index', function () {
    Action::factory()->magic()->count(2)->create();
    Action::factory()->melee()->create();

    $this->get(route('tos.actions.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/Actions/Index')
            ->has('actions.data', 3)
        );
});

it('renders the triggers index, eager-loading attached actions', function () {
    $action = Action::factory()->melee()->create(['name' => 'Slash']);
    Trigger::factory()->forActions($action)->create(['name' => 'Critical', 'suits' => 'R']);

    $this->get(route('tos.triggers.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/Triggers/Index')
            ->has('triggers.data', 1)
            ->where('triggers.data.0.actions.0.name', 'Slash')
        );
});

it('renders the special rules index', function () {
    SpecialUnitRule::factory()->count(4)->create();

    $this->get(route('tos.special_rules.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/SpecialRules/Index')->has('rules.data', 4));
});

it('Trigger attaches to multiple Actions via the pivot and survives action delete', function () {
    $action = Action::factory()->create();
    $t1 = Trigger::factory()->forActions($action)->create();
    $t2 = Trigger::factory()->forActions($action)->create();

    expect($action->fresh()->triggers->pluck('id'))->toContain($t1->id, $t2->id);

    // Deleting an Action only detaches the pivot — shared triggers survive
    // for their other actions.
    $action->delete();

    expect(Trigger::find($t1->id))->not->toBeNull()
        ->and(Trigger::find($t2->id))->not->toBeNull()
        ->and(\DB::table('tos_action_trigger')->where('action_id', $action->id)->count())->toBe(0);
});

it('Trigger can be shared across multiple Actions', function () {
    $slash = Action::factory()->melee()->create(['name' => 'Slash']);
    $strike = Action::factory()->melee()->create(['name' => 'Strike']);
    $critical = Trigger::factory()->forActions($slash, $strike)->create(['name' => 'Critical']);

    expect($slash->fresh()->triggers->pluck('id'))->toContain($critical->id)
        ->and($strike->fresh()->triggers->pluck('id'))->toContain($critical->id)
        ->and($critical->fresh()->actions->pluck('id'))->toContain($slash->id, $strike->id);
});
