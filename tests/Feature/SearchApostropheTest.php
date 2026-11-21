<?php

use App\Models\Action;
use App\Models\Character;

test('a search finds typographic-apostrophe text however the apostrophe is typed', function () {
    Action::factory()->create(['name' => 'Breath of Frost', 'description' => 'Deal damage equal to this action’s damage.']);

    foreach (["this action's damage", 'this action’s damage', 'this actions damage'] as $typed) {
        expect(Action::whereSearch('description', $typed)->count())->toBe(1, $typed);
    }
    expect(Action::whereSearch('description', 'this action damage')->count())->toBe(0);
    // A plain apostrophe in the data is found by a curly one too, and LIKE wildcards stay literal.
    Character::factory()->create(['name' => "Explorer's Guide", 'display_name' => "Explorer's Guide"]);
    expect(Character::whereSearch('name', 'explorer’s')->count())->toBe(1)
        ->and(Character::whereSearch('name', '100%')->count())->toBe(0);
});

test('the Card Creator and public API searches find a name whichever apostrophe is typed', function () {
    Action::factory()->create(['name' => 'Breath of Frost’s Echo']);

    foreach (["frost's", 'frost’s', 'frosts'] as $typed) {
        $this->getJson(route('api.card-creator.actions', ['q' => $typed]))->assertOk()->assertJsonFragment(['name' => 'Breath of Frost’s Echo']);
    }
});
