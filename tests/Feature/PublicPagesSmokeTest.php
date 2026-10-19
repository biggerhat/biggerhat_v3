<?php

use App\Models\User;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

/*
 * Every parameter-less public/user GET page must not 500, as a guest and as a
 * signed-in user, on an empty database. Redirects and 401/403/404 are fine
 * (auth gates, feature flags); server errors are not.
 */
function publicGetRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $r) => in_array('GET', $r->methods(), true) && $r->parameterNames() === [] && $r->getName())
        ->reject(fn (LaravelRoute $r) => str_starts_with($r->getName(), 'admin.')
            || str_starts_with($r->getName(), 'horizon.')
            || str_starts_with($r->getName(), 'telescope')
            || str_starts_with($r->getName(), 'log-viewer')
            || str_starts_with($r->getName(), 'ignition')
            || str_starts_with($r->getName(), 'debugbar')
            || str_starts_with($r->getName(), 'impersonate')
            || str_starts_with($r->getName(), 'scramble')
            || str_starts_with($r->getName(), 'storage.')
            // Streams / downloads / needs headless Chrome (Browsershot).
            || in_array($r->getName(), ['broadcasting.auth', 'tools.pdf.download', 'tools.bonanza_loot_deck.print', 'sanctum.csrf-cookie'], true))
        ->map(fn (LaravelRoute $r) => $r->getName())
        ->values()
        ->all();
}

it('serves no server errors to guests', function () {
    $failures = collect(publicGetRoutes())
        ->mapWithKeys(fn (string $name) => [$name => $this->get(route($name))->getStatusCode()])
        ->filter(fn (int $status) => $status >= 500)
        ->all();

    expect($failures)->toBe([]);
});

it('serves no server errors to a signed-in user', function () {
    $this->actingAs(User::factory()->create());

    $failures = collect(publicGetRoutes())
        ->mapWithKeys(fn (string $name) => [$name => $this->get(route($name))->getStatusCode()])
        ->filter(fn (int $status) => $status >= 500)
        ->all();

    expect($failures)->toBe([]);
});
