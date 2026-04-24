<?php

use App\Models\TOS\Ability;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Unit;

it('Unit::notCombinedArmsChild excludes units that are referenced as another unit\'s combined_arms_child', function () {
    $parent = Unit::factory()->withSides()->create(['name' => 'Parent']);
    $child = Unit::factory()->withSides()->create(['name' => 'Child']);
    $parent->update(['combined_arms_child_id' => $child->id]);

    $names = Unit::notCombinedArmsChild()->pluck('name')->all();

    expect($names)->toContain('Parent')
        ->and($names)->not->toContain('Child');
});

it('Ability::forAllegiance returns general abilities plus allegiance-specific ones', function () {
    $a = Allegiance::factory()->create();
    $other = Allegiance::factory()->create();

    $general = Ability::factory()->general()->create(['name' => 'General']);
    $specific = Ability::factory()->create(['name' => 'Specific', 'is_general' => false, 'allegiance_id' => $a->id]);
    $otherOnly = Ability::factory()->create(['name' => 'OtherOnly', 'is_general' => false, 'allegiance_id' => $other->id]);

    $names = Ability::forAllegiance($a)->pluck('name')->all();

    expect($names)->toContain('General', 'Specific')
        ->and($names)->not->toContain('OtherOnly');
});
