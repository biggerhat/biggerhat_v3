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

it('serves no server errors on single-model detail pages', function () {
    $this->actingAs(User::factory()->create());

    $failures = [];
    $covered = 0;
    foreach (Route::getRoutes()->getRoutes() as $route) {
        $name = (string) $route->getName();
        if ($name === '' || ! in_array('GET', $route->methods(), true) || count($route->parameterNames()) !== 1
            || preg_match('/^(admin\.|horizon|telescope|log-viewer|ignition|debugbar|impersonate|scramble|api\.|storage\.)/', $name)) {
            continue;
        }
        $param = collect($route->signatureParameters(['subClass' => Illuminate\Database\Eloquent\Model::class]))->first();
        $class = $param?->getType()?->getName();
        if (! $class || ! method_exists($class, 'factory')) {
            continue;
        }
        try {
            $model = $class::factory()->create();
            $url = route($name, $model);
        } catch (Throwable) {
            continue; // factory needs relations, or the route key isn't derivable
        }
        $covered++;
        $status = $this->get($url)->getStatusCode();
        if ($status >= 500) {
            $failures[$name] = $status;
        }
    }

    expect($failures)->toBe([]);
    expect($covered)->toBeGreaterThan(10);
});

it('404s a character page when the character has no standard miniature', function () {
    $character = App\Models\Character::factory()->create();

    $this->get('/characters/'.$character->getRouteKey())->assertNotFound();
});
