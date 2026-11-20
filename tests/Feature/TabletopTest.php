<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\Character;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use Spatie\Permission\Models\Permission;

it('hides the tabletop until its feature flag is on', function () {
    $this->get(route('tools.tabletop'))->assertNotFound();
});

it('renders the tabletop page when the flag is on, with the tokens a model can carry', function () {
    Feature::for(null)->activate('experiment-tabletop');
    \App\Models\Token::factory()->create(['name' => 'Shielded']);

    $this->get(route('tools.tabletop'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Tools/Tabletop')->where('token_options.0.name', 'Shielded'));
});

it('lets a beta tester open the tabletop while the flag is off, and nobody else', function () {
    $tester = User::factory()->create();
    $tester->givePermissionTo(PermissionEnum::UseTabletopBeta->value);

    $this->actingAs(User::factory()->create())->get(route('tools.tabletop'))->assertNotFound();
    $this->actingAs($tester)->get(route('tools.tabletop'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Tools/Tabletop'));
});

it('gives super admins beta access without the flag', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::SuperAdmin->value);

    $this->actingAs($admin)->get(route('tools.tabletop'))->assertOk();
});

it('has the beta permission after migrating', function () {
    expect(Permission::where('name', 'use_tabletop_beta')->exists())->toBeTrue();
});

it('searches character cards for the sandbox with base, Sz and Sp', function () {
    Feature::for(null)->activate('experiment-tabletop');
    Character::factory()->create(['name' => 'Pandora', 'title' => 'Gifted Lady', 'base' => 40, 'size' => 2, 'speed' => 6]);
    Character::factory()->create(['name' => 'Lady Justice', 'title' => null, 'base' => 50, 'size' => 3, 'speed' => 5]);
    Character::factory()->create(['name' => 'Silurid', 'title' => null, 'base' => 30]);
    Character::factory()->create(['name' => 'Hidden Lady', 'title' => null, 'is_hidden' => true]);

    $this->getJson(route('tools.tabletop.characters', ['q' => 'lady']))
        ->assertOk()
        ->assertJsonCount(2, 'characters')
        ->assertJsonPath('characters.0.name', 'Lady Justice')
        ->assertJsonPath('characters.0.base_mm', 50)
        ->assertJsonPath('characters.0.sz', 3)
        ->assertJsonPath('characters.0.sp', 5)
        ->assertJsonPath('characters.1.name', 'Pandora, Gifted Lady');
    $this->getJson(route('tools.tabletop.characters', ['q' => 'gifted pandora']))->assertJsonCount(1, 'characters');
    $this->getJson(route('tools.tabletop.characters', ['q' => 'silurid']))->assertJsonPath('characters.0.health', fn ($h) => is_int($h));
    $this->getJson(route('tools.tabletop.characters', ['q' => 'silurid']))
        ->assertJsonStructure(['characters' => [['faction_key', 'station', 'df', 'wp', 'front_image', 'back_image']]]);
    $this->getJson(route('tools.tabletop.characters', ['q' => 'nobody']))->assertJsonCount(0, 'characters');
});

it('refuses short or missing search text', function () {
    Feature::for(null)->activate('experiment-tabletop');

    $this->getJson(route('tools.tabletop.characters', ['q' => 'a']))->assertUnprocessable();
    $this->getJson(route('tools.tabletop.characters'))->assertUnprocessable();
});

it('hides the character search until the flag is on, or for beta testers', function () {
    $this->getJson(route('tools.tabletop.characters', ['q' => 'lady']))->assertNotFound();

    $tester = User::factory()->create();
    $tester->givePermissionTo(PermissionEnum::UseTabletopBeta->value);
    $this->actingAs($tester)->getJson(route('tools.tabletop.characters', ['q' => 'lady']))->assertOk();
});

it('offers the built-in table surfaces and terrain textures, with active uploads added to them', function () {
    Feature::for(null)->activate('experiment-tabletop');
    \App\Models\TabletopImage::factory()->create(['name' => 'Marble floor', 'image_path' => 'tabletop/marble.webp']);
    \App\Models\TabletopImage::factory()->inactive()->create(['name' => 'Hidden floor']);

    $this->get(route('tools.tabletop'))->assertInertia(fn (Assert $page) => $page
        ->has('table_images', 7)
        ->where('table_images.0', ['key' => 'grass', 'name' => 'Grass', 'url' => '/images/tabletop/tables/grass.svg', 'fit' => 'tile'])
        ->where('table_images.6.name', 'Marble floor')
        ->where('table_images.6.fit', 'cover')
        ->where('terrain_images.tower', '/images/tabletop/terrain/tower.svg')
        ->has('terrain_images', 21));
});

it('lets an uploaded terrain texture replace the built-in one for its kind, unless inactive', function () {
    Feature::for(null)->activate('experiment-tabletop');
    $granite = \App\Models\TabletopImage::factory()->terrain('tower')->create(['image_path' => 'tabletop/granite.webp']);
    \App\Models\TabletopImage::factory()->terrain('wall')->inactive()->create(['image_path' => 'tabletop/off.webp']);

    $this->get(route('tools.tabletop'))->assertInertia(fn (Assert $page) => $page
        ->where('terrain_images.tower', fn ($url) => str_ends_with($url, 'tabletop/granite.webp'))
        ->where('terrain_images.wall', '/images/tabletop/terrain/wall.svg'));

    $granite->update(['is_active' => false]);
    $this->get(route('tools.tabletop'))->assertInertia(fn (Assert $page) => $page->where('terrain_images.tower', '/images/tabletop/terrain/tower.svg'));
});

it('ships a built-in image file for every table surface and terrain kind', function () {
    foreach (array_keys(\App\Support\Tabletop\TabletopImages::DEFAULT_TABLES) as $key) {
        expect(file_exists(public_path("images/tabletop/tables/{$key}.svg")))->toBeTrue("missing table {$key}");
    }
    foreach (array_keys(\App\Support\Tabletop\TabletopImages::TERRAIN_KINDS) as $key) {
        expect(file_exists(public_path("images/tabletop/terrain/{$key}.svg")))->toBeTrue("missing terrain {$key}");
    }
});

it('tells the tabletop which markers are Strategy markers', function () {
    Feature::for(null)->activate('experiment-tabletop');
    \App\Models\Marker::factory()->strategy()->create(['name' => 'Turf Marker']);
    \App\Models\Marker::factory()->create(['name' => 'Scheme Marker']);

    $this->get(route('tools.tabletop'))->assertInertia(fn (Assert $page) => $page
        ->where('markers', fn ($markers) => collect($markers)->firstWhere('name', 'Turf Marker')['strategy'] === true
            && collect($markers)->firstWhere('name', 'Scheme Marker')['strategy'] === false));
});
