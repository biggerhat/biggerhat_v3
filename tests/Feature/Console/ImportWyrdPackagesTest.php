<?php

use App\Models\Package;
use Illuminate\Support\Facades\Http;

const WYRD_URL = 'https://giveusyourmoneypleasethankyou-wyrd.com/products.json*';

function fakeMalifauxProduct(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'title' => 'Malifaux Fourth Edition: Sonnia Criid Crew',
        'handle' => 'sonnia-criid-crew',
        'body_html' => '<p>Some prose.</p><p><strong>Contents:</strong> Sonnia Criid</p>',
        'tags' => ['M4E'],
        'variants' => [['sku' => 'WYR24001', 'price' => '55.00']],
        'images' => [],
    ], $overrides);
}

it('retries on a 429 and succeeds once the rate limit clears', function () {
    Http::fake([
        WYRD_URL => Http::sequence()
            ->push(null, 429, ['Retry-After' => '0'])
            ->push(['products' => [fakeMalifauxProduct()]])
            ->push(['products' => []]),
    ]);

    $this->artisan('app:import-wyrd-packages --skip-images')->assertSuccessful();

    expect(Package::where('slug', 'malifaux-fourth-edition-sonnia-criid-crew')->exists())->toBeTrue();
});

it('gives up after repeated 429s without crashing', function () {
    Http::fake([
        WYRD_URL => Http::response(null, 429, ['Retry-After' => '0']),
    ]);

    $this->artisan('app:import-wyrd-packages --skip-images')->assertSuccessful();

    expect(Package::count())->toBe(0);
});

it('makes no database changes on a dry run', function () {
    Http::fake([
        WYRD_URL => Http::sequence()
            ->push(['products' => [fakeMalifauxProduct()]])
            ->push(['products' => []]),
    ]);

    $this->artisan('app:import-wyrd-packages --dry-run --skip-images')->assertSuccessful();

    expect(Package::count())->toBe(0);
});
