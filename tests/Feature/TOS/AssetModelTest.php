<?php

use App\Enums\TOS\AssetLimitTypeEnum;
use App\Models\TOS\Ability;
use App\Models\TOS\Action;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Asset;

it('factory builds a bare asset with no pivots', function () {
    $asset = Asset::factory()->create();

    expect($asset->fresh()->limits)->toHaveCount(0)
        ->and($asset->fresh()->allegiances)->toHaveCount(0)
        ->and($asset->fresh()->abilities)->toHaveCount(0)
        ->and($asset->fresh()->actions)->toHaveCount(0);
});

it('attaches allegiances via the M:M pivot', function () {
    $ke = Allegiance::factory()->create(['name' => "King's Empire"]);
    $asset = Asset::factory()->forAllegiance($ke)->create();

    expect($asset->fresh()->allegiances->pluck('id'))->toContain($ke->id);
});

it('restrictedByUnitType state creates a Restricted limit with Unit Type param', function () {
    $asset = Asset::factory()->restrictedByUnitType('commander')->create();
    $limit = $asset->fresh()->limits->first();

    expect($limit->limit_type)->toBe(AssetLimitTypeEnum::Restricted)
        ->and($limit->parameter_value)->toBe('commander');
});

it('unique state creates a Unique limit with no parameters', function () {
    $asset = Asset::factory()->unique()->create();
    $limit = $asset->fresh()->limits->first();

    expect($limit->limit_type)->toBe(AssetLimitTypeEnum::Unique)
        ->and($limit->parameter_type)->toBeNull()
        ->and($limit->parameter_value)->toBeNull();
});

it('adjunct state stores the base size as parameter_value', function () {
    $asset = Asset::factory()->adjunct(30)->create();
    $limit = $asset->fresh()->limits->first();

    expect($limit->limit_type)->toBe(AssetLimitTypeEnum::Adjunct)
        ->and($limit->parameter_value)->toBe('30');
});

it('belongsToMany Ability and Action via their own pivots', function () {
    $ability = Ability::factory()->general()->create();
    $action = Action::factory()->melee()->create();
    $asset = Asset::factory()->create();

    $asset->abilities()->attach($ability->id, ['sort_order' => 0]);
    $asset->actions()->attach($action->id, ['sort_order' => 0]);

    expect($asset->fresh()->abilities->pluck('id'))->toContain($ability->id)
        ->and($asset->fresh()->actions->pluck('id'))->toContain($action->id);
});

it('deleting an asset cascades limits and pivots', function () {
    $ke = Allegiance::factory()->create();
    $asset = Asset::factory()->forAllegiance($ke)->unique()->create();
    $assetId = $asset->id;

    $asset->delete();

    expect(Asset::find($assetId))->toBeNull()
        ->and(\DB::table('tos_asset_limits')->where('asset_id', $assetId)->count())->toBe(0)
        ->and(\DB::table('tos_allegiance_asset')->where('asset_id', $assetId)->count())->toBe(0);
});
