<?php

namespace App\Http\Controllers;

use App\Enums\DeploymentEnum;
use App\Enums\FactionEnum;
use App\Enums\GameRoleEnum;
use App\Enums\GameStatusEnum;
use App\Enums\PoolSeasonEnum;
use App\Events\GamePlayerJoined;
use App\Events\GameStatusChanged;
use App\Http\Requests\Games\StoreGameRequest;
use App\Http\Requests\Games\UpdateGameSettingsRequest;
use App\Http\Requests\Games\UpdateScenarioRequest;
use App\Models\Character;
use App\Models\CrewBuild;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\GameTurn;
use App\Models\Scheme;
use App\Models\Strategy;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;
use Inertia\ResponseFactory;

class GameController extends Controller
{
    public function index(): Response|ResponseFactory
    {
        $userId = Auth::id();

        return inertia('Games/Index', [
            'active_games' => Game::forUser($userId)->active()
                ->with(['players.user:id,name', 'strategy:id,name'])
                ->latest()
                ->get(),
            'recent_games' => Game::forUser($userId)->completed()
                ->with(['players.user:id,name', 'strategy:id,name', 'winner:id,name'])
                ->latest('completed_at')
                ->take(20)
                ->get(),
            'observable_games' => $this->observableGamesList(),
        ]);
    }

    public function publicIndex(): Response|ResponseFactory
    {
        return inertia('Games/Index', [
            'active_games' => [],
            'recent_games' => [],
            'observable_games' => $this->observableGamesList(),
        ]);
    }

    /** Shared list of recently-active public games (index + publicIndex). */
    private function observableGamesList()
    {
        return Game::observable()
            ->with(['players.user:id,name', 'strategy:id,name', 'winner:id,name'])
            ->latest('updated_at')
            ->take(10)
            ->get();
    }

    public function create(): Response|ResponseFactory
    {
        $seasons = collect(PoolSeasonEnum::cases())->map(fn (PoolSeasonEnum $s) => [
            'value' => $s->value,
            'label' => $s->label(),
        ]);

        return inertia('Games/Create', [
            'seasons' => $seasons,
            'encounter_sizes' => [35, 40, 50],
        ]);
    }

