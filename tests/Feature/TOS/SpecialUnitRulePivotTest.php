<?php

use App\Enums\TOS\SpecialUnitRuleEnum;
use App\Models\TOS\Unit;

it('Fireteam parameters JSON round-trips intact', function () {
    $unit = Unit::factory()->withSides()->fireteam(baseMm: 50, modelsPerTeam: 4, modelSizeMm: 40)->create();

    $params = $unit->fresh()->specialUnitRules->firstWhere('slug', SpecialUnitRuleEnum::Fireteam->value)?->pivot->parameters;

    expect($params)->toBe(['base_mm' => 50, 'models_per_team' => 4, 'model_size_mm' => 40]);
});

it('Squad parameters JSON round-trips intact', function () {
    $unit = Unit::factory()->withSides()->squad(fireteamCount: 3)->create();

    $params = $unit->fresh()->specialUnitRules->firstWhere('slug', SpecialUnitRuleEnum::Squad->value)?->pivot->parameters;

    expect($params)->toBe(['fireteam_count' => 3]);
});

it('Unique rule attaches with null parameters', function () {
    $unit = Unit::factory()->withSides()->unique()->create();

    $rule = $unit->fresh()->specialUnitRules->firstWhere('slug', SpecialUnitRuleEnum::Unique->value);

    expect($rule)->not->toBeNull()
        ->and($rule->pivot->parameters)->toBeNull();
});

it('a unit can carry multiple special rules with distinct parameters', function () {
    $unit = Unit::factory()
        ->withSides()
        ->fireteam(baseMm: 30, modelsPerTeam: 3, modelSizeMm: 30)
        ->squad(fireteamCount: 2)
        ->unique()
        ->create();

    $rules = $unit->fresh()->specialUnitRules;

    expect($rules->pluck('slug')->all())->toContain(
        SpecialUnitRuleEnum::Fireteam->value,
        SpecialUnitRuleEnum::Squad->value,
        SpecialUnitRuleEnum::Unique->value,
    );

    $fireteamParams = $rules->firstWhere('slug', SpecialUnitRuleEnum::Fireteam->value)->pivot->parameters;
    $squadParams = $rules->firstWhere('slug', SpecialUnitRuleEnum::Squad->value)->pivot->parameters;
    $uniqueParams = $rules->firstWhere('slug', SpecialUnitRuleEnum::Unique->value)->pivot->parameters;

    expect($fireteamParams)->toBe(['base_mm' => 30, 'models_per_team' => 3, 'model_size_mm' => 30])
        ->and($squadParams)->toBe(['fireteam_count' => 2])
        ->and($uniqueParams)->toBeNull();
});
