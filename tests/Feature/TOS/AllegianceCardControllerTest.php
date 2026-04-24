<?php

use App\Models\TOS\AllegianceCard;

it('renders the allegiance cards index', function () {
    AllegianceCard::factory()->count(3)->create();

    $this->get(route('tos.allegiance_cards.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/AllegianceCards/Index')->has('cards', 3));
});

it('renders a single allegiance card by slug', function () {
    $card = AllegianceCard::factory()->withAbilities(2)->create(['slug' => 'ke-card', 'name' => "King's Empire"]);

    $this->get(route('tos.allegiance_cards.view', $card->slug))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/AllegianceCards/View')
            ->where('card.name', "King's Empire")
            ->has('card.abilities', 2)
        );
});

it('404s on an unknown slug', function () {
    $this->get(route('tos.allegiance_cards.view', 'does-not-exist'))->assertNotFound();
});
