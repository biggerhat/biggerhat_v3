<?php

namespace App\Http\Controllers\Game;

use App\Enums\DeploymentEnum;
use App\Enums\FactionEnum;
use App\Enums\GameFormatEnum;
use App\Enums\GamePlayModeEnum;
use App\Enums\GameRoleEnum;
use App\Enums\MessageTypeEnum;
use App\Enums\PoolSeasonEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Games\Logged\StoreLoggedGameRequest;
use App\Http\Requests\Games\Logged\UpdateLoggedGameRequest;
use App\Models\Character;
use App\Models\CrewBuild;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\User;
use App\Support\Game\LoggedGameRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * Games played and tracked somewhere else and entered by hand afterwards (Game::origin = logged). They have no play screen: this
 * controller is all of their life: the form to enter and edit one, its summary page, and the opponent's confirmation when the
 * logger linked their account. The game rows themselves are the same ones the tracker writes (App\Support\Game\LoggedGameRecorder).
 */
class LoggedGameController extends Controller
{
    public function __construct(private readonly LoggedGameRecorder $recorder) {}

    public function create(Request $request): Response|ResponseFactory
    {
        return inertia('Games/Log', [
            'options' => $this->options($request->user()),
            'game' => null,
        ]);
    }

    public function store(StoreLoggedGameRequest $request): RedirectResponse
    {
        $game = $this->recorder->create($request->user(), $request->validated());

        return redirect()->route('games.logged.show', $game)->withMessage('Game logged.', null, MessageTypeEnum::success);
    }

    public function edit(Request $request, Game $game): Response|ResponseFactory
    {
        $this->ownLogged($request, $game);

        return inertia('Games/Log', [
            'options' => $this->options($request->user()),
            'game' => $this->formShape($game),
        ]);
    }

    public function update(UpdateLoggedGameRequest $request, Game $game): RedirectResponse
    {
        $this->ownLogged($request, $game);
        $this->recorder->update($game, $request->validated());

        return redirect()->route('games.logged.show', $game)->withMessage('Game updated.', null, MessageTypeEnum::success);
    }

    public function destroy(Request $request, Game $game): RedirectResponse
    {
        $this->ownLogged($request, $game);
        $game->delete();

        return redirect()->route('games.index')->withMessage('Logged game deleted.', null, MessageTypeEnum::success);
    }

    /** The summary of a logged game: for its owner, the player invited as the opponent, and anyone at all when the owner made it public. */
    public function show(Request $request, Game $game): Response|ResponseFactory
    {
        abort_unless($game->isLogged(), 404);
        $game->load(['players.user:id,name,slug', 'players.linkedUser:id,name,slug', 'players.master:id,name,display_name', 'strategy:id,name', 'creator:id,name,slug']);
        $viewer = $request->user();
        abort_unless($this->mayView($viewer, $game), 404);

        $me = $game->players->firstWhere('slot', 1);
        $them = $game->players->firstWhere('slot', 2);
        $isOwner = $viewer !== null && $game->creator_id === $viewer->id;
        $invited = $viewer !== null && $them?->linked_user_id === $viewer->id;

        $turns = $game->turns()->with('scheme:id,name')->orderBy('turn_number')->get();
        $crews = $game->crewMembers()->orderBy('sort_order')->get(['id', 'game_player_id', 'display_name', 'station', 'cost', 'is_killed']);

        return inertia('Games/LoggedShow', [
            'game' => [
                'uuid' => $game->uuid,
                'name' => $game->name,
                'format' => $game->format->value,
                'format_label' => $game->format->label(),
                'played_on' => $game->completed_at?->toDateString(),
                'play_mode' => $game->play_mode?->label(),
                'season' => $game->season->label(),
                'encounter_size' => $game->encounter_size,
                'strategy' => $game->strategy?->name,
                'deployment' => $game->deployment?->label(),
                'notes' => $game->notes,
                'venue' => $game->venue,
                'is_public' => $game->is_public,
                'is_tie' => $game->is_tie,
                'winner_slot' => $game->winner_slot,
                'logged_by' => $game->creator->name,
            ],
            'players' => $game->players->map(fn (GamePlayer $p) => [
                'slot' => $p->slot,
                'name' => $p->slot === 1 ? $game->creator->name : ($p->opponent_name ?: 'Opponent'),
                'profile' => $p->slot === 1 ? $game->creator->slug : ($p->link_status === 'confirmed' ? $p->user?->slug : null),
                'faction' => $p->getRawOriginal('faction'),
                'master' => $p->master_name,
                'role' => $p->role?->value,
                'total_points' => $p->total_points,
                'soulstone_pool' => $p->soulstone_pool,
                'link_status' => $p->link_status,
            ])->values(),
            'turns' => $turns->map(fn ($t) => [
                'turn' => $t->turn_number,
                'slot' => $game->players->firstWhere('id', $t->game_player_id)?->slot,
                'strategy_points' => $t->strategy_points,
                'scheme_points' => $t->scheme_points,
                'scheme' => $t->scheme?->name,
                'scheme_action' => $t->scheme_action,
                'notes' => $t->notes,
            ])->values(),
            'crews' => $crews->map(fn ($m) => [
                'slot' => $game->players->firstWhere('id', $m->game_player_id)?->slot,
                'name' => $m->display_name,
                'station' => $m->station,
                'cost' => $m->cost,
                'killed' => (bool) $m->is_killed,
            ])->values(),
            'can_edit' => $isOwner,
            'can_confirm' => $invited && $them?->link_status === 'pending',
            'is_guest' => $viewer === null,
        ]);
    }

