<?php

use App\Enums\TOS\AllegianceTypeEnum;
use App\Enums\TOS\GarrisonFormatEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\AllegianceCard;
use App\Models\TOS\Asset;
use App\Models\TOS\Garrison;
use App\Models\TOS\GarrisonUnit;
use App\Models\TOS\Stratagem;
use App\Models\TOS\Unit;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->allegiance = Allegiance::factory()->earth()->create();
});

it('hydrates the format enum cast', function () {
    $g = Garrison::factory()
        ->forUser($this->user)
        ->forAllegiance($this->allegiance)
        ->format(GarrisonFormatEnum::TwoCommanders)
        ->create();

    expect($g->fresh()->format)->toBe(GarrisonFormatEnum::TwoCommanders);
});

it('exposes per-format caps via helper methods', function () {
    $g = Garrison::factory()
        ->forUser($this->user)
        ->forAllegiance($this->allegiance)
        ->format(GarrisonFormatEnum::TwoCommanders)
        ->create();

    expect($g->maxCommanders())->toBe(3);
    expect($g->scripBudget())->toBe(75);
    expect($g->stratagemCount())->toBe(12);
    expect($g->envoyCount())->toBe(1);
});

it('auto-generates a share_code on create', function () {
    $g = Garrison::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create();

    expect($g->share_code)->toBeString()->and(strlen($g->share_code))->toBe(12);
});

it('cascades units, assets, stratagems, and envoys on delete', function () {
    $g = Garrison::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create();
    $unit = Unit::factory()->create();
    GarrisonUnit::create(['garrison_id' => $g->id, 'unit_id' => $unit->id, 'is_commander' => false]);
    $asset = Asset::factory()->create();
    $g->assets()->attach($asset->id, ['quantity' => 2]);
    $stratagem = Stratagem::factory()->forAllegiance($this->allegiance)->create();
    $g->stratagems()->attach($stratagem->id);
    $card = AllegianceCard::factory()->for($this->allegiance)->create();
    $g->envoys()->attach($card->id);

    $g->delete();

    expect(GarrisonUnit::where('garrison_id', $g->id)->exists())->toBeFalse();
    expect(\DB::table('tos_garrison_assets')->where('garrison_id', $g->id)->exists())->toBeFalse();
    expect(\DB::table('tos_garrison_stratagems')->where('garrison_id', $g->id)->exists())->toBeFalse();
    expect(\DB::table('tos_garrison_envoys')->where('garrison_id', $g->id)->exists())->toBeFalse();
});

it('sums scrip across non-Commander units and asset quantities', function () {
    $g = Garrison::factory()
        ->forUser($this->user)
        ->forAllegiance($this->allegiance)
        ->format(GarrisonFormatEnum::TwoCommanders)
        ->create();

    $cmdr = Unit::factory()->create(['scrip' => 18]);
    $minion = Unit::factory()->create(['scrip' => 7]);
    GarrisonUnit::create(['garrison_id' => $g->id, 'unit_id' => $cmdr->id, 'is_commander' => true]);
    GarrisonUnit::create(['garrison_id' => $g->id, 'unit_id' => $minion->id, 'is_commander' => false]);

    $asset = Asset::factory()->create(['scrip_cost' => 5]);
    $g->assets()->attach($asset->id, ['quantity' => 3]);

    // 7 (minion) + 3 × 5 (asset pool) = 22. Commander scrip is excluded.
    expect($g->scripSpent())->toBe(22);
    expect($g->scripRemaining())->toBe(75 - 22);
});

it('reports violations against the active format', function () {
    $g = Garrison::factory()
        ->forUser($this->user)
        ->forAllegiance($this->allegiance)
        ->format(GarrisonFormatEnum::OneCommander) // 2 commanders / 40 scrip / 6 stratagems / 0 envoys
        ->create();

    // Build a roster that breaks every cap.
    $cmdr = Unit::factory()->create(['scrip' => 0, 'name' => 'Echo']);
    foreach (range(1, 3) as $_) {
        GarrisonUnit::create(['garrison_id' => $g->id, 'unit_id' => $cmdr->id, 'is_commander' => true]);
    }

    $expensive = Unit::factory()->create(['scrip' => 50, 'name' => 'Bigshot']);
    GarrisonUnit::create(['garrison_id' => $g->id, 'unit_id' => $expensive->id, 'is_commander' => false]);

    foreach (range(1, 7) as $_) {
        $g->stratagems()->attach(Stratagem::factory()->forAllegiance($this->allegiance)->create()->id);
    }
    $g->envoys()->attach(AllegianceCard::factory()->for($this->allegiance)->create()->id);

    $violations = $g->fresh()->violations();

    expect($violations)->toContain('Too many Commanders (3/2).');
    expect($violations)->toContain('Over Scrip budget by 10 (used 50/40).');
    expect($violations)->toContain('Too many copies of "Echo" (3/2).');
    expect($violations)->toContain('Too many Stratagems (7/6).');
    expect($violations)->toContain('Too many Envoys (1/0).');
});

it('reports an empty violation list for a legal Garrison', function () {
    $g = Garrison::factory()
        ->forUser($this->user)
        ->forAllegiance($this->allegiance)
        ->format(GarrisonFormatEnum::OneCommander)
        ->create();

    $cmdr = Unit::factory()->create(['scrip' => 18, 'name' => 'Cmdr']);
    GarrisonUnit::create(['garrison_id' => $g->id, 'unit_id' => $cmdr->id, 'is_commander' => true]);
    $minion = Unit::factory()->create(['scrip' => 6, 'name' => 'Minion']);
    GarrisonUnit::create(['garrison_id' => $g->id, 'unit_id' => $minion->id, 'is_commander' => false]);

    expect($g->fresh()->isLegal())->toBeTrue();
    expect($g->fresh()->violations())->toBe([]);
});

it('uses the AllegianceTypeEnum cases for ofType lookups', function () {
    // Sanity-check that the Allegiance scope still works with the new
    // Garrison flow — we'll lean on it later when scoping unit pools.
    $earth = Allegiance::factory()->earth()->create();
    Allegiance::factory()->malifaux()->create();

    expect(Allegiance::ofType(AllegianceTypeEnum::Earth)->pluck('id'))->toContain($earth->id);
});
