<?php

use App\Models\TOS\Unit;

it('unit index includes Combined Arms children so the database stays browseable', function () {
    $parent = Unit::factory()->withSides()->create(['name' => 'Parent Unit']);
    $child = Unit::factory()->withSides()->create(['name' => 'Embedded Child Unit']);
    $parent->update(['combined_arms_child_id' => $child->id]);

    Unit::factory()->withSides()->create(['name' => 'Standalone Unit']);

    // All three units are visible in the browse — users can read the child
    // card even though it's hired through its parent in the crew builder.
    $this->get(route('tos.units.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('TOS/Units/Index')
            ->has('units.data', 3)
        );
});

it('child unit still resolves by its sculpt for direct linking', function () {
    $parent = Unit::factory()->withSides()->create();
    $child = Unit::factory()->withSides()->create(['name' => 'Embedded']);
    $parent->update(['combined_arms_child_id' => $child->id]);

    $sculpt = \App\Models\TOS\UnitSculpt::factory()->forUnit($child)->create(['slug' => 'embedded-sculpt']);
    $child->allegiances()->attach(\App\Models\TOS\Allegiance::factory()->create()->id);

    $this->get(route('tos.units.view', $sculpt->slug))->assertOk();
});

it('combinedArmsParent relation reports the parent when a unit is embedded', function () {
    $parent = Unit::factory()->withSides()->create();
    $child = Unit::factory()->withSides()->create();
    $parent->update(['combined_arms_child_id' => $child->id]);

    expect($child->fresh()->combinedArmsParent?->id)->toBe($parent->id)
        ->and($parent->fresh()->combinedArmsParent)->toBeNull();
});
