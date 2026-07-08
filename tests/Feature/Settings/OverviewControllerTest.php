<?php

use App\Enums\Campaign\CampaignStatusEnum;
use App\Enums\GameStatusEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignPlayer;
use App\Models\CrewBuild;
use App\Models\CustomCharacter;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\SavedSearch;
use App\Models\TOS\Company;
use App\Models\TOS\Garrison;
use App\Models\Tournament;
use App\Models\TournamentRsvp;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;

it('requires authentication', function () {
    $this->get(route('overview'))->assertRedirect(route('login'));
});

it('renders the overview with zeroed counts for a fresh user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('overview'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Settings/Overview')
            ->where('active_games', 0)
            ->where('collection.malifaux_miniatures', 0)
            ->where('collection.malifaux_packages', 0)
            ->where('collection.tos_unit_sculpts', 0)
            ->where('wishlists.count', 0)
            ->where('wishlists.items', 0)
            ->where('crew_builds', 0)
            ->has('campaigns', 0)
            ->has('upcoming_tournaments', 0)
            ->where('is_supporter', false)
            ->where('tos_companies', 0)
            ->where('tos_garrisons', 0)
            ->where('custom_cards', 0)
            ->where('saved_searches.malifaux', 0)
            ->where('saved_searches.tos', 0)
        );
});

it('renders correct counts once the user has activity', function () {
    $user = User::factory()->create();

    $game = Game::factory()->create(['status' => GameStatusEnum::InProgress]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'slot' => 1]);

    $campaign = Campaign::factory()->create(['status' => CampaignStatusEnum::Active, 'current_week' => 3, 'length_weeks' => 8]);
    CampaignPlayer::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    // A campaign the user is NOT part of should not appear.
    Campaign::factory()->create(['status' => CampaignStatusEnum::Active]);

    // A campaign the user IS part of but isn't Active should not count.
    $planningCampaign = Campaign::factory()->create(['status' => CampaignStatusEnum::Planning]);
    CampaignPlayer::factory()->create(['campaign_id' => $planningCampaign->id, 'user_id' => $user->id]);

    $wishlist = Wishlist::create(['user_id' => $user->id, 'name' => 'My Wishlist']);
    WishlistItem::create([
        'wishlist_id' => $wishlist->id,
        'wishlistable_type' => \App\Models\Package::class,
        'wishlistable_id' => \App\Models\Package::factory()->create()->id,
    ]);

    CrewBuild::factory()->create(['user_id' => $user->id, 'is_archived' => false]);
    CrewBuild::factory()->create(['user_id' => $user->id, 'is_archived' => true]);

    Company::factory()->create(['user_id' => $user->id]);
    Garrison::factory()->create(['user_id' => $user->id]);

    CustomCharacter::create([
        'user_id' => $user->id,
        'share_code' => 'abc123xyz987',
        'name' => 'Test Character',
        'display_name' => 'Test Character',
        'slug' => 'test-character',
        'faction' => 'guild',
        'health' => 6,
        'defense' => 5,
        'willpower' => 6,
        'speed' => 5,
    ]);

    SavedSearch::create(['user_id' => $user->id, 'name' => 'My Malifaux Search', 'query_params' => [], 'game_system' => 'malifaux']);
    SavedSearch::create(['user_id' => $user->id, 'name' => 'My TOS Search', 'query_params' => [], 'game_system' => 'tos']);

    $this->actingAs($user)
        ->get(route('overview'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Settings/Overview')
            ->where('active_games', 1)
            ->where('wishlists.count', 1)
            ->where('wishlists.items', 1)
            ->where('crew_builds', 1)
            ->has('campaigns', 1)
            ->where('campaigns.0.name', $campaign->name)
            ->where('campaigns.0.current_week', 3)
            ->where('campaigns.0.length_weeks', 8)
            ->where('tos_companies', 1)
            ->where('tos_garrisons', 1)
            ->where('custom_cards', 1)
            ->where('saved_searches.malifaux', 1)
            ->where('saved_searches.tos', 1)
        );
});

it('lists upcoming tournaments the user has RSVP\'d for, excluding past and completed ones', function () {
    $user = User::factory()->create();

    $upcoming = Tournament::factory()->create([
        'name' => 'Upcoming RSVP Tournament',
        'event_date' => now()->addWeek()->toDateString(),
        'status' => \App\Enums\TournamentStatusEnum::Registration,
    ]);
    TournamentRsvp::create(['tournament_id' => $upcoming->id, 'user_id' => $user->id]);

    // RSVP'd but already completed — should not appear even though it's in the future on paper.
    $completed = Tournament::factory()->completed()->create(['event_date' => now()->addWeek()->toDateString()]);
    TournamentRsvp::create(['tournament_id' => $completed->id, 'user_id' => $user->id]);

    // RSVP'd but in the past — should not appear.
    $past = Tournament::factory()->create(['event_date' => now()->subWeek()->toDateString()]);
    TournamentRsvp::create(['tournament_id' => $past->id, 'user_id' => $user->id]);

    // Upcoming but the user never RSVP'd — should not appear.
    Tournament::factory()->create(['event_date' => now()->addWeek()->toDateString()]);

    $this->actingAs($user)
        ->get(route('overview'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Settings/Overview')
            ->has('upcoming_tournaments', 1)
            ->where('upcoming_tournaments.0.name', 'Upcoming RSVP Tournament')
            ->where('upcoming_tournaments.0.uuid', $upcoming->uuid)
        );
});

it('resolves the deferred achievements payload', function () {
    $user = User::factory()->create();

    $game = Game::factory()->create(['status' => GameStatusEnum::Completed, 'is_solo' => false, 'is_tie' => false, 'winner_id' => $user->id]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'slot' => 1]);

    $manifest = public_path('build/manifest.json');
    $version = file_exists($manifest) ? hash_file('xxh128', $manifest) : '';

    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'Settings/Overview',
            'X-Inertia-Partial-Data' => 'achievements',
        ])
        ->get(route('overview'));

    $response->assertOk();
    $payload = $response->json();
    expect($payload['props']['achievements']['total_games'])->toBe(1)
        ->and($payload['props']['achievements']['wins'])->toBe(1)
        ->and($payload['props']['achievements']['badges'])->not->toBeEmpty();
});
