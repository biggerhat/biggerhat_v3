<?php

use App\Enums\TOS\AllegianceTypeEnum;
use App\Models\TOS\Ability;
use App\Models\TOS\Allegiance;
use App\Models\TOS\AllegianceCard;

it('belongs to its Allegiance', function () {
    $allegiance = Allegiance::factory()->malifaux()->create();
    $card = AllegianceCard::factory()->forAllegiance($allegiance)->create();

    expect($card->fresh()->allegiance->id)->toBe($allegiance->id);
});

it('casts type to AllegianceTypeEnum', function () {
    $card = AllegianceCard::factory()->create(['type' => 'malifaux']);

    expect($card->fresh()->type)->toBe(AllegianceTypeEnum::Malifaux);
});

it('syncs abilities via the pivot with sort_order', function () {
    $card = AllegianceCard::factory()->create();
    $a1 = Ability::factory()->general()->create();
    $a2 = Ability::factory()->general()->create();

    $card->abilities()->sync([
        $a1->id => ['sort_order' => 0],
        $a2->id => ['sort_order' => 1],
    ]);

    expect($card->fresh()->abilities->pluck('id'))->toContain($a1->id, $a2->id);
});

it('withAbilities() factory state attaches the requested ability count', function () {
    $card = AllegianceCard::factory()->withAbilities(3)->create();

    expect($card->fresh()->abilities->count())->toBe(3);
});

it('forAllegiance() mirrors the allegiance type onto the card', function () {
    $allegiance = Allegiance::factory()->malifaux()->create();
    $card = AllegianceCard::factory()->forAllegiance($allegiance)->create();

    expect($card->type)->toBe(AllegianceTypeEnum::Malifaux);
});
