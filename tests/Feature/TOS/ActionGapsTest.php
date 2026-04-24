<?php

use App\Enums\TOS\ActionTypeEnum;
use App\Enums\TOS\UsageLimitEnum;
use App\Models\TOS\Action;
use App\Models\TOS\Unit;

it('withTypes attaches multiple action types via the pivot', function () {
    $action = Action::factory()->withTypes(ActionTypeEnum::Magic, ActionTypeEnum::Morale)->create();

    $types = $action->fresh()->typeLinks->pluck('type');

    expect($types)->toContain(ActionTypeEnum::Magic, ActionTypeEnum::Morale)
        ->and($types)->toHaveCount(2);
});

it('syncTypes replaces the existing pivot rows', function () {
    $action = Action::factory()->melee()->create();
    expect($action->fresh()->typeLinks->pluck('type')->first())->toBe(ActionTypeEnum::Melee);

    $action->syncTypes([ActionTypeEnum::Magic, ActionTypeEnum::Missile]);
    $action->refresh();

    expect($action->typeLinks->pluck('type'))->toHaveCount(2)
        ->and($action->typeLinks->pluck('type'))->toContain(ActionTypeEnum::Magic, ActionTypeEnum::Missile);
});

it('types accessor returns a Collection of ActionTypeEnum', function () {
    $action = Action::factory()->withTypes(ActionTypeEnum::Melee, ActionTypeEnum::Morale)->create();

    $types = $action->fresh()->types;

    expect($types)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($types->first())->toBeInstanceOf(ActionTypeEnum::class)
        ->and($types)->toHaveCount(2);
});

it('strength column stores integer values', function () {
    $action = Action::factory()->strength(3)->create();

    expect($action->fresh()->strength)->toBe(3);
});

it('av_suits column stores suited AV text', function () {
    $action = Action::factory()->avSuits('Rt')->create();

    expect($action->fresh()->av_suits)->toBe('Rt');
});

it('damage-type boolean columns round-trip', function () {
    $action = Action::factory()->piercing()->accurate()->area()->create();

    expect($action->fresh()->is_piercing)->toBeTrue()
        ->and($action->fresh()->is_accurate)->toBeTrue()
        ->and($action->fresh()->is_area)->toBeTrue();
});

it('usage_limit casts to UsageLimitEnum', function () {
    $action = Action::factory()->oncePer(UsageLimitEnum::OncePerTurn)->create();

    expect($action->fresh()->usage_limit)->toBe(UsageLimitEnum::OncePerTurn);
});

it('Action delete cascades typeLinks', function () {
    $action = Action::factory()->withTypes(ActionTypeEnum::Magic)->create();
    $actionId = $action->id;

    $action->delete();

    expect(\DB::table('tos_action_types')->where('action_id', $actionId)->count())->toBe(0);
});

it('deleting an Action cascades tos_unit_side_action pivot rows', function () {
    $unit = Unit::factory()->withSides()->create();
    $action = Action::factory()->melee()->create();
    $unit->standardSide()->actions()->attach($action->id);

    expect(\DB::table('tos_unit_side_action')->where('action_id', $action->id)->count())->toBe(1);

    $action->delete();

    expect(\DB::table('tos_unit_side_action')->where('action_id', $action->id)->count())->toBe(0);
});