    public function store(StoreGameRequest $request)
    {
        $validated = $request->validated();

        $seasonEnum = PoolSeasonEnum::from($validated['season']);

        // Generate scenario
        $strategies = Strategy::forSeason($seasonEnum)->get();
        $schemes = Scheme::forSeason($seasonEnum)->get();
        $deployments = DeploymentEnum::cases();

        $strategy = $strategies->isNotEmpty() ? $strategies->random() : null;
        $deployment = $deployments[array_rand($deployments)];
        $schemePool = $schemes->count() >= 3
            ? $schemes->random(3)->pluck('id')->toArray()
            : $schemes->pluck('id')->toArray();

        $isSolo = filter_var($request->input('is_solo', false), FILTER_VALIDATE_BOOLEAN);

        $game = Game::create([
            'name' => $validated['name'] ?? null,
            'encounter_size' => $validated['encounter_size'],
            'season' => $seasonEnum->value,
            'strategy_id' => $strategy?->id,
            'deployment' => $deployment->value,
            'scheme_pool' => $schemePool,
            'status' => $isSolo ? GameStatusEnum::FactionSelect : GameStatusEnum::Setup,
            'started_at' => $isSolo ? now() : null,
            'creator_id' => Auth::id(),
            'is_solo' => $isSolo,
        ]);

        // Randomly assign roles
        $roles = collect([GameRoleEnum::Attacker->value, GameRoleEnum::Defender->value])->shuffle();

        // Creator is player 1
        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => Auth::id(),
            'slot' => 1,
            'role' => $isSolo ? $roles[0] : null,
        ]);

        if ($isSolo) {
            // Create opponent player (no user) for solo mode
            GamePlayer::create([
                'game_id' => $game->id,
                'user_id' => null,
                'slot' => 2,
                'opponent_name' => 'Opponent',
                'role' => $roles[1],
            ]);
        }

        return redirect()->route('games.show', $game->uuid);
    }

    public function show(Game $game): Response|ResponseFactory
    {
        $this->authorize('view', $game);
        $userId = Auth::id();

        $eagerLoads = [
            'players.user:id,name',
            'strategy',
        ];

        // Load crew members for scheme select (both players need to see crews) and gameplay
        if (in_array($game->status, [GameStatusEnum::SchemeSelect, GameStatusEnum::InProgress, GameStatusEnum::Completed, GameStatusEnum::Abandoned])) {
            $eagerLoads[] = 'players.crewMembers';
            $eagerLoads[] = 'players.crewBuild';
            $eagerLoads[] = 'players.master.crewUpgrades';
        }
        if ($game->status === GameStatusEnum::InProgress) {
            // During gameplay, only load scoring data — exclude large crew_snapshot JSON
            $eagerLoads[] = 'players.turns:id,game_player_id,turn_number,strategy_points,scheme_points,scheme_id,scheme_action,scheme_notes,next_scheme_id,points_scored';
        } elseif (in_array($game->status, [GameStatusEnum::Completed, GameStatusEnum::Abandoned])) {
            // Summary view needs full turn data including snapshots
            $eagerLoads[] = 'players.turns';
        }
        if (in_array($game->status, [GameStatusEnum::Completed, GameStatusEnum::Abandoned])) {
            $eagerLoads[] = 'winner:id,name';
        }

        $game->load($eagerLoads);
        $this->ensureCrewReferences($game);

        // ─── Simultaneous reveal for faction + master ───
        // During FactionSelect / MasterSelect, each player's choice must be
        // hidden from the opponent until BOTH have locked in (game advances
        // to the next status). Without this, the first player to submit has
        // their pick leaked via the Inertia payload.
        if (! $game->is_solo) {
            $mySlot = $game->players->firstWhere('user_id', $userId)?->slot;

            if ($game->status === GameStatusEnum::FactionSelect) {
                foreach ($game->players as $player) {
                    if ($player->slot === $mySlot) {
                        continue;
                    }
                    $player->setAttribute('faction', null);
                }
            }

            if ($game->status === GameStatusEnum::MasterSelect) {
                foreach ($game->players as $player) {
                    if ($player->slot === $mySlot) {
                        continue;
                    }
                    $player->setAttribute('master_name', null);
                    $player->setAttribute('master_id', null);
                }
            }
        }

        // Append image_url on strategy for the show page
        $game->strategy?->append('image_url');

        $schemePoolOrder = $game->scheme_pool ?? [];
        // Load all schemes for the season — small table, avoids cache misses on deep chains
        $schemeCache = Scheme::forSeason($game->season)->get()->keyBy('id');
        $poolSchemes = $schemeCache->filter(fn (Scheme $s) => in_array($s->id, $schemePoolOrder));

        // Build full reachable scheme tree: pool + all follow-ups (recursively).
        // Uses a seen-set + index cursor instead of array_shift (O(n) per shift → O(n²) total).
        $seen = array_flip($schemePoolOrder);
        $queue = array_values($schemePoolOrder);
        for ($qi = 0; $qi < count($queue); $qi++) {
            $scheme = $schemeCache->get($queue[$qi]);
            if (! $scheme) {
                continue;
            }
            foreach ([$scheme->next_scheme_one_id, $scheme->next_scheme_two_id, $scheme->next_scheme_three_id] as $nextId) {
                if ($nextId && ! isset($seen[$nextId])) {
                    $seen[$nextId] = true;
                    $queue[] = $nextId;
                }
            }
        }
        $reachableIds = collect(array_keys($seen));

        $schemes = $poolSchemes
            ->sortBy(fn (Scheme $s) => array_search($s->id, $schemePoolOrder))
            ->values()
            ->map(fn (Scheme $s) => $s->toTrackerArray());

        // All reachable schemes (pool + follow-up chains) for lookups
        $allReachableSchemes = $schemeCache->filter(fn (Scheme $s) => $reachableIds->contains($s->id))
            ->values()
            ->map(fn (Scheme $s) => $s->toTrackerArray());

        // Scenario editor data (available before gameplay starts, for creator)
        $canEditScenario = fn () => Auth::user()?->can('updateScenario', $game) ?? false;
        $allStrategies = fn () => $canEditScenario()
            ? Strategy::forSeason($game->season)->orderBy('name')->get()->map(fn (Strategy $s) => ['id' => $s->id, 'name' => $s->name, 'slug' => $s->slug, 'image_url' => $s->image_url])
            : [];
        $allSchemes = fn () => $canEditScenario()
            ? Scheme::forSeason($game->season)->orderBy('name')->get()->map(fn (Scheme $s) => ['id' => $s->id, 'name' => $s->name, 'slug' => $s->slug, 'image_url' => $s->image_url])
            : [];
        $allDeployments = fn () => $canEditScenario()
            ? collect(DeploymentEnum::cases())->map(fn (DeploymentEnum $d) => ['value' => $d->value, 'label' => $d->label(), 'image_url' => $d->imageUrl()])
            : [];

        return inertia('Games/Show', [
            'game' => $game,
            'schemes' => $schemes,
            'all_reachable_schemes' => $allReachableSchemes,
            'deployment' => $game->deployment ? (function () use ($game) {
                /** @var DeploymentEnum $d */
                $d = $game->deployment;

                return [
                    'value' => $d->value,
                    'label' => $d->label(),
                    'description' => $d->description(),
                    'image_url' => $d->imageUrl(),
                ];
            })() : null,
            'factions' => fn () => FactionEnum::buildDetails(),
            'masters' => fn () => $this->buildMastersProp($game),
            'my_crews' => fn () => $this->buildMyCrewsProp($game),
            'all_strategies' => $allStrategies,
            'all_schemes' => $allSchemes,
            'all_deployments' => $allDeployments,
            'all_markers' => fn () => in_array($game->status, [GameStatusEnum::InProgress, GameStatusEnum::SchemeSelect])
                ? \App\Models\Marker::orderBy('name')->get(['id', 'name', 'slug'])
                : [],
            'tokens' => fn () => $game->status === GameStatusEnum::InProgress
                ? \App\Models\Token::orderBy('name')->get(['id', 'name', 'slug', 'description'])
                : [],
            'character_upgrades' => fn () => $game->status === GameStatusEnum::InProgress
                ? \App\Models\Upgrade::standard()->forCharacters()->orderBy('name')->get(['id', 'name', 'slug', 'front_image', 'back_image', 'type', 'plentiful'])
                : [],
            'current_schemes' => fn () => $this->buildCurrentSchemesProp($game),
            'opponent_scheme_intel' => fn () => $this->buildOpponentSchemeIntel($game, $schemeCache),
            // next_schemes and opponent_next_schemes read directly from stored scheme_pool
            'next_schemes' => function () use ($game, $schemeCache) {
                if ($game->status !== GameStatusEnum::InProgress) {
                    return [];
                }
                $myPlayer = $game->players->first(fn ($p) => $p->user_id === Auth::id());
                if (! $myPlayer) {
                    return [];
                }

                return self::schemesFromCache($schemeCache, $myPlayer->scheme_pool ?? []);
            },
            'opponent_next_schemes' => function () use ($game, $schemeCache) {
                if (! $game->is_solo || $game->status !== GameStatusEnum::InProgress) {
                    return [];
                }
                $opponent = $game->players->firstWhere('slot', 2);
                if (! $opponent) {
                    return [];
                }

                return self::schemesFromCache($schemeCache, $opponent->scheme_pool ?? []);
            },
            'observer_scheme_intel' => fn () => null,
            'starting_crews' => fn () => $this->getStartingCrews($game),
            'is_observer' => false,
        ]);
    }

    public function join(Game $game)
    {
        // Require login — redirect back to this join URL after auth
        if (! Auth::check()) {
            session()->put('url.intended', route('games.join', $game->uuid));

            return redirect()->route('login')
                ->with('message', 'Please log in or create an account to join this game.')
                ->with('messageType', 'warning');
        }

        $userId = Auth::id();

        // Solo games cannot be joined
        if ($game->is_solo) {
            return redirect()->route('games.index');
        }

        // Already in game? Go to show
        if ($game->players()->where('user_id', $userId)->exists()) {
            return redirect()->route('games.show', $game->uuid);
        }

        // Creator can't join as player 2
        if ($game->creator_id === $userId) {
            return redirect()->route('games.show', $game->uuid);
        }

        // Game must be in setup and have an open slot
        if ($game->status !== GameStatusEnum::Setup || $game->isFull()) {
            return redirect()->route('games.index')
                ->withMessage('This game is no longer accepting players.');
        }

        // Randomly assign attacker/defender to both players
        $roles = collect([GameRoleEnum::Attacker->value, GameRoleEnum::Defender->value])->shuffle();
        $game->players()->where('slot', 1)->update(['role' => $roles[0]]);

        $player = GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $userId,
            'slot' => 2,
            'role' => $roles[1],
        ]);

        // Advance to faction select now that both players are in
        $game->update([
            'status' => GameStatusEnum::FactionSelect,
            'started_at' => now(),
        ]);

        broadcast(new GamePlayerJoined($game, $player->load('user')))->toOthers();
        broadcast(new GameStatusChanged($game))->toOthers();

        return redirect()->route('games.show', $game->uuid);
    }

    public function updateScenario(UpdateScenarioRequest $request, Game $game)
    {
        $validated = $request->validated();

        $game->update([
            'strategy_id' => $validated['strategy_id'],
            'deployment' => $validated['deployment'],
            'scheme_pool' => $validated['scheme_pool'],
        ]);

        return response()->json(['success' => true]);
    }

    public function regenerateScenario(Game $game)
    {
        $this->authorize('updateScenario', $game);

        $seasonEnum = $game->season;
        $strategies = Strategy::forSeason($seasonEnum)->get();
        $schemes = Scheme::forSeason($seasonEnum)->get();
        $deployments = DeploymentEnum::cases();

        $game->update([
            'strategy_id' => $strategies->isNotEmpty() ? $strategies->random()->id : null,
            'deployment' => $deployments[array_rand($deployments)]->value,
            'scheme_pool' => $schemes->count() >= 3
                ? $schemes->random(3)->pluck('id')->toArray()
                : $schemes->pluck('id')->toArray(),
        ]);

        return redirect()->route('games.show', $game->uuid);
    }

    public function destroy(Game $game)
    {
        $this->authorize('view', $game);
        $userId = Auth::id();
        $player = $game->players()->where('user_id', $userId)->first();

        // Solo games or games still in setup (no opponent yet): hard delete
        if ($game->is_solo || $game->players()->count() <= 1) {
            $game->delete();

            return redirect()->route('games.index')
                ->withMessage('Game deleted.');
        }

        // Duel game: soft-hide for this player
        if ($player) {
            $player->update(['hidden_at' => now()]);
        }

        // If all players have hidden, hard delete
        $allHidden = $game->players()->whereNull('hidden_at')->count() === 0;
        if ($allHidden) {
            $game->delete();
        }

        return redirect()->route('games.index')
            ->withMessage('Game removed from your list.');
    }

    public function observe(Game $game): Response|ResponseFactory
    {
        if (! $game->is_observable) {
            abort(404);
        }

        $eagerLoads = [
            'players.user:id,name',
            'strategy',
        ];

        if (in_array($game->status, [GameStatusEnum::InProgress, GameStatusEnum::Completed, GameStatusEnum::Abandoned])) {
            $eagerLoads[] = 'players.crewMembers';
            $eagerLoads[] = 'players.crewBuild';
            $eagerLoads[] = 'players.master.crewUpgrades';
            $eagerLoads[] = 'players.turns';
        }
        if (in_array($game->status, [GameStatusEnum::Completed, GameStatusEnum::Abandoned])) {
            $eagerLoads[] = 'winner:id,name';
        }

        $game->load($eagerLoads);
        $this->ensureCrewReferences($game);

        // Observers should never see unrevealed faction/master picks.
        if (! $game->is_solo) {
            if ($game->status === GameStatusEnum::FactionSelect) {
                foreach ($game->players as $player) {
                    $player->setAttribute('faction', null);
                }
            }
            if ($game->status === GameStatusEnum::MasterSelect) {
                foreach ($game->players as $player) {
                    $player->setAttribute('master_name', null);
                    $player->setAttribute('master_id', null);
                }
            }
        }

        $game->strategy?->append('image_url');

        $schemePoolOrder = $game->scheme_pool ?? [];
        $schemes = Scheme::whereIn('id', $schemePoolOrder)->get()
            ->sortBy(fn (Scheme $s) => array_search($s->id, $schemePoolOrder))
            ->values()
            ->map(fn (Scheme $s) => $s->toTrackerArray());

        return inertia('Games/Show', [
            'game' => $game,
            'schemes' => $schemes,
            'all_reachable_schemes' => fn () => [],
            'deployment' => $game->deployment ? (function () use ($game) {
                /** @var DeploymentEnum $d */
                $d = $game->deployment;

                return ['value' => $d->value, 'label' => $d->label(), 'description' => $d->description(), 'image_url' => $d->imageUrl()];
            })() : null,
            'factions' => fn () => [],
            'masters' => fn () => [],
            'my_crews' => fn () => [],
            'all_strategies' => fn () => [],
            'all_schemes' => fn () => [],
            'all_deployments' => fn () => [],
            'tokens' => fn () => in_array($game->status, [GameStatusEnum::InProgress, GameStatusEnum::Completed])
                ? \App\Models\Token::orderBy('name')->get(['id', 'name', 'slug', 'description'])
                : [],
            'character_upgrades' => fn () => [],
            'all_markers' => fn () => [],
            'current_schemes' => function () use ($game) {
                if (! in_array($game->status, [GameStatusEnum::InProgress, GameStatusEnum::Completed])) {
                    return [];
                }
                $schemeIds = $game->players->pluck('current_scheme_id')->filter()->unique()->values();
                if ($game->status === GameStatusEnum::Completed) {
                    $turnSchemeIds = GameTurn::where('game_id', $game->id)
                        ->whereNotNull('scheme_id')
                        ->pluck('scheme_id')
                        ->unique();
                    $schemeIds = $schemeIds->merge($turnSchemeIds)->unique()->values();
                }

                return Scheme::whereIn('id', $schemeIds)->get()->map(fn (Scheme $s) => $s->toTrackerArray())->toArray();
            },
            'opponent_scheme_intel' => fn () => null,
            'next_schemes' => fn () => [],
            'opponent_next_schemes' => fn () => [],
            'observer_scheme_intel' => fn () => $this->buildObserverSchemeIntel($game),
            'starting_crews' => fn () => $this->getStartingCrews($game),
            'is_observer' => true,
        ]);
    }

    public function summary(Game $game): Response|ResponseFactory
    {
        if (! in_array($game->status, [GameStatusEnum::Completed, GameStatusEnum::Abandoned])) {
            abort(404);
        }

        $game->load([
            'players.user:id,name',
            'players.crewMembers',
            'players.crewBuild',
            'players.master.crewUpgrades',
            'players.turns',
            'strategy',
            'winner:id,name',
        ]);
        $this->ensureCrewReferences($game);

        $game->strategy?->append('image_url');

        $schemePoolOrder = $game->scheme_pool ?? [];
        $schemes = Scheme::whereIn('id', $schemePoolOrder)->get()
            ->sortBy(fn (Scheme $s) => array_search($s->id, $schemePoolOrder))
            ->values()
            ->map(fn (Scheme $s) => $s->toTrackerArray());

        $schemeIds = $game->players->pluck('current_scheme_id')->filter()->unique()->values();
        $turnSchemeIds = GameTurn::where('game_id', $game->id)
            ->whereNotNull('scheme_id')
            ->pluck('scheme_id')
            ->unique();
        $allSchemeIds = $schemeIds->merge($turnSchemeIds)->unique()->values();
        $currentSchemes = Scheme::whereIn('id', $allSchemeIds)->get()->map(fn (Scheme $s) => $s->toTrackerArray())->toArray();

        return inertia('Games/Show', [
            'game' => $game,
            'schemes' => $schemes,
            'all_reachable_schemes' => fn () => [],
            'deployment' => $game->deployment ? (function () use ($game) {
                /** @var DeploymentEnum $d */
                $d = $game->deployment;

                return ['value' => $d->value, 'label' => $d->label(), 'description' => $d->description(), 'image_url' => $d->imageUrl()];
            })() : null,
            'factions' => fn () => [],
            'masters' => fn () => [],
            'my_crews' => fn () => [],
            'all_strategies' => fn () => [],
            'all_schemes' => fn () => [],
            'all_deployments' => fn () => [],
            'tokens' => fn () => \App\Models\Token::orderBy('name')->get(['id', 'name', 'slug', 'description']),
            'character_upgrades' => fn () => [],
            'current_schemes' => $currentSchemes,
            'opponent_scheme_intel' => fn () => null,
            'next_schemes' => fn () => [],
            'opponent_next_schemes' => fn () => [],
            'observer_scheme_intel' => fn () => null,
            'all_markers' => fn () => [],
            'starting_crews' => $this->getStartingCrews($game),
            'is_observer' => true,
        ]);
    }

    public function toggleObservable(Game $game)
    {
        $this->authorize('update', $game);

        $game->update(['is_observable' => ! $game->is_observable]);

        return back();
    }

    public function updateSettings(UpdateGameSettingsRequest $request, Game $game)
    {
        $validated = $request->validated();

        $game->update(['settings' => array_merge($game->settings ?? [], $validated['settings'])]);

        return back();
    }

    private function getStartingCrews(Game $game): array
    {
        if (! in_array($game->status, [GameStatusEnum::Completed, GameStatusEnum::Abandoned])) {
            return [];
        }

        $startingCrews = [];
        /** @var GamePlayer $player */
        foreach ($game->players as $player) {
            $startingCrews[$player->slot] = $player->crewMembers
                ->where('is_summoned', false)
                ->sortBy('sort_order')
                ->map(fn (GameCrewMember $m) => [
                    'display_name' => $m->display_name,
                    'faction' => $m->getRawOriginal('faction'),
                    'cost' => $m->cost,
                    'hiring_category' => $m->hiring_category,
                    'front_image' => $m->front_image,
                    'back_image' => $m->back_image,
                ])
                ->values()
                ->toArray();
        }

        return $startingCrews;
    }

    /**
     * Ensure all crew builds in the game have valid pre-computed references.
     */
    private function ensureCrewReferences(Game $game): void
    {
        if (! in_array($game->status, [GameStatusEnum::InProgress, GameStatusEnum::Completed, GameStatusEnum::Abandoned])) {
            return;
        }

        foreach ($game->players as $player) {
            /** @var GamePlayer $player */
            /** @var CrewBuild|null $crewBuild */
            $crewBuild = $player->crewBuild;
            if ($crewBuild) {
                $crewBuild->ensureReferences();
            }
        }
    }

    public function abandon(Game $game)
    {
        $this->authorize('view', $game);

        if ($game->status->isFinished()) {
            return redirect()->route('games.show', $game->uuid);
        }

        $game->update([
            'status' => GameStatusEnum::Abandoned,
            'completed_at' => now(),
        ]);

        if (! $game->is_solo) {
            broadcast(new GameStatusChanged($game))->toOthers();
        }

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('games.index')
            ->withMessage('Game abandoned.');
    }

    /**
     * Master characters (plus alternate leaders granted by crew upgrades)
     * grouped by name with their titles, for the MasterSelect UI.
     */
    private function buildMastersProp(Game $game): array
    {
        $masterStatuses = [GameStatusEnum::MasterSelect, GameStatusEnum::CrewSelect];
        // Solo mode needs masters during faction_select too (for opponent setup after own faction).
        if ($game->is_solo) {
            $masterStatuses[] = GameStatusEnum::FactionSelect;
        }
        if (! in_array($game->status, $masterStatuses)) {
            return [];
        }

        $characters = Character::standard()->where('station', 'master')
            ->where('is_hidden', false)
            ->with('miniatures')
            ->orderBy('name')
            ->orderBy('title')
            ->get();

        // Include alternate leaders granted by crew upgrades (e.g., Wrath via On Tour).
        $alternateLeaderIds = \App\Models\Upgrade::standard()->forCrews()
            ->whereNotNull('hiring_rules')
            ->pluck('hiring_rules')
            ->map(fn ($rules) => $rules['alternate_leader_id'] ?? null)
            ->filter()
            ->unique();

        if ($alternateLeaderIds->isNotEmpty()) {
            $altLeaders = Character::standard()->whereIn('id', $alternateLeaderIds)
                ->where('is_hidden', false)
                ->with('miniatures')
                ->get();

            foreach ($altLeaders as $alt) {
                if (! $characters->contains('id', $alt->id)) {
                    $characters->push($alt);
                }
            }
        }

        $characters = $characters->sortBy('name')->sortBy('title');

        $grouped = [];
        foreach ($characters->groupBy('name') as $name => $group) {
            /** @var Character $first */
            $first = $group->first();
            $grouped[] = [
                'name' => $name,
                'faction' => $first->getRawOriginal('faction'),
                'second_faction' => $first->getRawOriginal('second_faction'),
                'front_image' => $first->miniatures->first()?->front_image,
                'is_alternate_leader' => $alternateLeaderIds->contains($first->id),
                'titles' => $group->map(fn (Character $c) => [
                    'id' => $c->id,
                    'display_name' => $c->display_name,
                    'title' => $c->title,
                ])->values(),
            ];
        }

        return $grouped;
    }

    /**
     * Current user's saved CrewBuilds shaped for the CrewSelect list — each
     * preview carries a summary of members and computed soulstone pool so the
     * UI can render without drilling into relations.
     */
    private function buildMyCrewsProp(Game $game): array
    {
        if (! in_array($game->status, [GameStatusEnum::MasterSelect, GameStatusEnum::CrewSelect]) || ! Auth::check()) {
            return [];
        }

        $builds = CrewBuild::where('user_id', Auth::id())
            ->where('is_archived', false)
            ->with('master.keywords')
            ->orderBy('updated_at', 'desc')
            ->get();

        // One big character load — avoids N+1 per-build when shaping members below.
        $totemIds = $builds->map(fn (CrewBuild $b) => $b->master?->has_totem_id)->filter()->unique(); // @phpstan-ignore nullsafe.neverNull
        $allCharIds = $builds->flatMap(fn (CrewBuild $b) => $b->crew_data ?? [])->unique()->merge($totemIds)->unique();
        $characters = Character::with('keywords', 'characteristics')
            ->whereIn('id', $allCharIds)->get()->keyBy('id');

        $result = [];
        foreach ($builds as $b) {
            $master = $b->master;
            if (! $master) {
                continue;
            }

            $leaderKeywords = $master->keywords->pluck('slug')->toArray();
            $members = [];
            $totalSpent = 0;
            $ookCount = 0;

            $members[] = [
                'display_name' => $master->display_name,
                'faction' => $master->getRawOriginal('faction'),
                'cost' => 0,
                'effective_cost' => 0,
                'category' => 'leader',
            ];

            if ($master->has_totem_id) {
                $totem = $characters->get($master->has_totem_id) ?? Character::find($master->has_totem_id);
                if ($totem) {
                    $totemCount = max(1, $totem->count ?? 1);
                    for ($i = 0; $i < $totemCount; $i++) {
                        $members[] = [
                            'display_name' => $totem->display_name,
                            'faction' => $totem->getRawOriginal('faction'),
                            'cost' => 0,
                            'effective_cost' => 0,
                            'category' => 'totem',
                        ];
                    }
                }
            }

            foreach ($b->crew_data ?? [] as $id) {
                $char = $characters->get($id);
                if (! $char) {
                    continue;
                }

                $sharesKeyword = $char->keywords->pluck('slug')->intersect($leaderKeywords)->isNotEmpty();
                $isVersatile = $char->characteristics->pluck('name')->map(fn ($n) => strtolower($n))->contains('versatile');
                $category = $sharesKeyword ? 'in-keyword' : ($isVersatile ? 'versatile' : 'ook');
                $effectiveCost = $category === 'ook' ? ($char->cost + 1) : $char->cost;
                $totalSpent += $effectiveCost;
                if ($category === 'ook') {
                    $ookCount++;
                }

                $members[] = [
                    'display_name' => $char->display_name,
                    'faction' => $char->getRawOriginal('faction'),
                    'cost' => $char->cost,
                    'effective_cost' => $effectiveCost,
                    'category' => $category,
                ];
            }

            $remaining = $b->encounter_size - $totalSpent;
            $result[] = [
                'id' => $b->id,
                'name' => $b->name,
                'share_code' => $b->share_code,
                'faction' => $b->getRawOriginal('faction'),
                'master_name' => $master->display_name,
                'encounter_size' => $b->encounter_size,
                'crew_count' => count($b->crew_data ?? []) + 1,
                'total_spent' => $totalSpent,
                'soulstone_pool' => $remaining > 6 ? 6 : max(0, $remaining),
                'ook_count' => $ookCount,
                'is_over_budget' => $totalSpent > $game->encounter_size,
                'members' => $members,
            ];
        }

        return $result;
    }

    /**
     * What the current user can infer about the opponent's scheme state:
     * the last revealed scheme (scored/discarded), a possible-schemes pool
     * derived from follow-ups of that reveal, and a turn-by-turn history
     * with held turns attributed retroactively once the scheme reveals.
     */
    private function buildOpponentSchemeIntel(Game $game, \Illuminate\Support\Collection $schemeCache): ?array
    {
        if ($game->status !== GameStatusEnum::InProgress) {
            return null;
        }

        $userId = Auth::id();
        /** @var GamePlayer|null $opponent */
        $opponent = $game->is_solo
            ? $game->players->firstWhere('slot', 2)
            : $game->players->first(fn ($p) => $p->user_id !== $userId);

        if (! $opponent) {
            return null;
        }

        // Possible schemes come from the opponent's LAST REVEALED scheme's
        // follow-ups — NOT their stored scheme_pool, which reflects an
        // as-yet-hidden next-scheme pick. Before any reveal: the shared game pool.
        $lastRevealedTurn = $opponent->turns
            ->sortByDesc('turn_number')
            ->first(fn (GameTurn $t) => in_array($t->scheme_action, ['scored', 'discarded']));

        if ($lastRevealedTurn && $lastRevealedTurn->scheme_id) {
            $revealedScheme = $schemeCache->get($lastRevealedTurn->scheme_id);
            $poolIds = $revealedScheme
                ? array_values(array_filter([
                    $revealedScheme->next_scheme_one_id,
                    $revealedScheme->next_scheme_two_id,
                    $revealedScheme->next_scheme_three_id,
                ]))
                : ($game->scheme_pool ?? []);
            if (empty($poolIds)) {
                $poolIds = $game->scheme_pool ?? [];
            }
        } else {
            $poolIds = $game->scheme_pool ?? [];
        }

        $possible = self::schemesFromCache($schemeCache, $poolIds);

        // Scheme history: walk turns in order, buffering held turns. When a
        // scored/discarded turn arrives it retroactively reveals the scheme
        // the buffered held turns belonged to. Trailing held turns stay hidden.
        $schemeHistory = [];
        $heldBuffer = [];
        foreach ($opponent->turns->sortBy('turn_number') as $t) {
            /** @var GameTurn $t */
            if ($t->scheme_action === null) {
                continue;
            }

            if ($t->scheme_action === 'held') {
                $heldBuffer[] = $t->turn_number;

                continue;
            }

            $scheme = $t->scheme_id ? $schemeCache->get($t->scheme_id) : null;
            $schemeName = $scheme->name ?? 'Unknown';
            foreach ($heldBuffer as $heldTurn) {
                $schemeHistory[] = [
                    'turn_number' => $heldTurn,
                    'scheme_id' => $t->scheme_id,
                    'scheme_name' => $schemeName,
                    'scheme_action' => 'held',
                ];
            }
            $heldBuffer = [];

            $schemeHistory[] = [
                'turn_number' => $t->turn_number,
                'scheme_id' => $t->scheme_id,
                'scheme_name' => $schemeName,
                'scheme_action' => $t->scheme_action,
            ];
        }

        $lastRevealed = collect($schemeHistory)
            ->last(fn ($h) => in_array($h['scheme_action'], ['scored', 'discarded']));

        return [
            'last_revealed' => $lastRevealed,
            'possible_schemes' => $possible,
            'scheme_history' => $schemeHistory,
        ];
    }

    /**
     * Schemes scored by each player this game plus any currently-held scheme.
     * Completed games include every scheme referenced across all turns, even
     * ones that left the pool via follow-up chains.
     */
    private function buildCurrentSchemesProp(Game $game): array
    {
        if ($game->status === GameStatusEnum::Completed) {
            $turnSchemeIds = GameTurn::where('game_id', $game->id)
                ->whereNotNull('scheme_id')
                ->pluck('scheme_id')
                ->unique()
                ->values();
            $playerSchemeIds = $game->players->pluck('current_scheme_id')->filter();
            $allIds = $turnSchemeIds->merge($playerSchemeIds)->unique()->values();

            return Scheme::whereIn('id', $allIds)->get()->map(fn (Scheme $s) => $s->toTrackerArray())->toArray();
        }

        if ($game->status !== GameStatusEnum::InProgress) {
            return [];
        }

        $schemeIds = $game->players->pluck('current_scheme_id')->filter()->unique()->values();

        return Scheme::whereIn('id', $schemeIds)->get()->map(fn (Scheme $s) => $s->toTrackerArray())->toArray();
    }

    /** Filter a keyed scheme cache by id list and shape each for the tracker. */
    private static function schemesFromCache(\Illuminate\Support\Collection $schemeCache, array $ids): array
    {
        return $schemeCache->filter(fn (Scheme $s) => in_array($s->id, $ids))
            ->map(fn (Scheme $s) => $s->toTrackerArray())
            ->values()
            ->toArray();
    }

    /**
     * Per-player possible-schemes hints for public observers: each slot gets
     * the follow-ups of their last revealed scheme, plus whether they scored
     * (revealing the held scheme) on the current turn.
     */
    private function buildObserverSchemeIntel(Game $game): ?array
    {
        if ($game->status !== GameStatusEnum::InProgress) {
            return null;
        }

        $schemeCache = Scheme::forSeason($game->season)->get()->keyBy('id');
        $result = [];

        foreach ($game->players as $player) {
            $lastRevealedTurn = $player->turns
                ->sortByDesc('turn_number')
                ->first(fn ($t) => in_array($t->scheme_action, ['scored', 'discarded']));

            if ($lastRevealedTurn && $lastRevealedTurn->scheme_id) {
                $revealedScheme = $schemeCache->get($lastRevealedTurn->scheme_id);
                $possibleIds = $revealedScheme ? array_values(array_filter([
                    $revealedScheme->next_scheme_one_id,
                    $revealedScheme->next_scheme_two_id,
                    $revealedScheme->next_scheme_three_id,
                ])) : [];
            } else {
                $possibleIds = $game->scheme_pool ?? [];
            }

            $currentTurnRecord = $player->turns->firstWhere('turn_number', $game->current_turn);
            $revealedThisTurn = $currentTurnRecord && $currentTurnRecord->scheme_action === 'scored';

            $result[$player->slot] = [
                'possible_schemes' => self::schemesFromCache($schemeCache, $possibleIds),
                'revealed_scheme_id' => $revealedThisTurn ? $currentTurnRecord->scheme_id : null,
                'last_scored_turn' => $revealedThisTurn ? $currentTurnRecord->turn_number : null,
            ];
        }

        return $result;
    }
}
