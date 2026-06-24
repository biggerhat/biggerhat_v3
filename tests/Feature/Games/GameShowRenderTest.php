<?php

use App\Enums\GameFormatEnum;
use App\Enums\GameStatusEnum;
use App\Enums\PoolSeasonEnum;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\GameTurn;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * SSR smoke / render coverage for the three `Games/Show` render methods
 * (GameController::show / observe / summary).
 *
 * Model::shouldBeStrict() is enabled globally (AppServiceProvider), so simply
 * issuing a full GET against each render path makes missing-attribute access
 * throw — reproducing the "prod-only 500" class without running the Node SSR
 * bundle. The prod bugs were prod-only purely because no test exercised these
 * paths, not because tests lacked strict mode. These tests encode current
 * behavior and are the regression net for the buildShowProps refactor (Phase b).
 *
 * The Node SSR bundle itself (resources/js/ssr.ts) is not executed here — JS-side
 * render errors are out of scope for a PHP feature test; that path degrades
 * gracefully to client render and is covered by the Vite SSR build in CI.
 */

/**
 * Build a fully-populated 2-player game at the given status: scheme pool, a crew
 * member per player, and (for gameplay/post-game statuses) a real turn row whose
 * lean column-select is exercised under strict mode.
 *
 * @param  array<string, mixed>  $overrides
 */
function buildShowRenderGame(GameStatusEnum $status, array $overrides = []): Game
{
    $season = PoolSeasonEnum::GainingGrounds0;
    $isBonanza = ($overrides['format'] ?? null) === GameFormatEnum::BonanzaBrawl->value;

    $strategy = $isBonanza ? null : Strategy::factory()->create(['season' => $season]);
    $schemes = Scheme::factory()->count(3)->create(['season' => $season]);
    $schemeIds = $schemes->pluck('id')->toArray();

    $creator = User::factory()->create();
    $opponent = User::factory()->create();

    $game = Game::factory()->create(array_merge([
        'creator_id' => $creator->id,
        'season' => $season,
        'status' => $status,
        'strategy_id' => $strategy?->id,
        'scheme_pool' => $isBonanza ? null : $schemeIds,
        'started_at' => $status === GameStatusEnum::Setup ? null : now(),
        'completed_at' => in_array($status, [GameStatusEnum::Completed, GameStatusEnum::Abandoned]) ? now() : null,
        'current_turn' => $status === GameStatusEnum::InProgress ? 1 : 0,
    ], $overrides));

    $players = collect([$creator, $opponent])->map(fn (User $user, int $i) => GamePlayer::factory()->create([
        'game_id' => $game->id,
        'user_id' => $user->id,
        'slot' => $i + 1,
        'current_scheme_id' => $schemes[$i]->id,
        'scheme_pool' => $isBonanza ? null : $schemeIds,
    ]));

    // Crew members so the conditional crew eager loads + tracker-array mapping run.
    if (in_array($status, [GameStatusEnum::SchemeSelect, GameStatusEnum::InProgress, GameStatusEnum::Completed, GameStatusEnum::Abandoned])) {
        $players->each(fn (GamePlayer $p) => GameCrewMember::factory()->create([
            'game_id' => $game->id,
            'game_player_id' => $p->id,
        ]));
    }

    // A real turn row exercises the lean `players.turns:id,...` column select
    // under strict mode (accessing an unselected column would throw).
    if (in_array($status, [GameStatusEnum::InProgress, GameStatusEnum::Completed, GameStatusEnum::Abandoned])) {
        $players->each(fn (GamePlayer $p) => GameTurn::create([
            'game_id' => $game->id,
            'game_player_id' => $p->id,
            'turn_number' => 1,
            'scheme_id' => $schemes[0]->id,
            'points_scored' => 2,
        ]));
    }

    return $game->fresh();
}

$allStatuses = [
    'setup' => GameStatusEnum::Setup,
    'faction_select' => GameStatusEnum::FactionSelect,
    'master_select' => GameStatusEnum::MasterSelect,
    'crew_select' => GameStatusEnum::CrewSelect,
    'scheme_select' => GameStatusEnum::SchemeSelect,
    'in_progress' => GameStatusEnum::InProgress,
    'completed' => GameStatusEnum::Completed,
    'abandoned' => GameStatusEnum::Abandoned,
];

// ─── show() — participant render, every status ───

