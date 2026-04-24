<?php

use App\Enums\TOS\UsageLimitEnum;
use App\Models\TOS\Ability;
use App\Models\TOS\Unit;

it('usage_limit casts to UsageLimitEnum', function () {
    $a = Ability::factory()->oncePer(UsageLimitEnum::OncePerGame)->create();

    expect($a->fresh()->usage_limit)->toBe(UsageLimitEnum::OncePerGame);
});

it('factory default leaves usage_limit null', function () {
    $a = Ability::factory()->create();

    expect($a->fresh()->usage_limit)->toBeNull();
});

it('deleting an Ability cascades tos_unit_side_ability pivot rows', function () {
    $unit = Unit::factory()->withSides()->create();
    $ability = Ability::factory()->general()->create();
    $unit->standardSide()->abilities()->attach($ability->id);
    $ability->fresh();

    expect(\DB::table('tos_unit_side_ability')->where('ability_id', $ability->id)->count())->toBe(1);

    $ability->delete();

    expect(\DB::table('tos_unit_side_ability')->where('ability_id', $ability->id)->count())->toBe(0);
});
