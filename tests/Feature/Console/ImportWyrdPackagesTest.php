<?php

use App\Models\Package;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

const WYRD_URL = 'https://giveusyourmoneypleasethankyou-wyrd.com/products.json*';
const WYRD_RATE_LIMIT_COOLDOWN_KEY = 'wyrd-import:rate-limited-until';

// The rate-limit cooldown lives in cache and is shared across every Wyrd
// import command — clear it so one test's 429 doesn't skip the next test's
// fetch entirely.
beforeEach(fn () => Cache::flush());

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

it('gives up after repeated 429s without crashing, and sets a shared cooldown', function () {
    Http::fake([
        WYRD_URL => Http::response(null, 429, ['Retry-After' => '0']),
    ]);

    $this->artisan('app:import-wyrd-packages --skip-images')->assertSuccessful();

    expect(Package::count())->toBe(0);
    expect(Cache::get(WYRD_RATE_LIMIT_COOLDOWN_KEY))->not->toBeNull();
});

it('skips fetching entirely while a prior run\'s rate-limit cooldown is active', function () {
    Cache::put(WYRD_RATE_LIMIT_COOLDOWN_KEY, now()->addMinutes(20));
    Http::fake([WYRD_URL => Http::response(null, 500)]);

    $this->artisan('app:import-wyrd-packages --skip-images')->assertSuccessful();

    Http::assertNothingSent();
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
