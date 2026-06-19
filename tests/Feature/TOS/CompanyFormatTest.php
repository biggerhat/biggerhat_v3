<?php

use App\Enums\TOS\GarrisonFormatEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Company;
use App\Models\TOS\CompanyUnit;
use App\Models\TOS\Unit;

it('exposes company-play format semantics distinct from the garrison pool', function () {
    expect(GarrisonFormatEnum::OneCommander->commandersFielded())->toBe(1);
    expect(GarrisonFormatEnum::OneCommanderPlus10->commandersFielded())->toBe(1);
    expect(GarrisonFormatEnum::TwoCommanders->commandersFielded())->toBe(2);
    expect(GarrisonFormatEnum::TheaterOfWar->commandersFielded())->toBe(2);
    expect(GarrisonFormatEnum::NoMansLand->commandersFielded())->toBe(1);

    expect(GarrisonFormatEnum::OneCommanderPlus10->scripBonus())->toBe(10);
    expect(GarrisonFormatEnum::OneCommander->scripBonus())->toBe(0);
});

it('adds the format Scrip bonus on top of Commander Scrip', function () {
    $commander = Unit::factory()->create(['scrip' => 20]);
    $company = Company::factory()->create(['format' => GarrisonFormatEnum::OneCommanderPlus10]);
    CompanyUnit::create(['company_id' => $company->id, 'unit_id' => $commander->id, 'is_commander' => true, 'position' => 0]);

    expect($company->scripBudget())->toBe(30); // 20 Commander Scrip + 10 format bonus
    expect($company->maxCommandersFielded())->toBe(1);
    expect($company->commanderCount())->toBe(1);
});

it('sums multiple Commanders under a two-commander format', function () {
    $c1 = Unit::factory()->create(['scrip' => 18]);
    $c2 = Unit::factory()->create(['scrip' => 22]);
    $company = Company::factory()->create(['format' => GarrisonFormatEnum::TwoCommanders]);
    CompanyUnit::create(['company_id' => $company->id, 'unit_id' => $c1->id, 'is_commander' => true, 'position' => 0]);
    CompanyUnit::create(['company_id' => $company->id, 'unit_id' => $c2->id, 'is_commander' => true, 'position' => 1]);

    expect($company->scripBudget())->toBe(40); // 18 + 22, no bonus
    expect($company->maxCommandersFielded())->toBe(2);
    expect($company->commanderCount())->toBe(2);
});

it('defaults to a single Commander and no bonus when no format is chosen', function () {
    $company = Company::factory()->create(['format' => null]);

    expect($company->maxCommandersFielded())->toBe(1);
    expect($company->scripBudget())->toBe(0);
});

it('stores an Envoy as a second Allegiance', function () {
    $primary = Allegiance::factory()->earth()->create();
    $envoy = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forAllegiance($primary)->create(['envoy_allegiance_id' => $envoy->id]);

    expect($company->envoyAllegiance->id)->toBe($envoy->id);
    expect($company->allegiance->id)->toBe($primary->id);
});
