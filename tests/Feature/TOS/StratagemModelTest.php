<?php

use App\Enums\TOS\AllegianceTypeEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Stratagem;

it('tactical_cost and allegiance_type columns exist as distinct fields', function () {
    $s = Stratagem::factory()->create(['tactical_cost' => 3]);

    expect($s->tactical_cost)->toBe(3)
        ->and($s->getAttributes())->toHaveKey('tactical_cost')
        ->and($s->getAttributes())->toHaveKey('allegiance_type');
});

it('belongs to a specific Allegiance when forAllegiance() is used', function () {
    $ke = Allegiance::factory()->earth()->create();
    $s = Stratagem::factory()->forAllegiance($ke)->create();

    expect($s->fresh()->allegiance->id)->toBe($ke->id)
        ->and($s->allegiance_type)->toBe(AllegianceTypeEnum::Earth);
});

it('forType() leaves allegiance_id null and sets the type only', function () {
    $s = Stratagem::factory()->forType(AllegianceTypeEnum::Malifaux)->create();

    expect($s->allegiance_id)->toBeNull()
        ->and($s->allegiance_type)->toBe(AllegianceTypeEnum::Malifaux);
});

it('availableTo scope returns Stratagems matching the target Allegiance directly', function () {
    $cult = Allegiance::factory()->malifaux()->create();
    $hordes = Allegiance::factory()->malifaux()->create();
    $ke = Allegiance::factory()->earth()->create();

    Stratagem::factory()->forAllegiance($cult)->create(['name' => 'Cult-specific']);

    expect(Stratagem::availableTo($cult)->pluck('name'))->toContain('Cult-specific')
        ->and(Stratagem::availableTo($hordes)->pluck('name'))->not->toContain('Cult-specific')
        ->and(Stratagem::availableTo($ke)->pluck('name'))->not->toContain('Cult-specific');
});

it('availableTo also returns Stratagems that match by allegiance_type', function () {
    $cult = Allegiance::factory()->malifaux()->create();
    $ke = Allegiance::factory()->earth()->create();

    Stratagem::factory()->forType(AllegianceTypeEnum::Malifaux)->create(['name' => 'Any Malifaux']);

    expect(Stratagem::availableTo($cult)->pluck('name'))->toContain('Any Malifaux')
        ->and(Stratagem::availableTo($ke)->pluck('name'))->not->toContain('Any Malifaux');
});
