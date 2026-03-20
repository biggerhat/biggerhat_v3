<?php

namespace App\Http\Controllers;

use App\Enums\DeploymentEnum;
use App\Enums\FactionEnum;
use App\Enums\GameRoleEnum;
use App\Enums\GameStatusEnum;
use App\Enums\PoolSeasonEnum;
use App\Events\GamePlayerJoined;
use App\Models\Character;
use App\Models\CrewBuild;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Scheme;
use App\Models\Strategy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;
use Inertia\ResponseFactory;

class GameController extends Controller
{
    public function index(): Response|ResponseFactory
    {
        $userId = Auth::id();

        $activeGames = Game::whereHas('players', fn ($q) => $q->where('user_id', $userId))
            ->whereNotIn('status', [GameStatusEnum::Completed->value, GameStatusEnum::Abandoned->value])
            ->with(['players.user:id,name', 'strategy:id,name'])
            ->latest()
            ->get();

        $recentGames = Game::whereHas('players', fn ($q) => $q->where('user_id', $userId))
            ->whereIn('status', [GameStatusEnum::Completed->value, GameStatusEnum::Abandoned->value])
            ->with(['players.user:id,name', 'strategy:id,name', 'winner:id,name'])
            ->latest('completed_at')
            ->take(20)
            ->get();

        return inertia('Games/Index', [
            'active_games' => $activeGames,
            'recent_games' => $recentGames,
        ]);
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'encounter_size' => ['required', 'integer', 'min:20', 'max:100'],
            'season' => ['required', 'string'],
            'is_solo' => ['sometimes', 'boolean'],
        ]);

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
            'name' => $validated['name'],
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
        $userId = Auth::id();

        // Must be creator or player
        $isParticipant = $game->creator_id === $userId
            || $game->players()->where('user_id', $userId)->exists();

        if (! $isParticipant) {
            abort(403);
        }

        $eagerLoads = [
            'players.user:id,name',
            'strategy',
        ];

        // Only load crew/turn data when relevant
        if (in_array($game->status, [GameStatusEnum::InProgress, GameStatusEnum::Completed, GameStatusEnum::Abandoned])) {
            $eagerLoads[] = 'players.crewMembers';
            $eagerLoads[] = 'players.crewBuild';
            $eagerLoads[] = 'players.master.crewUpgrades';
        }
        if (in_array($game->status, [GameStatusEnum::InProgress, GameStatusEnum::Completed, GameStatusEnum::Abandoned])) {
            $eagerLoads[] = 'players.turns';
        }
        if (in_array($game->status, [GameStatusEnum::Completed, GameStatusEnum::Abandoned])) {
            $eagerLoads[] = 'winner:id,name';
        }

        $game->load($eagerLoads);

        $schemePoolOrder = $game->scheme_pool ?? [];
        $schemes = Scheme::whereIn('id', $schemePoolOrder)->get()
            ->sortBy(fn (Scheme $s) => array_search($s->id, $schemePoolOrder))
            ->values()
            ->map(fn (Scheme $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'slug' => $s->slug,
                'image_url' => $s->image_url,
                'prerequisite' => $s->prerequisite,
                'reveal' => $s->reveal,
                'scoring' => $s->scoring,
            ]);

        // Scenario editor data (only in setup phase for creator)
        $allStrategies = fn () => $game->status === GameStatusEnum::Setup && $game->creator_id === Auth::id()
            ? Strategy::forSeason($game->season)->orderBy('name')->get(['id', 'name', 'slug'])
            : [];
        $allSchemes = fn () => $game->status === GameStatusEnum::Setup && $game->creator_id === Auth::id()
            ? Scheme::forSeason($game->season)->orderBy('name')->get(['id', 'name', 'slug'])
            : [];
        $allDeployments = fn () => $game->status === GameStatusEnum::Setup && $game->creator_id === Auth::id()
            ? collect(DeploymentEnum::cases())->map(fn (DeploymentEnum $d) => ['value' => $d->value, 'label' => $d->label()])
            : [];

        // Data for setup steps (lazy-loaded based on current status)
        $factions = fn () => FactionEnum::buildDetails();
        $masters = function () use ($game) {
            $masterStatuses = [GameStatusEnum::MasterSelect, GameStatusEnum::CrewSelect];
            // Solo mode needs masters during faction_select too (for opponent setup after own faction)
            if ($game->is_solo) {
                $masterStatuses[] = GameStatusEnum::FactionSelect;
            }
            if (! in_array($game->status, $masterStatuses)) {
                return [];
            }

            $characters = Character::where('station', 'master')
                ->where('is_hidden', false)
                ->with('miniatures')
                ->orderBy('name')
                ->orderBy('title')
                ->get();

            // Include alternate leaders from crew upgrades (e.g., Wrath via On Tour)
            $alternateLeaderIds = \App\Models\Upgrade::forCrews()
                ->whereNotNull('hiring_rules')
                ->get()
                ->pluck('hiring_rules.alternate_leader_id')
                ->filter()
                ->unique();

            if ($alternateLeaderIds->isNotEmpty()) {
                $altLeaders = Character::whereIn('id', $alternateLeaderIds)
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

            return $grouped; // @phpstan-ignore return.type
        };
        $myCrews = function () use ($game) {
            if ($game->status !== GameStatusEnum::CrewSelect || ! Auth::check()) {
                return [];
            }

            $builds = CrewBuild::where('user_id', Auth::id())
                ->where('is_archived', false)
                ->with('master.keywords')
                ->orderBy('updated_at', 'desc')
                ->get();

            $allCharIds = $builds->flatMap(fn (CrewBuild $b) => $b->crew_data ?? [])->unique();
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

                // Leader
                $members[] = [
                    'display_name' => $master->display_name,
                    'faction' => $master->getRawOriginal('faction'),
                    'cost' => 0,
                    'effective_cost' => 0,
                    'category' => 'leader',
                ];

                // Totem
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
        };

        return inertia('Games/Show', [
            'game' => $game,
            'schemes' => $schemes,
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
            'factions' => $factions,
            'masters' => $masters,
            'my_crews' => $myCrews,
            'all_strategies' => $allStrategies,
            'all_schemes' => $allSchemes,
            'all_deployments' => $allDeployments,
            'tokens' => fn () => $game->status === GameStatusEnum::InProgress
                ? \App\Models\Token::orderBy('name')->get(['id', 'name', 'slug', 'description'])
                : [],
            'markers' => fn () => $game->status === GameStatusEnum::InProgress
                ? \App\Models\Marker::orderBy('name')->get(['id', 'name', 'slug'])
                : [],
            'next_schemes' => fn () => $this->getNextSchemesForPlayer($game, 1),
            'opponent_next_schemes' => fn () => $game->is_solo ? $this->getNextSchemesForPlayer($game, 2) : [],
        ]);
    }

    public function join(Game $game)
    {
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

        return redirect()->route('games.show', $game->uuid);
    }

    public function updateScenario(Request $request, Game $game)
    {
        // Only creator can edit, only in setup phase
        if ($game->creator_id !== Auth::id()) {
            abort(403);
        }
        if ($game->status !== GameStatusEnum::Setup) {
            return response()->json(['error' => 'Game already started'], 422);
        }

        $validated = $request->validate([
            'strategy_id' => ['nullable', 'exists:strategies,id'],
            'deployment' => ['nullable', 'string'],
            'scheme_pool' => ['required', 'array', 'min:3', 'max:3'],
            'scheme_pool.*' => ['integer', 'exists:schemes,id'],
        ]);

        $game->update([
            'strategy_id' => $validated['strategy_id'],
            'deployment' => $validated['deployment'],
            'scheme_pool' => $validated['scheme_pool'],
        ]);

        return response()->json(['success' => true]);
    }

    public function regenerateScenario(Game $game)
    {
        if ($game->creator_id !== Auth::id()) {
            abort(403);
        }
        if ($game->status !== GameStatusEnum::Setup) {
            return response()->json(['error' => 'Game already started'], 422);
        }

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
        if ($game->creator_id !== Auth::id()) {
            abort(403);
        }

        $game->delete();

        return redirect()->route('games.index')
            ->withMessage('Game deleted.');
    }

    private function getNextSchemesForPlayer(Game $game, int $slot): array
    {
        if ($game->status !== GameStatusEnum::InProgress) {
            return [];
        }
        /** @var GamePlayer|null $player */
        $player = $game->players()->where('slot', $slot)->first();
        if (! $player || ! $player->current_scheme_id) {
            return [];
        }
        $currentScheme = Scheme::find($player->current_scheme_id);
        if (! $currentScheme) {
            return [];
        }
        $nextIds = array_filter([
            $currentScheme->next_scheme_one_id,
            $currentScheme->next_scheme_two_id,
            $currentScheme->next_scheme_three_id,
        ]);
        if (empty($nextIds)) {
            return [];
        }

        return Scheme::whereIn('id', $nextIds)->get()->map(fn (Scheme $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'slug' => $s->slug,
            'image_url' => $s->image_url,
            'prerequisite' => $s->prerequisite,
            'reveal' => $s->reveal,
            'scoring' => $s->scoring,
        ])->toArray();
    }

    public function abandon(Game $game)
    {
        $userId = Auth::id();

        $isParticipant = $game->creator_id === $userId
            || $game->players()->where('user_id', $userId)->exists();

        if (! $isParticipant) {
            abort(403);
        }

        if ($game->status->isFinished()) {
            return redirect()->route('games.show', $game->uuid);
        }

        $game->update([
            'status' => GameStatusEnum::Abandoned,
            'completed_at' => now(),
        ]);

        return redirect()->route('games.index')
            ->withMessage('Game abandoned.');
    }
}
