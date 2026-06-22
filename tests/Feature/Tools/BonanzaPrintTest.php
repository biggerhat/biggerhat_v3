<?php

use App\Models\Action;
use App\Models\LootCard;

it('renders the printer-friendly Bonanza loot deck PDF from card data', function () {
    $card = LootCard::create([
        'slug' => 'test-loot',
        'name' => 'Test Loot',
        'suit' => 'crow',
        'value' => 7,
        'value_label' => '7',
        'sort_order' => 1,
        'title_a' => 'Alpha',
        'effect_a' => 'Gain 1 {{crow}} this turn.',
        'title_b' => 'Beta',
        'effect_b' => 'Heal 2.',
    ]);

    $action = Action::factory()->create([
        'name' => 'Quick Slash', 'stone_cost' => 1, 'range' => 1, 'stat' => 6, 'damage' => '2/3/4',
    ]);
    $card->sideAActions()->attach($action->id, ['side' => 'a', 'sort_order' => 0]);

    $resp = $this->get(route('tools.bonanza_loot_deck.print'));

    $resp->assertOk();
    expect($resp->headers->get('content-type'))->toContain('application/pdf');
    expect(strlen($resp->getContent()))->toBeGreaterThan(1000);
});

it('renders the print PDF with an empty deck', function () {
    $this->get(route('tools.bonanza_loot_deck.print'))->assertOk();
});

it('renders cards without images just fine (no stored image needed)', function () {
    LootCard::create([
        'slug' => 'no-img',
        'name' => 'No Image Card',
        'suit' => 'ram',
        'value' => 3,
        'value_label' => '3',
        'sort_order' => 1,
        'image' => null,
        'effect_a' => 'Test effect.',
    ]);

    $resp = $this->get(route('tools.bonanza_loot_deck.print'));
    $resp->assertOk();
    expect($resp->headers->get('content-type'))->toContain('application/pdf');
});
