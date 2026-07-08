<?php

use App\Enums\GameSystemEnum;
use App\Models\Character;
use App\Models\Package;
use App\Models\TOS\Unit;
use Illuminate\Support\Facades\Http;

const WYRD_TOS_URL = 'https://giveusyourmoneypleasethankyou-wyrd.com/collections/the-other-side/products.json*';

function fakeTosResponses(array $products): array
{
    return [
        WYRD_TOS_URL => Http::sequence()
            ->push(['products' => $products])
            ->push(['products' => []]),
    ];
}

function fakeTosProduct(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'title' => 'Abyssinia Allegiance Box',
        'handle' => 'abyssinia-allegiance-box',
        'body_html' => '<p>Some prose.</p><p><strong>Contains</strong></p><ul data-rte-list="default"><li><p><strong>Commander : </strong>Prince Unathi [1 model]</p></li></ul>',
        'product_type' => 'The Other Side Allegiance Box',
        'tags' => ['Abyssinia', 'The Other Side', 'ToS', 'ToS-Allegiance', 'ToS-Core'],
        'variants' => [['sku' => 'WYR40151', 'price' => '90.00']],
        'images' => [],
    ], $overrides);
}

it('imports a TOS-only package and links its Unit by name', function () {
    Unit::factory()->create(['name' => 'Prince Unathi']);

    Http::fake(fakeTosResponses([fakeTosProduct()]));

    $this->artisan('app:import-wyrd-tos-packages --skip-images')->assertSuccessful();

    $package = Package::where('slug', 'abyssinia-allegiance-box')->first();

    expect($package)->not->toBeNull()
        ->and($package->game_system)->toBe(GameSystemEnum::Tos)
        ->and($package->tosUnits)->toHaveCount(1)
        ->and($package->tosUnits->first()->name)->toBe('Prince Unathi');
});

it('flags a crossover product as Both and links its Character via the Malifaux-alias prose', function () {
    Character::factory()->create(['name' => 'Datsue Ba', 'display_name' => 'Datsue Ba']);

    $product = fakeTosProduct([
        'title' => 'Binh Nguyen',
        'handle' => 'binh-nguyen-1',
        'body_html' => '<p><strong>This Model is compatible with Malifaux Third Edition as Datsue Ba and the M3E Card is included in the box.</strong></p><p><strong>Contains</strong></p><ul data-rte-list="default"><li><p><strong>Champion : </strong><span>Binh Nguyen</span></p></li></ul>',
        'tags' => ['Malifaux', 'The Other Side', 'ToS', 'ToS-Character'],
        'variants' => [['sku' => 'WYR40351', 'price' => '15.00']],
    ]);

    Http::fake(fakeTosResponses([$product]));

    $this->artisan('app:import-wyrd-tos-packages --skip-images')->assertSuccessful();

    $package = Package::where('sku', 'WYR40351')->first();

    expect($package->game_system)->toBe(GameSystemEnum::Both)
        ->and($package->characters)->toHaveCount(1)
        ->and($package->characters->first()->display_name)->toBe('Datsue Ba');
});

it('upgrades an existing Malifaux package to Both without overwriting its fields, unless --force is passed', function () {
    $existing = Package::factory()->create([
        'slug' => 'abyssinia-allegiance-box',
        'name' => 'Hand-curated Malifaux Name',
        'sku' => 'WYR40151',
        'game_system' => GameSystemEnum::Malifaux,
    ]);
    Unit::factory()->create(['name' => 'Prince Unathi']);

    Http::fake(fakeTosResponses([fakeTosProduct()]));

    $this->artisan('app:import-wyrd-tos-packages --skip-images')->assertSuccessful();

    $existing->refresh();

    expect($existing->name)->toBe('Hand-curated Malifaux Name')
        ->and($existing->game_system)->toBe(GameSystemEnum::Both)
        ->and($existing->tosUnits)->toHaveCount(1);
});

it('overwrites existing package fields when --force is passed', function () {
    Package::factory()->create([
        'slug' => 'abyssinia-allegiance-box',
        'name' => 'Stale Name',
        'sku' => 'WYR40151',
        'game_system' => GameSystemEnum::Malifaux,
    ]);
    Unit::factory()->create(['name' => 'Prince Unathi']);

    Http::fake(fakeTosResponses([fakeTosProduct()]));

    $this->artisan('app:import-wyrd-tos-packages --skip-images --force')->assertSuccessful();

    $package = Package::where('slug', 'abyssinia-allegiance-box')->first();

    expect($package->name)->toBe('Abyssinia Allegiance Box')
        ->and($package->game_system)->toBe(GameSystemEnum::Both);
});

it('makes no database changes on a dry run', function () {
    Unit::factory()->create(['name' => 'Prince Unathi']);

    Http::fake(fakeTosResponses([fakeTosProduct()]));

    $this->artisan('app:import-wyrd-tos-packages --dry-run --skip-images')->assertSuccessful();

    expect(Package::count())->toBe(0);
});

it('retries on a 429 and succeeds once the rate limit clears', function () {
    Unit::factory()->create(['name' => 'Prince Unathi']);

    Http::fake([
        WYRD_TOS_URL => Http::sequence()
            ->push(null, 429, ['Retry-After' => '0'])
            ->push(['products' => [fakeTosProduct()]])
            ->push(['products' => []]),
    ]);

    $this->artisan('app:import-wyrd-tos-packages --skip-images')->assertSuccessful();

    expect(Package::where('slug', 'abyssinia-allegiance-box')->exists())->toBeTrue();
});

it('gives up after repeated 429s without crashing', function () {
    Http::fake([
        WYRD_TOS_URL => Http::response(null, 429, ['Retry-After' => '0']),
    ]);

    $this->artisan('app:import-wyrd-tos-packages --skip-images')->assertSuccessful();

    expect(Package::count())->toBe(0);
});