    /** The invited opponent says it was them: the game becomes part of their record. */
    public function confirmLink(Request $request, Game $game): RedirectResponse
    {
        $theirs = $this->pendingLinkFor($request, $game);

        DB::transaction(function () use ($game, $theirs, $request) {
            $theirs->update(['user_id' => $request->user()->id, 'link_status' => 'confirmed']);
            // the result belongs to that account too: a win by the opponent now points at them
            if ($game->winner_slot === 2) {
                $game->update(['winner_id' => $request->user()->id]);
            }
        });

        return redirect()->route('games.logged.show', $game)->withMessage('Added to your stats.', null, MessageTypeEnum::success);
    }

    /** The invited opponent says it was not them: nothing is added to their record. */
    public function declineLink(Request $request, Game $game): RedirectResponse
    {
        $theirs = $this->pendingLinkFor($request, $game);
        $theirs->update(['link_status' => 'declined']);

        return redirect()->route('games.index')->withMessage('Declined. It will not appear in your stats.', null, MessageTypeEnum::success);
    }

    /** A small lookup for the form's model and master pickers. */
    public function characters(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['characters' => []]);
        }

        $query = Character::standard()->with('characteristics:id,name');
        $query->where(fn ($b) => $b->whereSearch('display_name', $q)->orWhereSearch('name', $q));
        if ($request->query('station') === 'master') {
            $query->where('station', 'master');
        }
        if ($faction = $request->query('faction')) {
            $query->where(fn ($b) => $b->where('faction', $faction)->orWhere('second_faction', $faction));
        }

        return response()->json([
            'characters' => $query->orderBy('display_name')->limit(20)->get()->map(fn (Character $c) => [
                'id' => $c->id,
                'name' => $c->display_name,
                'faction' => $c->getRawOriginal('faction'),
                'station' => $c->station?->value,
                'cost' => $c->cost,
            ])->values(),
        ]);
    }

    private function ownLogged(Request $request, Game $game): void
    {
        abort_unless($game->isLogged(), 404);
        abort_unless($game->creator_id === $request->user()->id, 404);
    }

    private function pendingLinkFor(Request $request, Game $game): GamePlayer
    {
        abort_unless($game->isLogged(), 404);
        /** @var GamePlayer|null $theirs */
        $theirs = $game->players()->where('slot', 2)->where('linked_user_id', $request->user()->id)->where('link_status', 'pending')->first();
        abort_unless($theirs !== null, 404);

        return $theirs;
    }

    private function mayView(?User $viewer, Game $game): bool
    {
        if ($game->is_public) {
            return true;
        }
        if ($viewer === null) {
            return false;
        }

        return $game->creator_id === $viewer->id
            || $game->players->contains(fn (GamePlayer $p) => $p->user_id === $viewer->id || ($p->linked_user_id === $viewer->id && $p->link_status !== 'declined'));
    }

    /**
     * What the form offers.
     *
     * @return array<string, mixed>
     */
    private function options(User $user): array
    {
        return [
            'formats' => collect(GameFormatEnum::cases())->map(fn (GameFormatEnum $f) => [
                'value' => $f->value,
                'label' => $f->label(),
                'default_encounter_size' => $f->defaultEncounterSize(),
                'uses_scenario' => $f->usesScenario(),
            ])->values(),
            'seasons' => collect(PoolSeasonEnum::casesNewestFirst())->map(fn (PoolSeasonEnum $s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            'default_season' => PoolSeasonEnum::defaultSeason()->value,
            'play_modes' => collect(GamePlayModeEnum::cases())->map(fn (GamePlayModeEnum $m) => ['value' => $m->value, 'label' => $m->label()])->values(),
            'factions' => collect(FactionEnum::cases())->map(fn (FactionEnum $f) => ['value' => $f->value, 'label' => $f->label()])->values(),
            'deployments' => collect(DeploymentEnum::cases())->map(fn (DeploymentEnum $d) => ['value' => $d->value, 'label' => $d->label()])->values(),
            'roles' => collect(GameRoleEnum::cases())->map(fn (GameRoleEnum $r) => ['value' => $r->value, 'label' => ucfirst($r->value)])->values(),
            'strategies' => Strategy::query()->orderBy('name')->get(['id', 'name']),
            'schemes' => Scheme::query()->orderBy('name')->get(['id', 'name']),
            'crew_builds' => CrewBuild::query()->where('user_id', $user->id)->where('is_archived', false)->latest('updated_at')->limit(50)
                ->get(['id', 'name', 'faction', 'master_id', 'crew_data'])
                ->map(fn (CrewBuild $b) => $this->crewBuildOption($b))->values(),
            'today' => now()->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    private function crewBuildOption(CrewBuild $build): array
    {
        $ids = array_map('intval', $build->crew_data ?? []);
        $characters = Character::query()->whereIn('id', array_values(array_unique([...$ids, $build->master_id])))->get()->keyBy('id');

        return [
            'id' => $build->id,
            'name' => $build->name,
            'faction' => $build->getRawOriginal('faction'),
            'master' => $characters->get($build->master_id)?->only(['id', 'display_name']),
            'models' => collect($ids)->map(fn (int $id) => $characters->get($id))->filter()->map(fn (Character $c) => [
                'character_id' => $c->id,
                'name' => $c->display_name,
                'cost' => $c->cost,
                'station' => $c->station?->value,
            ])->values(),
        ];
    }

    /**
     * A logged game in the shape the form edits.
     *
     * @return array<string, mixed>
     */
    private function formShape(Game $game): array
    {
        $game->load(['players.linkedUser:id,name', 'turns', 'crewMembers']);
        $side = function (GamePlayer $p) use ($game): array {
            return [
                'faction' => $p->getRawOriginal('faction'),
                'master_id' => $p->master_id,
                'master_name' => $p->master_name,
                'role' => $p->role?->value,
                'total_points' => $p->total_points,
                'soulstone_pool' => $p->soulstone_pool,
                'crew_build_id' => $p->crew_build_id,
                'opponent_name' => $p->opponent_name,
                'linked_user_id' => $p->linked_user_id,
                'linked_user_name' => $p->linkedUser?->name,
                'link_status' => $p->link_status,
                'models' => $game->crewMembers->where('game_player_id', $p->id)->sortBy('sort_order')->map(fn ($m) => [
                    'character_id' => $m->character_id,
                    'name' => $m->display_name,
                    'killed' => (bool) $m->is_killed,
                    'cost' => $m->cost,
                    'hiring_category' => $m->hiring_category,
                ])->values(),
            ];
        };
        $turnRow = fn ($t) => $t === null ? [] : [
            'strategy_points' => $t->strategy_points,
            'scheme_points' => $t->scheme_points,
            'scheme_id' => $t->scheme_id,
            'scheme_action' => $t->scheme_action,
            'strategy_bonus_used' => (bool) $t->strategy_bonus_used,
            'notes' => $t->notes,
        ];
        $me = $game->players->firstWhere('slot', 1);
        $them = $game->players->firstWhere('slot', 2);

        return [
            'uuid' => $game->uuid,
            'format' => $game->format->value,
            'played_on' => $game->completed_at?->toDateString(),
            'play_mode' => $game->play_mode?->value,
            'season' => $game->season->value,
            'encounter_size' => $game->encounter_size,
            'name' => $game->name,
            'notes' => $game->notes,
            'venue' => $game->venue,
            'is_public' => $game->is_public,
            'strategy_id' => $game->strategy_id,
            'deployment' => $game->deployment?->value,
            'scheme_pool' => $game->scheme_pool ?? [],
            'result' => $game->is_tie ? 'tie' : ($game->winner_slot === 1 ? 'win' : 'loss'),
            'me' => $side($me),
            'opponent' => $side($them),
            'turns' => $game->turns->groupBy('turn_number')->map(fn ($rows, $n) => [
                'turn_number' => (int) $n,
                'me' => $turnRow($rows->firstWhere('game_player_id', $me->id)),
                'opponent' => $turnRow($rows->firstWhere('game_player_id', $them->id)),
            ])->sortBy('turn_number')->values(),
        ];
    }
}
