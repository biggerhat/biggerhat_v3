<?php

use App\Enums\FactionEnum;
use App\Enums\PackageCategoryEnum;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\Package;

it('displays the packages index page', function () {
    Package::factory()->count(3)->create();

    $response = $this->get(route('packages.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Packages/Index')
            ->has('packages.data', 3)
            ->has('factions')
        );
});

it('displays the packages index with no packages', function () {
    $response = $this->get(route('packages.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Packages/Index')
            ->has('packages.data', 0)
        );
});

it('filters packages by faction on index', function () {
    Package::factory()->create(['factions' => [FactionEnum::Guild->value]]);
    Package::factory()->create(['factions' => [FactionEnum::Bayou->value]]);

    $response = $this->get(route('packages.index', ['faction' => 'guild']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Packages/Index')
            ->has('packages.data', 1)
        );
});

it('returns package data with faction details on index', function () {
    Package::factory()->create([
        'name' => 'Test Box',
        'factions' => [FactionEnum::Guild->value],
    ]);

    $response = $this->get(route('packages.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Packages/Index')
            ->has('packages.data.0', fn ($pkg) => $pkg
                ->where('name', 'Test Box')
                ->has('factions.0', fn ($faction) => $faction
                    ->where('value', 'guild')
                    ->has('label')
                    ->has('color')
                    ->has('logo')
                )
                ->etc()
            )
        );
});

it('displays a single package view page', function () {
    $package = Package::factory()->create();

    $response = $this->get(route('packages.view', $package));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Packages/View')
            ->has('package', fn ($pkg) => $pkg
                ->where('id', $package->id)
                ->where('name', $package->name)
                ->where('slug', $package->slug)
                ->etc()
            )
        );
});

it('shows related characters on package view', function () {
    $package = Package::factory()->create();
    $character = Character::factory()->create();
    $package->characters()->attach($character);

    $response = $this->get(route('packages.view', $package));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Packages/View')
            ->has('package.characters', 1)
        );
});

it('shows related keywords on package view', function () {
    $package = Package::factory()->create();
    $keyword = Keyword::factory()->create();
    $package->keywords()->attach($keyword);

    $response = $this->get(route('packages.view', $package));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Packages/View')
            ->has('package.keywords', 1)
        );
});

it('shows related miniatures on package view', function () {
    $package = Package::factory()->create();
    $character = Character::factory()->create();
    $miniature = Miniature::factory()->create(['character_id' => $character->id]);
    $package->miniatures()->attach($miniature);

    $response = $this->get(route('packages.view', $package));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Packages/View')
            ->has('package.miniatures', 1)
        );
});

it('returns 404 for nonexistent package', function () {
    $response = $this->get('/packages/nonexistent-slug');

    $response->assertNotFound();
});

it('displays the box contents page with only packages that have characters attached', function () {
    $withCharacters = Package::factory()->create(['name' => 'Has Characters']);
    $withCharacters->characters()->attach(Character::factory()->create(), ['quantity' => 3]);
    Package::factory()->create(['name' => 'No Characters']);

    $response = $this->get(route('packages.contents'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Packages/Contents')
            ->has('packages', 1)
            ->where('packages.0.name', 'Has Characters')
            ->where('packages.0.characters.0.quantity', 3)
        );
});

it('excludes TOS-only packages from box contents', function () {
    Package::factory()->tos()->create()->characters()->attach(Character::factory()->create());

    $response = $this->get(route('packages.contents'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Packages/Contents')
            ->has('packages', 0)
        );
});

it('includes msrp, category, and character keywords in box contents', function () {
    $package = Package::factory()->create(['name' => 'Priced Box', 'msrp' => 4500, 'category' => PackageCategoryEnum::CoreBox]);
    $character = Character::factory()->create();
    $keyword = Keyword::factory()->create(['name' => 'Academic']);
    $keyword->characters()->attach($character);
    $package->characters()->attach($character, ['quantity' => 1]);

    $response = $this->get(route('packages.contents'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Packages/Contents')
            ->where('packages.0.msrp', 4500)
            ->where('packages.0.category', 'core_box')
            ->where('packages.0.characters.0.keywords.0', 'Academic')
            ->has('categories')
            ->has('keywords')
        );
});
