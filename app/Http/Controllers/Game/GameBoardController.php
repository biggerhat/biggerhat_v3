<?php

namespace App\Http\Controllers\Game;

use App\Enums\BaseSizeEnum;
use App\Enums\GameFormatEnum;
use App\Enums\GameStatusEnum;
use App\Events\GameBoardAurasChanged;
use App\Events\GameBoardMarkersChanged;
use App\Events\GameBoardMoved;
use App\Events\GameBoardSetupChanged;
use App\Events\GameBoardTerrainChanged;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Game\Concerns\BroadcastsGameEvents;
use App\Http\Requests\Games\UpdateAurasRequest;
use App\Http\Requests\Games\UpdateBoardRequest;
use App\Http\Requests\Games\UpdateBoardSetupRequest;
use App\Http\Requests\Games\UpdateMarkersRequest;
use App\Http\Requests\Games\UpdateTerrainRequest;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\Marker;
use App\Services\Game\RoundClock;
use App\Services\Game\TurnSequence;
use App\Support\Game\CrewUpgradeCatalog;
use App\Support\Game\GameDeployment;
use App\Support\Tabletop\Layouts;
use App\Support\Tabletop\MarkerCatalogue;
use App\Support\Tabletop\TabletopAccess;
use App\Support\Tabletop\TabletopImages;
use App\Support\Tabletop\TokenCatalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\ResponseFactory;

class GameBoardController extends Controller
{
    use BroadcastsGameEvents;

    /** Each player can have this many of each kind of marker on the table at once. */
    private const MARKERS_PER_PLAYER = 5;

    /** A model or marker can show the other player this many auras (the same limit as private ones). */
    private const AURAS_PER_TOKEN = 3;

    public function show(Game $game): Response|ResponseFactory
    {
        $this->guard();
        Gate::authorize('view', $game);

        return $this->render($game, readOnly: false);
    }

    /**
     * A read-only board for anyone watching a game its players made observable (the same switch as the observe page). Observers
     * are not on the game's live channel, so this page refreshes itself instead.
     */
    public function watch(Game $game): Response|ResponseFactory
    {
        $this->guard();
        abort_unless($game->is_observable, 404);

        return $this->render($game, readOnly: true);
    }

