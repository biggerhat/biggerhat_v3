<?php

use App\Enums\TOS\EnvoyRestrictionEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Envoy;

it('Court of Two (Malifaux syndicate) is hireable into Malifaux allegiances', function () {
    $courtOfTwo = Allegiance::factory()->malifaux()->syndicate()->create(['name' => 'Court of Two']);
    $cult = Allegiance::factory()->malifaux()->create(['name' => 'Cult of the Burning Man']);
    $hordes = Allegiance::factory()->malifaux()->create(['name' => 'Gibbering Hordes']);

    Envoy::factory()->forAllegiance($courtOfTwo)->create(['name' => 'Court of Two Envoy']);

    expect(Envoy::hireableInto($cult)->pluck('name'))->toContain('Court of Two Envoy')
        ->and(Envoy::hireableInto($hordes)->pluck('name'))->toContain('Court of Two Envoy');
});

it('Court of Two is NOT hireable into Earth allegiances', function () {
    $courtOfTwo = Allegiance::factory()->malifaux()->syndicate()->create(['name' => 'Court of Two']);
    $kingsEmpire = Allegiance::factory()->earth()->create(['name' => "King's Empire"]);
    $abyssinia = Allegiance::factory()->earth()->create(['name' => 'Abyssinia']);

    Envoy::factory()->forAllegiance($courtOfTwo)->create(['name' => 'Court of Two Envoy']);

    expect(Envoy::hireableInto($kingsEmpire)->count())->toBe(0)
        ->and(Envoy::hireableInto($abyssinia)->count())->toBe(0);
});

it('Earth-restriction envoys are hireable into Earth allegiances only', function () {
    $syndicate = Allegiance::factory()->earth()->syndicate()->create();
    Envoy::factory()->forAllegiance($syndicate)->create(['name' => 'Earth Envoy']);

    $earthAllegiance = Allegiance::factory()->earth()->create();
    $malifauxAllegiance = Allegiance::factory()->malifaux()->create();

    expect(Envoy::hireableInto($earthAllegiance)->pluck('name'))->toContain('Earth Envoy')
        ->and(Envoy::hireableInto($malifauxAllegiance)->count())->toBe(0);
});

it('forAllegiance() derives restriction from the parent type', function () {
    $malifauxSyndicate = Allegiance::factory()->malifaux()->syndicate()->create();
    $earthSyndicate = Allegiance::factory()->earth()->syndicate()->create();

    $m = Envoy::factory()->forAllegiance($malifauxSyndicate)->create();
    $e = Envoy::factory()->forAllegiance($earthSyndicate)->create();

    expect($m->restriction)->toBe(EnvoyRestrictionEnum::Malifaux)
        ->and($e->restriction)->toBe(EnvoyRestrictionEnum::Earth);
});

it('hybrid Allegiance can hire Envoys keyed to either type', function () {
    $hybrid = \App\Models\TOS\Allegiance::factory()->earth()->create();
    $hybrid->update(['secondary_type' => \App\Enums\TOS\AllegianceTypeEnum::Malifaux->value]);
    $hybrid->refresh();

    $earthSyndicate = \App\Models\TOS\Allegiance::factory()->earth()->syndicate()->create();
    $malifauxSyndicate = \App\Models\TOS\Allegiance::factory()->malifaux()->syndicate()->create();

    $earthEnvoy = \App\Models\TOS\Envoy::factory()->forAllegiance($earthSyndicate)
        ->create(['name' => 'Earth Envoy', 'restriction' => 'earth']);
    $malifauxEnvoy = \App\Models\TOS\Envoy::factory()->forAllegiance($malifauxSyndicate)
        ->create(['name' => 'Malifaux Envoy', 'restriction' => 'malifaux']);

    $names = \App\Models\TOS\Envoy::hireableInto($hybrid)->pluck('name')->all();
    expect($names)->toContain('Earth Envoy')
        ->and($names)->toContain('Malifaux Envoy');
});
