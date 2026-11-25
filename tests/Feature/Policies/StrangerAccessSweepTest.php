<?php

use App\Enums\Campaign\CampaignStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignPlayer;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

/**
 * Every route that acts on someone else's game or campaign, called by a signed-in user who is not part of it.
 * None of them may succeed: the answer must be 403 or 404 (or a redirect to sign-in). New routes are picked up on their own, so a
 * contributor who adds an endpoint and forgets the ownership check fails here.
 *
 * Routes with a second model in the path (a prompt, a crew) are skipped: the stranger would get a 404 for the missing second model
 * whether or not the check exists, which proves nothing.
 */

/** @return list<LaravelRoute> */
function routesWithOnly(string $param): array
{
    $found = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        $methods = array_diff($route->methods(), ['HEAD']);
        if (! preg_grep('/^(GET|POST|PUT|PATCH|DELETE)$/', $methods)) {
            continue;
        }
        if ($route->parameterNames() !== [$param]) {
            continue;
        }
        $uri = $route->uri();
        if (str_starts_with($uri, 'api/') || str_starts_with($uri, 'admin') || str_contains($uri, 'telescope') || str_contains($uri, 'log-viewer')) {
            continue;
        }
        $found[] = $route;
    }

    return $found;
}

/**
 * Whether the route let the stranger through. A redirect counts as a refusal only when it carries validation errors (the request was
 * rejected before it did anything); the join page is open by design and turns a game that has started away with a message.
 */
function strangerGotThrough($test, LaravelRoute $route, string $key): ?string
{
    $uri = '/'.str_replace('{'.$route->parameterNames()[0].'}', $key, $route->uri());
    $method = array_values(array_diff($route->methods(), ['HEAD']))[0];

    $status = $test->call($method, $uri)->getStatusCode();

    if ($status === 429) {
        return "429 (a rate limiter answered, so the check was not reached) $method {$route->uri()}";
    }
    if ($status >= 400) {
        return null;
    }
    if (in_array($status, [301, 302], true)) {
        if (str_ends_with($route->uri(), '/join') || session('errors')?->any()) {
            return null;
        }
    }

    return "$status $method {$route->uri()}";
}

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
});

it('keeps a stranger out of every route on someone else\'s game', function () {
    $owner = User::factory()->create();
    $game = Game::factory()->inProgress()->create(['creator_id' => $owner->id, 'is_observable' => false]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $owner->id, 'slot' => 1]);

    // Control: the same address works for the owner, so a refusal below is the check, not a bad address.
    $this->actingAs($owner)->get('/games/'.$game->uuid)->assertOk();

    $stranger = User::factory()->create();
    $this->actingAs($stranger);
    $this->withoutMiddleware(ThrottleRequests::class);

    $routes = routesWithOnly('game');
    expect(count($routes))->toBeGreaterThan(40);

    $leaks = [];
    foreach ($routes as $route) {
        $leak = strangerGotThrough($this, $route, $game->uuid);
        if ($leak !== null) {
            $leaks[] = $leak;
        }
    }

    expect($leaks)->toBe([]);
});

it('keeps a stranger out of every route on someone else\'s campaign', function () {
    $owner = User::factory()->create();
    $owner->givePermissionTo(PermissionEnum::UseCampaignMode->value);
    $campaign = Campaign::factory()->create(['organizer_user_id' => $owner->id, 'status' => CampaignStatusEnum::Active]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $owner->id]);

    // Control: the same address works for the organizer, so a refusal below is the check, not a bad address.
    $this->actingAs($owner)->get('/campaigns/'.$campaign->getRouteKey())->assertOk();

    $stranger = User::factory()->create();
    $stranger->givePermissionTo(PermissionEnum::UseCampaignMode->value);
    $this->actingAs($stranger);
    $this->withoutMiddleware(ThrottleRequests::class);

    $routes = routesWithOnly('campaign');
    expect(count($routes))->toBeGreaterThan(5);

    $leaks = [];
    foreach ($routes as $route) {
        $leak = strangerGotThrough($this, $route, (string) $campaign->getRouteKey());
        if ($leak !== null) {
            $leaks[] = $leak;
        }
    }

    expect($leaks)->toBe([]);
});