    private function render(Game $game, bool $readOnly): Response|ResponseFactory
    {
        $game->load(['players.user:id,name', 'players.crewBuild:id,references', 'players.crewMembers.character:id,base,size,speed', 'players.crewMembers.customCharacter:id,base,size,speed,faction,actions,abilities,display_name,front_image,back_image,card_image_generated_at']);

        return inertia('Games/Board', [
            'game' => [
                'uuid' => $game->uuid,
                'is_solo' => (bool) $game->is_solo,
                'deployment' => $game->deployment?->value,
                // the tracker's setting: a killed model gives its own crew a soulstone
                'auto_soulstone_on_kill' => ($game->settings['auto_soulstone_on_kill'] ?? true) !== false,
                // Campaign games offer injuries as well as upgrades to attach to a model
                'is_campaign' => $game->format === GameFormatEnum::Campaign,
                'status' => $game->status->value,
            ],
            'read_only' => $readOnly,
            'token_options' => TokenCatalogue::options(),
            // what can be attached to a model (the same lists as the play screen); loaded after the board is up, and only for someone who can attach
            'character_upgrades' => Inertia::defer(fn () => ! $readOnly && $game->status === GameStatusEnum::InProgress ? CrewUpgradeCatalog::equipment($game)->values() : []),
            'character_injuries' => Inertia::defer(fn () => ! $readOnly && $game->status === GameStatusEnum::InProgress ? CrewUpgradeCatalog::injuries($game)->values() : []),
            'players' => $game->players->map(fn (GamePlayer $p) => [
                'slot' => $p->slot,
                // attacker or defender (null until the second player has joined and roles are given): the board colours the sides by it
                'role' => $p->role?->value,
                'name' => $this->playerName($p),
                'mine' => ! $readOnly && $this->controls($p, $game),
                'soulstone_pool' => (int) $p->soulstone_pool,
                // the upgrades this player's crew build lists, offered first when attaching one (their own crew only)
                'reference_upgrade_ids' => ! $readOnly && $this->controls($p, $game)
                    ? collect($p->crewBuild?->references['upgrades'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
                    : [],
                // a scheme is hidden information: only its own player is given the notes
                'scheme_notes' => ! $readOnly && $this->controls($p, $game) ? ($p->scheme_notes ?? null) : null,
            ])->values(),
            'turn' => fn () => app(TurnSequence::class)->state($game),
            'clock' => fn () => app(RoundClock::class)->for($game),
            // where the models and markers stood at the end of each turn, for the look back
            'history' => $game->board_state['history'] ?? (object) [],
            'models' => $this->models($game),
            'positions' => $game->board_state['positions'] ?? (object) [],
            'terrain' => $game->board_state['terrain'] ?? [],
            'markers' => MarkerCatalogue::options(),
            'placed_markers' => $game->board_state['markers'] ?? [],
            'shared_auras' => $game->board_state['auras'] ?? [],
            'deployment_variant' => (int) ($game->board_state['deployment_variant'] ?? 0),
            // the deployment step: whose turn, what is being placed; null once play (or an earlier step) is under way
            // (and, just after it, the "finished: choose schemes" state)
            'deploy' => $game->status === GameStatusEnum::Deployment || ($game->status === GameStatusEnum::SchemeSelect && isset($game->board_state['deploy']))
                ? GameDeployment::summary($game)
                : null,
            'table_image' => $game->board_state['table_image'] ?? null,
            'table_images' => TabletopImages::tables(),
            'terrain_images' => TabletopImages::terrain(),
            'layouts' => Inertia::defer(fn () => Layouts::mine(auth()->user())),
        ]);
    }

    /** Every crew member on the table, with the slot of the player who owns it. */
    private function models(Game $game): array
    {
        $models = [];
        foreach ($game->players as $player) {
            foreach ($player->crewMembers as $member) {
                $models[] = [
                    'id' => $member->id,
                    'name' => $member->display_name,
                    'slot' => $player->slot,
                    'base_mm' => $this->baseMm($member),
                    'sz' => $this->sz($member),
                    'sp' => $this->sp($member),
                    'killed' => (bool) $member->is_killed,
                    'health' => $member->current_health,
                    'max_health' => $member->max_health,
                    'activated' => (bool) $member->is_activated,
                    'tokens' => collect($member->attached_tokens ?? [])
                        ->filter(fn ($t) => is_array($t) && isset($t['id']))
                        ->map(fn (array $t) => ['id' => (int) $t['id'], 'name' => (string) ($t['name'] ?? 'Token')])
                        ->values()
                        ->all(),
                    'notes' => $member->notes,
                    // what the crew list on the board shows (the same row the play screen uses)
                    'member' => [
                        ...$member->only([
                            'id', 'game_player_id', 'character_id', 'display_name', 'faction', 'defense', 'willpower', 'speed', 'size',
                            'characteristics', 'front_image', 'back_image', 'attached_upgrades', 'station', 'is_summoned',
                        ]),
                        'custom_character' => $member->customCharacter?->only([
                            'id', 'faction', 'actions', 'abilities', 'display_name', 'front_image', 'back_image', 'card_image_generated_at',
                        ]),
                    ],
                ];
            }
        }

        return $models;
    }

    public function update(UpdateBoardRequest $request, Game $game): RedirectResponse
    {
        $this->guard();

        $game->load('players.crewMembers:id,game_player_id');
        $movable = $game->players
            ->filter(fn (GamePlayer $p) => $this->controls($p, $game))
            ->flatMap(fn (GamePlayer $p) => $p->crewMembers->pluck('id'))
            ->map(fn ($id) => (string) $id)
            ->all();

        // While the crews are being deployed only the group being placed may be moved (and only by the player placing it)
        $deployMovable = GameDeployment::movable($game);
        if ($deployMovable !== null) {
            $movable = array_values(array_intersect($movable, array_map('strval', $deployMovable)));
        }

        $incoming = collect($request->validated('positions'))
            ->filter(fn ($_, $id) => in_array((string) $id, $movable, true))
            ->map(fn (array $p) => ['x' => round((float) $p['x'], 2), 'y' => round((float) $p['y'], 2)] + (! empty($p['under']) ? ['under' => true] : [])
                + (! empty($p['ignores']) ? ['ignores' => array_values($p['ignores'])] : []));

        abort_if($incoming->isEmpty(), 403, 'You can only move your own models.');

        // Merge under a lock so two players dropping models at once do not overwrite each other.
        DB::transaction(function () use ($game, $incoming) {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);
            $positions = array_replace($locked->board_state['positions'] ?? [], $incoming->all());
            $locked->update(['board_state' => array_merge($locked->board_state ?? [], ['positions' => $positions])]);
        });

        $this->broadcastToOpponents($game, new GameBoardMoved($game, $incoming->all()));

        return redirect()->back();
    }

    /** Either player may lay out the table: in a real game both place the terrain together. The whole layout is replaced. */
    public function updateTerrain(UpdateTerrainRequest $request, Game $game): RedirectResponse
    {
        $this->guard();

        $terrain = array_values($request->validated('terrain'));

        DB::transaction(function () use ($game, $terrain) {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);
            $locked->update(['board_state' => array_merge($locked->board_state ?? [], ['terrain' => $terrain])]);
        });

        $this->broadcastToOpponents($game, new GameBoardTerrainChanged($game, $terrain));

        return redirect()->back();
    }

