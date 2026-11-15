<?php

namespace App\Http\Controllers\Game;

use App\Enums\BaseSizeEnum;
use App\Events\GameBoardAurasChanged;
use App\Events\GameBoardMarkersChanged;
use App\Events\GameBoardMoved;
use App\Events\GameBoardTerrainChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Games\UpdateAurasRequest;
use App\Http\Requests\Games\UpdateBoardRequest;
use App\Http\Requests\Games\UpdateMarkersRequest;
use App\Http\Requests\Games\UpdateTerrainRequest;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Support\Tabletop\MarkerCatalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Inertia\ResponseFactory;
use Laravel\Pennant\Feature;

class GameBoardController extends Controller
{
    /** Each player can have this many of each kind of marker on the table at once. */
    private const MARKERS_PER_PLAYER = 5;

    /** A model or marker can show the other player this many auras (the same limit as private ones). */
    private const AURAS_PER_TOKEN = 3;

    public function show(Game $game): Response|ResponseFactory
    {
        $this->guard();
        Gate::authorize('view', $game);

        $game->load(['players.user:id,name', 'players.crewMembers.character:id,base', 'players.crewMembers.customCharacter:id,base']);

        return inertia('Games/Board', [
            'game' => ['uuid' => $game->uuid, 'is_solo' => (bool) $game->is_solo, 'deployment' => $game->deployment?->value],
            'players' => $game->players->map(fn (GamePlayer $p) => [
                'slot' => $p->slot,
                'name' => $this->playerName($p),
                'mine' => $this->controls($p, $game),
            ])->values(),
            'models' => $game->players->flatMap(fn (GamePlayer $p) => $p->crewMembers->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->display_name,
                'slot' => $p->slot,
                'base_mm' => $this->baseMm($m),
                'killed' => (bool) $m->is_killed,
            ]))->values(),
            'positions' => $game->board_state['positions'] ?? (object) [],
            'terrain' => $game->board_state['terrain'] ?? [],
            'markers' => MarkerCatalogue::options(),
            'placed_markers' => $game->board_state['markers'] ?? [],
            'shared_auras' => $game->board_state['auras'] ?? [],
        ]);
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

        $incoming = collect($request->validated('positions'))
            ->filter(fn ($_, $id) => in_array((string) $id, $movable, true))
            ->map(fn (array $p) => ['x' => round((float) $p['x'], 2), 'y' => round((float) $p['y'], 2)]);

        abort_if($incoming->isEmpty(), 403, 'You can only move your own models.');

        // Merge under a lock so two players dropping models at once do not overwrite each other.
        DB::transaction(function () use ($game, $incoming) {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);
            $positions = array_replace($locked->board_state['positions'] ?? [], $incoming->all());
            $locked->update(['board_state' => array_merge($locked->board_state ?? [], ['positions' => $positions])]);
        });

        broadcast(new GameBoardMoved($game, $incoming->all()))->toOthers();

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

        broadcast(new GameBoardTerrainChanged($game, $terrain))->toOthers();

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

            if ($placed->groupBy(fn ($m) => ($m['owner'] ?? 0).'-'.$m['marker_id'])->contains(fn ($group) => $group->count() > self::MARKERS_PER_PLAYER)) {
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

        broadcast(new GameBoardMarkersChanged($game, $result))->toOthers();

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

        broadcast(new GameBoardAurasChanged($game, $result))->toOthers();

        return redirect()->back();
    }

    /** Pre-release: hidden (404, not 403) until the flag is on. */
    private function guard(): void
    {
        abort_unless(Feature::for(null)->active('experiment-tabletop'), 404);
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