it('renders the show page without error for status', function (GameStatusEnum $status) {
    $game = buildShowRenderGame($status);
    $participant = $game->creator;

    $this->actingAs($participant)
        ->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Games/Show')
            ->has('game')
            ->has('schemes')
            ->where('is_observer', fn ($v) => $v === null || $v === false)
        );
})->with($allStatuses);

it('renders the show page for a Bonanza in-progress game', function () {
    $game = buildShowRenderGame(GameStatusEnum::InProgress, ['format' => GameFormatEnum::BonanzaBrawl->value, 'encounter_size' => 11]);

    $this->actingAs($game->creator)
        ->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Games/Show')->has('game'));
});

// ─── observe() — public render when observable ───

it('renders the observe page without error for status', function (GameStatusEnum $status) {
    $game = buildShowRenderGame($status, ['is_observable' => true]);

    // No auth — observe is public for observable games.
    $this->get(route('games.observe', $game->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Games/Show')
            ->where('is_observer', true)
        );
})->with($allStatuses);

it('hides unrevealed faction picks from observers during faction select', function () {
    $game = buildShowRenderGame(GameStatusEnum::FactionSelect, ['is_observable' => true]);

    $this->get(route('games.observe', $game->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('game.players', fn ($players) => collect($players)->every(fn ($p) => $p['faction'] === null))
        );
});

it('404s the observe page for non-observable games', function () {
    $game = buildShowRenderGame(GameStatusEnum::InProgress, ['is_observable' => false]);

    $this->get(route('games.observe', $game->uuid))->assertNotFound();
});

// ─── summary() — completed / abandoned only ───

it('renders the summary page for completed and abandoned games', function (GameStatusEnum $status) {
    $game = buildShowRenderGame($status);

    $this->get(route('games.summary', $game->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Games/Show')
            ->where('is_observer', true)
            ->has('game')
        );
})->with([
    'completed' => GameStatusEnum::Completed,
    'abandoned' => GameStatusEnum::Abandoned,
]);

it('404s the summary page for in-flight games', function (GameStatusEnum $status) {
    $game = buildShowRenderGame($status);

    $this->get(route('games.summary', $game->uuid))->assertNotFound();
})->with([
    'setup' => GameStatusEnum::Setup,
    'faction_select' => GameStatusEnum::FactionSelect,
    'in_progress' => GameStatusEnum::InProgress,
]);

/*
 * ─── Per-context payload divergences ───
 * These lock the subtle value/key differences between the three renders that a
 * naive unification could silently regress (audit #4). They characterize the
 * CURRENT behavior; the buildShowProps refactor (Phase b) must keep them green.
 */

it('exposes editor + self-only props on the participant view', function () {
    $game = buildShowRenderGame(GameStatusEnum::InProgress);

    $this->actingAs($game->creator)
        ->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('is_observer', false)
            ->where('factions', fn ($v) => count($v) > 0)      // editor data populated
            ->where('observer_scheme_intel', null)             // self never gets observer intel
            ->has('bonanza_crew_upgrades')                     // self-only keys present
            ->has('campaign_context')
            ->has('loot_card_catalog')
        );
});

it('strips editor data and self-only keys on the observer view', function () {
    $game = buildShowRenderGame(GameStatusEnum::InProgress, ['is_observable' => true]);

    $this->get(route('games.observe', $game->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('is_observer', true)
            ->where('factions', [])                            // editor data stripped
            ->where('masters', [])
            ->where('all_reachable_schemes', [])
            ->where('observer_scheme_intel', fn ($v) => $v !== null) // observer gets its own intel
            ->has('loot_card_catalog')                         // observer keeps loot catalog
            ->missing('bonanza_crew_upgrades')                 // self-only keys omitted
            ->missing('campaign_context')
        );
});

it('omits the loot catalog on the summary view', function () {
    $game = buildShowRenderGame(GameStatusEnum::Completed);

    $this->get(route('games.summary', $game->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('is_observer', true)
            ->where('factions', [])
            ->where('observer_scheme_intel', null)
            ->missing('loot_card_catalog')                     // summary drops it (latent omission)
            ->missing('bonanza_crew_upgrades')
            ->missing('campaign_context')
        );
});

it('populates summary current_schemes for abandoned games where the self view is empty', function () {
    $game = buildShowRenderGame(GameStatusEnum::Abandoned);

    // Self view of an abandoned game returns no current schemes…
    $this->actingAs($game->creator)
        ->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('current_schemes', []));

    // …but the public summary lists them (turn + held schemes).
    $this->get(route('games.summary', $game->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('current_schemes', fn ($v) => count($v) > 0));
});