    /** Either player may turn the deployment zones round; the other sees it too. */
    public function updateSetup(UpdateBoardSetupRequest $request, Game $game): RedirectResponse
    {
        $this->guard();

        $data = $request->validated();
        $variant = 0;
        $tableImage = null;

        DB::transaction(function () use ($game, $data, &$variant, &$tableImage) {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);
            /** @var array<string, mixed> $board */
            $board = $locked->board_state ?? [];
            if (array_key_exists('deployment_variant', $data)) {
                $board['deployment_variant'] = (int) $data['deployment_variant'];
            }
            if (array_key_exists('table_image', $data)) {
                if ($data['table_image'] === null) {
                    unset($board['table_image']);
                } else {
                    $board['table_image'] = (string) $data['table_image'];
                }
            }
            $locked->update(['board_state' => $board]);
            $variant = (int) ($board['deployment_variant'] ?? 0);
            $tableImage = $board['table_image'] ?? null;
        });

        $this->broadcastToOpponents($game, new GameBoardSetupChanged($game, $variant, $tableImage));

        return redirect()->back();
    }

    /**
     * Each player owns the markers they place and can only move or pick up their own (in a solo game you play both sides).
     * A player can have five of each kind of marker on the table. Applied under a lock, so simultaneous edits both survive.
     */
    public function updateMarkers(UpdateMarkersRequest $request, Game $game): RedirectResponse
    {
        $this->guard();

        $game->load('players');
        $mine = $game->players->filter(fn (GamePlayer $p) => $this->controls($p, $game))->pluck('slot')->map(fn ($s) => (int) $s)->all();
        $upsert = collect($request->validated('upsert') ?? []);
        $remove = collect($request->validated('remove') ?? [])->all();
        $problem = null;
        $result = [];

        DB::transaction(function () use ($game, $upsert, $remove, $mine, &$problem, &$result) {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);
            $placed = collect($locked->board_state['markers'] ?? [])->keyBy('id');
            $allowed = fn (?array $existing): bool => $existing === null || ! isset($existing['owner']) || in_array((int) $existing['owner'], $mine, true);

            foreach ($remove as $id) {
                if (! $allowed($placed->get($id))) {
                    $problem = 'forbidden';

                    return;
                }
                $placed->forget($id);
            }
            foreach ($upsert as $m) {
                $existing = $placed->get($m['id']);
                $owner = $existing['owner'] ?? (int) $m['owner'];
                // Moving one of yours, or placing a new one for a side you play. Never someone else's.
                if (! $allowed($existing) || ($existing === null && ! in_array((int) $owner, $mine, true))) {
                    $problem = 'forbidden';

                    return;
                }
                $placed->put($m['id'], [
                    'id' => $m['id'],
                    'marker_id' => (int) $m['marker_id'],
                    'owner' => (int) $owner,
                    'x' => round((float) $m['x'], 2),
                    'y' => round((float) $m['y'], 2),
                ]);
            }

            // Strategy markers do not count towards the limit of five of a kind (p.45).
            $strategy = Marker::query()->whereIn('id', $placed->pluck('marker_id')->unique())->where('is_strategy', true)->pluck('id')->all();
            $limited = $placed->reject(fn ($m) => in_array((int) $m['marker_id'], $strategy, true));
            if ($limited->groupBy(fn ($m) => ($m['owner'] ?? 0).'-'.$m['marker_id'])->contains(fn ($group) => $group->count() > self::MARKERS_PER_PLAYER)) {
                $problem = 'limit';

                return;
            }

            $result = $placed->values()->all();
            // An aura cast by a marker that is gone goes with it.
            $auras = array_values(array_filter($locked->board_state['auras'] ?? [], fn ($a) => ! in_array($a['owner_id'], $remove, true)));
            $locked->update(['board_state' => array_merge($locked->board_state ?? [], ['markers' => $result, 'auras' => $auras])]);
        });

        abort_if($problem === 'forbidden', 403, 'You can only move or pick up your own markers.');
        if ($problem === 'limit') {
            throw ValidationException::withMessages(['upsert' => 'Each player can have up to '.self::MARKERS_PER_PLAYER.' of each marker on the table.']);
        }

        $this->broadcastToOpponents($game, new GameBoardMarkersChanged($game, $result));

        return redirect()->back();
    }

    /**
     * Auras are private to a viewer's browser until the owner shares one. A shared aura is saved with the game so both players see
     * it (and it survives a reload); only the player who controls the model, or placed the marker, changes or withdraws it.
     */
    public function updateAuras(UpdateAurasRequest $request, Game $game): RedirectResponse
    {
        $this->guard();

        $game->load('players.crewMembers:id,game_player_id');
        $mine = $game->players->filter(fn (GamePlayer $p) => $this->controls($p, $game));
        $myModels = $mine->flatMap(fn (GamePlayer $p) => $p->crewMembers->pluck('id'))->map(fn ($id) => (string) $id)->all();
        $mySlots = $mine->pluck('slot')->map(fn ($s) => (int) $s)->all();
        $upsert = collect($request->validated('upsert') ?? []);
        $remove = collect($request->validated('remove') ?? [])->all();
        $problem = null;
        $result = [];

        DB::transaction(function () use ($game, $upsert, $remove, $myModels, $mySlots, &$problem, &$result) {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);
            $markers = collect($locked->board_state['markers'] ?? [])->keyBy('id');
            $auras = collect($locked->board_state['auras'] ?? [])->keyBy('id');
            $modelIds = $game->players->flatMap(fn (GamePlayer $p) => $p->crewMembers->pluck('id'))->map(fn ($id) => (string) $id)->all();

            // Yours when you control the model, or placed the marker (a marker with no owner belongs to either player).
            $isMine = function (string $ownerId) use ($myModels, $mySlots, $markers, $modelIds): bool {
                if (in_array($ownerId, $modelIds, true)) {
                    return in_array($ownerId, $myModels, true);
                }
                $marker = $markers->get($ownerId);

                return $marker !== null && (! isset($marker['owner']) || in_array((int) $marker['owner'], $mySlots, true));
            };

            foreach ($remove as $id) {
                $existing = $auras->get($id);
                if ($existing !== null && ! $isMine($existing['owner_id'])) {
                    $problem = 'forbidden';

                    return;
                }
                $auras->forget($id);
            }
            foreach ($upsert as $a) {
                $existing = $auras->get($a['id']);
                $ownerId = $existing['owner_id'] ?? $a['owner_id'];
                if (! $isMine($ownerId)) {
                    $problem = 'forbidden';

                    return;
                }
                $auras->put($a['id'], array_filter([
                    'id' => $a['id'],
                    'owner_id' => $ownerId,
                    'range' => round((float) $a['range'] * 2) / 2,
                    'locked' => (bool) $a['locked'],
                    'x' => $a['locked'] ? round((float) $a['x'], 2) : null,
                    'y' => $a['locked'] ? round((float) $a['y'], 2) : null,
                    'traits' => ! empty($a['traits']) ? array_values($a['traits']) : null,
                ], fn ($v) => $v !== null));
            }

            if ($auras->groupBy('owner_id')->contains(fn ($group) => $group->count() > self::AURAS_PER_TOKEN)) {
                $problem = 'limit';

                return;
            }

            $result = $auras->values()->all();
            $locked->update(['board_state' => array_merge($locked->board_state ?? [], ['auras' => $result])]);
        });

        abort_if($problem === 'forbidden', 403, 'You can only share or change auras on your own models and markers.');
        if ($problem === 'limit') {
            throw ValidationException::withMessages(['upsert' => 'A model can show up to '.self::AURAS_PER_TOKEN.' auras.']);
        }

        $this->broadcastToOpponents($game, new GameBoardAurasChanged($game, $result));

        return redirect()->back();
    }

    /** Pre-release: hidden (404, not 403) unless the flag is on or the user holds the beta permission. */
    private function guard(): void
    {
        abort_unless(TabletopAccess::allows(auth()->user()), 404);
    }

    /** Base size in mm from the character card, or the custom card, falling back to 30 mm (the common size). */
    private function baseMm(GameCrewMember $member): int
    {
        if ($member->character_id !== null) {
            return $member->character->base->value;
        }
        if ($member->custom_character_id !== null) {
            return $member->customCharacter->base->value;
        }

        return BaseSizeEnum::ThirtyMM->value;
    }

    /** The Sz stat from the character card, or the custom card; null when it is not known (the board then assumes 1). */
    private function sz(GameCrewMember $member): ?int
    {
        if ($member->character_id !== null) {
            return $member->character->size;
        }
        if ($member->custom_character_id !== null) {
            return $member->customCharacter->size;
        }

        return null;
    }

    /** The Sp stat as printed on the card: a Walk moves up to this many inches. Null when unknown. */
    private function sp(GameCrewMember $member): ?int
    {
        if ($member->character_id !== null) {
            return $member->character->speed;
        }
        if ($member->custom_character_id !== null) {
            return $member->customCharacter->speed;
        }

        return null;
    }

    /** You move your own crew. In a solo game you play both sides. */
    private function controls(GamePlayer $player, Game $game): bool
    {
        return $game->is_solo ? true : $player->user_id === auth()->id();
    }

    private function playerName(GamePlayer $player): string
    {
        return ($player->user_id === null ? null : $player->user->name) ?? $player->opponent_name ?? 'Opponent';
    }
}
