<?php

use App\Enums\FactionEnum;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\Package;

it('creates a package with factory defaults', function () {
    $package = Package::factory()->create();

    expect($package)->toBeInstanceOf(Package::class)
        ->and($package->name)->toBeString()
        ->and($package->slug)->toBeString()
        ->and($package->factions)->toBeArray();
});

it('casts factions as array', function () {
    $package = Package::factory()->create([
        'factions' => [FactionEnum::Guild->value, FactionEnum::Arcanists->value],
    ]);

    $package->refresh();

    expect($package->factions)->toBe(['guild', 'arcanists']);
});

it('casts is_preassembled as boolean', function () {
    $package = Package::factory()->create(['is_preassembled' => true]);
    $package->refresh();

    expect($package->is_preassembled)->toBeTrue();
});

it('casts released_at as date', function () {
    $package = Package::factory()->create(['released_at' => '2025-01-15']);
    $package->refresh();

    expect($package->released_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('auto-generates slug from name', function () {
    $package = Package::factory()->create(['name' => 'Lady Justice Core Box']);

    expect($package->slug)->toBe('lady-justice-core-box');
});

it('resolves route by slug', function () {
    $package = Package::factory()->create(['name' => 'Rasputina Core Box']);

    expect($package->getRouteKeyName())->toBe('slug');
});

it('has characters relationship via morphedByMany', function () {
    $package = Package::factory()->create();
    $character = Character::factory()->create();

    $package->characters()->attach($character);

    expect($package->characters)->toHaveCount(1)
        ->and($package->characters->first()->id)->toBe($character->id);
});

it('has keywords relationship via morphedByMany', function () {
    $package = Package::factory()->create();
    $keyword = Keyword::factory()->create();

    $package->keywords()->attach($keyword);

    expect($package->keywords)->toHaveCount(1)
        ->and($package->keywords->first()->id)->toBe($keyword->id);
});

it('has miniatures relationship via UsesMiniatures trait', function () {
    $package = Package::factory()->create();
    $character = Character::factory()->create();
    $miniature = Miniature::factory()->create(['character_id' => $character->id]);

    $package->miniatures()->attach($miniature);

    expect($package->miniatures)->toHaveCount(1)
        ->and($package->miniatures->first()->id)->toBe($miniature->id);
});

it('allows inverse packages relationship on Character', function () {
    $package = Package::factory()->create();
    $character = Character::factory()->create();

    $package->characters()->attach($character);
    $character->refresh();

    expect($character->packages)->toHaveCount(1)
        ->and($character->packages->first()->id)->toBe($package->id);
});

it('allows inverse packages relationship on Keyword', function () {
    $package = Package::factory()->create();
    $keyword = Keyword::factory()->create();

    $package->keywords()->attach($keyword);
    $keyword->refresh();

    expect($keyword->packages)->toHaveCount(1)
        ->and($keyword->packages->first()->id)->toBe($package->id);
});

it('can have multiple characters and keywords', function () {
    $package = Package::factory()->create();
    $characters = Character::factory()->count(3)->create();
    $keywords = Keyword::factory()->count(2)->create();

    $package->characters()->attach($characters->pluck('id'));
    $package->keywords()->attach($keywords->pluck('id'));

    expect($package->characters)->toHaveCount(3)
        ->and($package->keywords)->toHaveCount(2);
});

it('stores nullable fields correctly', function () {
    $package = Package::factory()->create([
        'description' => null,
        'sku' => null,
        'upc' => null,
        'msrp' => null,
        'released_at' => null,
        'factions' => null,
    ]);

    $package->refresh();

    expect($package->description)->toBeNull()
        ->and($package->sku)->toBeNull()
        ->and($package->upc)->toBeNull()
        ->and($package->msrp)->toBeNull()
        ->and($package->released_at)->toBeNull();
});
