<?php

use App\Enums\PermissionEnum;
use App\Models\BattleReport;
use App\Models\Campaign\CampaignAftermath;
use App\Models\Campaign\CampaignGame;
use App\Models\Campaign\CampaignInvitation;
use App\Models\Character;
use App\Models\CollectionModel;
use App\Models\CrewBuild;
use App\Models\CustomCharacter;
use App\Models\CustomUpgrade;
use App\Models\Friendship;
use App\Models\Miniature;
use App\Models\SavedSearch;
use App\Models\TabletopLayout;
use App\Models\TOS\Company;
use App\Models\TOS\Garrison;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

/**
 * Every route that changes one person's data (a wishlist, a custom card, a crew, a collection, a report...), called by a signed-in user
 * who does not own it. None may succeed. Reading is not swept here: a share link or a public page is meant to be open.
 *
 * Games and campaigns are in StrangerAccessSweepTest, tournaments in Tournament/AuthorizationSweepTest.
 *
 * The requests are empty, so a route that validates before it checks ownership answers with a validation redirect; that counts as a
 * refusal because nothing was changed. (Ownership checks that sit after validation are what the per-feature tests cover.)
 *
 * A new route with one of these models in its path is picked up on its own. A new model that is not in $subjects below is
 * not, so add it when a feature gets user-owned data.
 */
beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
});

/** Route parameter name => a row belonging to someone else. */
function strangerSubjects(User $owner): array
{
    return [
        'wishlist' => fn () => Wishlist::create(['user_id' => $owner->id, 'name' => 'Theirs']),
        'savedSearch' => fn () => SavedSearch::create(['user_id' => $owner->id, 'name' => 'Theirs', 'query_params' => ['q' => 'x']]),
        'collectionModel' => fn () => CollectionModel::create(['user_id' => $owner->id, 'miniature_id' => Miniature::factory()->create(['character_id' => Character::factory()->create()->id])->id]),
        'crewBuild' => fn () => CrewBuild::factory()->create(['user_id' => $owner->id]),
        'layout' => fn () => TabletopLayout::factory()->create(['user_id' => $owner->id]),
        'report' => fn () => BattleReport::factory()->create(['user_id' => $owner->id]),
        'customCharacter' => fn () => CustomCharacter::create([
            'user_id' => $owner->id, 'name' => 'Theirs', 'display_name' => 'Theirs', 'slug' => 'theirs', 'faction' => 'guild', 'station' => 'master',
            'health' => 6, 'defense' => 5, 'willpower' => 5, 'speed' => 5, 'base' => 30, 'share_code' => 'theirs-1',
        ]),
        'customUpgrade' => fn () => CustomUpgrade::create([
            'user_id' => $owner->id, 'name' => 'Theirs', 'display_name' => 'Theirs', 'slug' => 'theirs', 'domain' => 'character', 'faction' => 'guild',
            'content_blocks' => [], 'share_code' => 'theirs-2',
        ]),
        'friendship' => fn () => Friendship::factory()->create(),
        'company' => fn () => Company::factory()->create(),
        'garrison' => fn () => Garrison::factory()->create(),
        'invitation' => fn () => CampaignInvitation::factory()->create(),
        'campaignGame' => fn () => CampaignGame::factory()->create(),
        'aftermath' => fn () => CampaignAftermath::factory()->create(),
    ];
}

/** @return list<LaravelRoute> */
function writeRoutesFor(string $param): array
{
    $found = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        if ($route->parameterNames() !== [$param]) {
            continue;
        }
        $uri = $route->uri();
        if (str_starts_with($uri, 'api/') || str_starts_with($uri, 'admin') || str_contains($uri, 'telescope') || str_contains($uri, 'log-viewer')) {
            continue;
        }
        // Public by design: copying a shared card needs only the share code.
        if (str_contains($uri, '/shared/')) {
            continue;
        }
        $methods = array_values(array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']));
        if ($methods !== []) {
            $found[] = $route;
        }
    }

    return $found;
}

it('refuses every write on someone else\'s data to a signed-in stranger', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create(['email_verified_at' => now()]);
    $stranger->givePermissionTo(PermissionEnum::UseCampaignMode->value);
    $this->actingAs($stranger);
    // A rate limiter answers 429 before the ownership check runs, which would hide a missing check; the limits have their own tests.
    $this->withoutMiddleware(ThrottleRequests::class);

    $leaks = [];
    $checked = 0;

    foreach (strangerSubjects($owner) as $param => $make) {
        $routes = writeRoutesFor($param);
        expect($routes)->not->toBeEmpty("No write routes found for {{$param}}: was the parameter renamed?");

        $row = $make();

        foreach ($routes as $route) {
            $method = array_values(array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']))[0];
            $uri = '/'.str_replace('{'.$param.'}', (string) $row->getRouteKey(), $route->uri());

            $status = $this->call($method, $uri)->getStatusCode();
            $checked++;

            if ($status === 429) {
                $leaks[] = "429 (not actually checked) $method {$route->uri()}";
            } elseif ($status < 400 && ! (in_array($status, [301, 302], true) && session('errors')?->any())) {
                $leaks[] = "$status $method {$route->uri()}";
            }
        }
    }

    expect($checked)->toBeGreaterThan(40);
    expect($leaks)->toBe([]);
});

it('lets the owner through the same addresses, so the refusals above are real', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $wishlist = Wishlist::create(['user_id' => $owner->id, 'name' => 'Mine']);

    // An empty rename is a validation redirect for the owner, but not a 403 or 404.
    $status = $this->actingAs($owner)->call('PUT', '/wishlists/'.$wishlist->getRouteKey())->getStatusCode();
    expect($status)->not->toBeIn([403, 404]);

    $stranger = User::factory()->create(['email_verified_at' => now()]);
    expect($this->actingAs($stranger)->call('DELETE', '/wishlists/'.$wishlist->getRouteKey())->getStatusCode())->toBeIn([403, 404]);
    expect(Wishlist::find($wishlist->id))->not->toBeNull();
});
