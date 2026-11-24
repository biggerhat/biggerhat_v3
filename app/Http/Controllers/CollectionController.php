<?php

namespace App\Http\Controllers;

use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\GameSystemEnum;
use App\Enums\ModelStageEnum;
use App\Http\Requests\Collection\CollectionAddCharacterRequest;
use App\Http\Requests\Collection\CollectionAddCharactersRequest;
use App\Http\Requests\Collection\CollectionAddModelRequest;
use App\Http\Requests\Collection\CollectionAddPackageRequest;
use App\Http\Requests\Collection\CollectionDeleteModelRequest;
use App\Http\Requests\Collection\CollectionRemoveBulkRequest;
use App\Http\Requests\Collection\CollectionRemoveRequest;
use App\Http\Requests\Collection\CollectionSetStageBulkRequest;
use App\Http\Requests\Collection\CollectionTogglePackageRequest;
use App\Http\Requests\Collection\CollectionToggleRequest;
use App\Http\Requests\Collection\CollectionUpdateModelRequest;
use App\Http\Requests\Collection\CollectionUpdateStatusBulkRequest;
use App\Http\Requests\Collection\CollectionUpdateStatusRequest;
use App\Models\Character;
use App\Models\CollectionModel;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\Package;
use App\Models\User;
use App\Support\Collection\CollectionModels;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CollectionController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Ensure share code exists
        if (! $user->collection_share_code) {
            $user->collection_share_code = Str::random(12);
            $user->save();
        }

        return inertia('Collection/Index', [
            ...$this->collectionPropsFor($user, true),
            'is_owner' => true,
            'share_code' => $user->collection_share_code,
            'is_public' => (bool) $user->collection_is_public,
        ]);
    }

    /**
     * "What can I play?" — for every Master the user owns, the owned models that share one of its
     * keywords and how far that gets toward a full encounter-size crew.
     */
    public function playable()
    {
        $user = Auth::user();

        $owned = $user->collectionMiniatures()
            ->with(['character:id,slug,display_name,faction,station,cost,is_unhirable'])
            ->get()
            ->filter(fn (Miniature $m) => $m->character !== null)
            ->groupBy('character_id')
            ->map(fn ($group) => ['character' => $group->first()->character, 'quantity' => (int) $group->sum('pivot.quantity')]);

        $characterIds = $owned->keys()->all();
        /** @var array<int, array<int, int>> $keywordIdsByCharacter */
        $keywordIdsByCharacter = [];
        foreach (DB::table('characterables')
            ->where('characterable_type', Keyword::class)
            ->whereIn('character_id', $characterIds)
            ->get(['character_id', 'characterable_id']) as $link) {
            $keywordIdsByCharacter[(int) $link->character_id][] = (int) $link->characterable_id;
        }

        $keywordNames = Keyword::whereIn('id', collect($keywordIdsByCharacter)->flatten()->unique()->all())->pluck('name', 'id');

        $isMaster = fn (array $row): bool => $row['character']->station === CharacterStationEnum::Master;
        $masters = $owned->filter($isMaster);
        $hires = $owned->filter(fn (array $row) => ! $isMaster($row) && ! $row['character']->is_unhirable);

        $rows = [];
        foreach ($masters as $masterId => $row) {
            $masterKeywords = $keywordIdsByCharacter[$masterId] ?? [];
            $models = [];
            foreach ($hires as $hireId => $hire) {
                if (array_intersect($masterKeywords, $keywordIdsByCharacter[$hireId] ?? []) === []) {
                    continue;
                }
                $models[] = [
                    'slug' => $hire['character']->slug,
                    'name' => $hire['character']->display_name,
                    'cost' => (int) $hire['character']->cost,
                    'quantity' => $hire['quantity'],
                ];
            }
            usort($models, fn (array $a, array $b) => $b['cost'] <=> $a['cost']);

            $rows[] = [
                'slug' => $row['character']->slug,
                'name' => $row['character']->display_name,
                'faction' => $row['character']->faction->value,
                'keywords' => array_values(array_filter(array_map(fn (int $id) => $keywordNames[$id] ?? null, $masterKeywords))),
                'models' => $models,
                'model_count' => array_sum(array_column($models, 'quantity')),
                'total_cost' => array_sum(array_map(fn (array $m) => $m['cost'] * $m['quantity'], $models)),
            ];
        }
        usort($rows, fn (array $a, array $b) => $b['total_cost'] <=> $a['total_cost']);

        return inertia('Collection/Playable', [
            'masters' => $rows,
            'encounter_size' => 50,
        ]);
    }

    public function share(string $shareCode)
    {
        $user = User::where('collection_share_code', $shareCode)->firstOrFail();

        if (! $user->collection_is_public && Auth::id() !== $user->id) {
            abort(403, 'This collection is private.');
        }

        return inertia('Collection/Index', [
            ...$this->collectionPropsFor($user, Auth::id() === $user->id),
            'is_owner' => Auth::id() === $user->id,
            'share_code' => $user->collection_share_code,
            'is_public' => (bool) $user->collection_is_public,
            'owner_name' => $user->name,
        ]);
    }

    public function togglePublic()
    {
        $user = Auth::user();
        $user->collection_is_public = ! $user->collection_is_public;

        if (! $user->collection_share_code) {
            $user->collection_share_code = Str::random(12);
        }

        $user->save();

        return back();
    }

    public function toggle(CollectionToggleRequest $request)
    {
        $validated = $request->validated();

        $user = Auth::user();
        $miniatureId = $validated['miniature_id'];
        $quantity = $validated['quantity'] ?? null;

        $existing = $user->collectionMiniatures()->where('miniature_id', $miniatureId)->exists();

        if ($quantity === 0 || ($existing && $quantity === null)) {
            CollectionModels::remove($user, [$miniatureId]);
        } else {
            CollectionModels::setQuantity($user, $miniatureId, $quantity ?? 1);
        }

        return back();
    }

    public function addCharacter(CollectionAddCharacterRequest $request)
    {
        $validated = $request->validated();

        $user = Auth::user();
        $character = Character::with('standardMiniatures')->findOrFail($validated['character_id']);

        // Wrap in a transaction so a half-applied attach can't leave the
        // collection with orphan rows on retry — matches addCharacters and
        // addPackage which already do this.
        DB::transaction(function () use ($user, $character) {
            $miniatureIds = $character->standardMiniatures->pluck('id');
            CollectionModels::addMissing($user, $miniatureIds);
        });

        return back();
    }

    public function addCharacters(CollectionAddCharactersRequest $request)
    {
        $validated = $request->validated();

        $user = Auth::user();

        DB::transaction(function () use ($user, $validated) {
            $characters = Character::with('standardMiniatures')->whereIn('id', $validated['character_ids'])->get();
            $miniatureIds = $characters->flatMap(fn ($c) => $c->standardMiniatures->pluck('id'));
            CollectionModels::addMissing($user, $miniatureIds);
        });

        return back();
    }

    public function addPackage(CollectionAddPackageRequest $request)
    {
        $validated = $request->validated();

        $user = Auth::user();
        $package = Package::with('characters.standardMiniatures')->findOrFail($validated['package_id']);

        DB::transaction(function () use ($user, $package) {
            if (! $user->collectionPackages()->where('package_id', $package->id)->exists()) {
                $user->collectionPackages()->attach($package->id);
            }

            $miniatureIds = $package->characters->flatMap(fn ($c) => $c->standardMiniatures->pluck('id'));
            CollectionModels::addMissing($user, $miniatureIds);
        });

        return back();
    }

    public function togglePackage(CollectionTogglePackageRequest $request)
    {
        $validated = $request->validated();

        $user = Auth::user();
        $packageId = $validated['package_id'];

        if ($user->collectionPackages()->where('package_id', $packageId)->exists()) {
            $user->collectionPackages()->detach($packageId);
        } else {
            $user->collectionPackages()->attach($packageId);
        }

        return back();
    }

    public function updateStatus(CollectionUpdateStatusRequest $request)
    {
        $validated = $request->validated();

        $user = Auth::user();
        $miniatureId = $validated['miniature_id'];

        CollectionModels::mark($user, [(int) $miniatureId], $validated['is_built'] ?? null, $validated['is_painted'] ?? null);

        return back();
    }

    public function remove(CollectionRemoveRequest $request)
    {
        $validated = $request->validated();

        CollectionModels::remove(Auth::user(), [(int) $validated['miniature_id']]);

        return back();
    }

    public function removeBulk(CollectionRemoveBulkRequest $request)
    {
        $validated = $request->validated();

        CollectionModels::remove(Auth::user(), array_map('intval', $validated['miniature_ids']));

        return back();
    }

    public function updateStatusBulk(CollectionUpdateStatusBulkRequest $request)
    {
        $validated = $request->validated();

        CollectionModels::mark(Auth::user(), array_map('intval', $validated['miniature_ids']), $validated['is_built'] ?? null, $validated['is_painted'] ?? null);

        return back();
    }

    /** Every copy of the chosen miniatures to one stage. */
    public function setStageBulk(CollectionSetStageBulkRequest $request)
    {
        $validated = $request->validated();
        CollectionModels::setStage(Auth::user(), array_map('intval', $validated['miniature_ids']), ModelStageEnum::from($validated['stage']));

        return back();
    }

    /** One more copy of a miniature, with its own stage and note. */
    public function addModel(CollectionAddModelRequest $request)
    {
        $validated = $request->validated();
        $model = CollectionModels::addModel(
            Auth::user(),
            (int) $validated['miniature_id'],
            isset($validated['stage']) ? ModelStageEnum::from($validated['stage']) : ModelStageEnum::Unbuilt,
            $validated['note'] ?? null,
        );

        return back()->withMessage($model === null ? 'That is the most copies one collection can hold.' : 'Model added.');
    }

    /** One copy's stage or note. */
    public function updateModel(CollectionUpdateModelRequest $request, CollectionModel $collectionModel)
    {
        $validated = $request->validated();
        CollectionModels::updateModel(
            $collectionModel,
            isset($validated['stage']) ? ModelStageEnum::from($validated['stage']) : null,
            array_key_exists('note', $validated),
            $validated['note'] ?? null,
        );

        return back();
    }

    /** Takes one copy out of the collection. The last copy takes the miniature out. */
    public function deleteModel(CollectionDeleteModelRequest $request, CollectionModel $collectionModel)
    {
        CollectionModels::deleteModel($collectionModel);

        return back();
    }

    /**
     * Props shared by the owner-facing and share-link Collection views.
     *
     * Splits the payload three ways:
     *   1. Immediate — collection items, totals, faction stats. These power
     *      the default Collection tab + overview cards and need to be in the
     *      initial HTML so users see content on first paint.
     *   2. Deferred (collection_extras group) — keyword stats and owned
     *      packages. Only visible after the user opens those tabs, so we
     *      let Inertia stream them in a follow-up request and shave them
     *      off the synchronous response time.
     *
     * @return array<string, mixed>
     */
    private function collectionPropsFor(User $user, bool $withNotes = false): array
    {
        return [
            ...$this->buildCoreCollectionData($user, $withNotes),
            'keyword_stats' => Inertia::defer(fn () => $this->buildKeywordStats($user), 'collection_extras'),
            'owned_packages' => Inertia::defer(fn () => $this->buildOwnedPackages($user), 'collection_extras'),
        ];
    }

    /**
     * Immediate render payload: characters owned, faction stats, totals.
     * Shares one Character query across faction stats + totals.
     *
     * @return array<string, mixed>
     */
    private function buildCoreCollectionData(User $user, bool $withNotes = false): array
    {
        $totals = CollectionModels::totals($user);

        // Characters in the catalogue and how many the user has at least one miniature of, per faction (two grouped counts).
        $standard = fn () => Character::standard()->where('is_hidden', false);
        $totalByFaction = $standard()->selectRaw('faction, COUNT(*) as c')->groupBy('faction')->pluck('c', 'faction');
        $ownedCharacterIds = DB::table('user_miniatures')
            ->join('miniatures', 'miniatures.id', '=', 'user_miniatures.miniature_id')
            ->where('user_miniatures.user_id', $user->id)
            ->pluck('miniatures.character_id')->unique()->values()->all();
        $ownedByFaction = $ownedCharacterIds === []
            ? collect()
            : $standard()->whereIn('id', $ownedCharacterIds)->selectRaw('faction, COUNT(*) as c')->groupBy('faction')->pluck('c', 'faction');

        // The models owned in each faction, and how many of them are built and painted.
        $built = ModelStageEnum::builtValues();
        $painted = ModelStageEnum::paintedValues();
        $modelsByFaction = DB::table('collection_models')
            ->join('miniatures', 'miniatures.id', '=', 'collection_models.miniature_id')
            ->join('characters', 'characters.id', '=', 'miniatures.character_id')
            ->where('collection_models.user_id', $user->id)
            ->selectRaw(
                'characters.faction as faction, COUNT(*) as models, SUM(CASE WHEN collection_models.stage IN ('.implode(',', array_fill(0, count($built), '?')).') THEN 1 ELSE 0 END) as built, SUM(CASE WHEN collection_models.stage IN ('.implode(',', array_fill(0, count($painted), '?')).') THEN 1 ELSE 0 END) as painted',
                [...$built, ...$painted],
            )
            ->groupBy('characters.faction')->get()->keyBy('faction');

        $factionStats = [];
        foreach (FactionEnum::cases() as $faction) {
            $total = (int) ($totalByFaction[$faction->value] ?? 0);
            $owned = (int) ($ownedByFaction[$faction->value] ?? 0);
            $factionStats[] = [
                'models' => (int) ($modelsByFaction[$faction->value]->models ?? 0),
                'built' => (int) ($modelsByFaction[$faction->value]->built ?? 0),
                'painted' => (int) ($modelsByFaction[$faction->value]->painted ?? 0),
                'faction' => $faction->value,
                'name' => $faction->label(),
                'color' => $faction->color(),
                'logo' => $faction->logo(),
                'total' => $total,
                'owned' => $owned,
                'percent' => $total > 0 ? round(($owned / $total) * 100, 1) : 0,
            ];
        }
        $allCount = (int) $totalByFaction->sum();
        $ownedCount = (int) $ownedByFaction->sum();

        $models = CollectionModel::where('user_id', $user->id)->orderBy('id')->get(['id', 'miniature_id', 'stage', 'note'])->groupBy('miniature_id');

        $collectionItems = $user->collectionMiniatures()
            ->with(['character.keywords', 'character.standardMiniatures'])
            ->get()
            ->map(function (Miniature $m) use ($models, $withNotes) {
                $character = $m->character;
                $copies = $models[$m->id] ?? collect();
                $built = $copies->filter(fn (CollectionModel $c) => $c->stage->isBuilt())->count();
                $painted = $copies->filter(fn (CollectionModel $c) => $c->stage->isPainted())->count();

                return [
                    'miniature_id' => $m->id,
                    'miniature_name' => $m->display_name,
                    'miniature_slug' => $m->slug,
                    'front_image' => $m->front_image,
                    'character_id' => $m->character_id,
                    'character_name' => $character->display_name,
                    'character_slug' => $character->slug,
                    'faction' => $character->faction->value,
                    'station' => $character->station?->value,
                    'keywords' => $character->keywords->pluck('name')->toArray(),
                    'quantity' => $copies->count(),
                    'is_built' => $copies->isNotEmpty() && $built === $copies->count(),
                    'is_painted' => $copies->isNotEmpty() && $painted === $copies->count(),
                    'built_count' => $built,
                    'painted_count' => $painted,
                    // Each copy: its stage, and its note when the owner is looking.
                    'models' => $copies->map(fn (CollectionModel $c) => [
                        'id' => $c->id,
                        'stage' => $c->stage->value,
                        'note' => $withNotes ? $c->note : null,
                    ])->values()->all(),
                    'standard_miniature_id' => $character->standardMiniatures->first()?->id,
                ];
            });

        $ownedPackagesCount = $user->collectionPackages()->whereIn('game_system', [GameSystemEnum::Malifaux, GameSystemEnum::Both])->count();

        return [
            'collection' => $collectionItems,
            'faction_stats' => $factionStats,
            'stages' => collect(ModelStageEnum::cases())->map(fn (ModelStageEnum $s) => ['value' => $s->value, 'label' => $s->label(), 'count' => $totals['stages'][$s->value]])->all(),
            'totals' => [
                'characters' => $allCount,
                'owned_characters' => $ownedCount,
                'owned_miniatures' => $totals['models'],
                'owned_packages' => $ownedPackagesCount,
                'percent' => $allCount > 0 ? round(($ownedCount / $allCount) * 100, 1) : 0,
                'built' => $totals['built'],
                'painted' => $totals['painted'],
                'built_percent' => $totals['models'] > 0 ? round(($totals['built'] / $totals['models']) * 100, 1) : 0,
                'painted_percent' => $totals['models'] > 0 ? round(($totals['painted'] / $totals['models']) * 100, 1) : 0,
            ],
        ];
    }

    /**
     * Keyword stats — runs in the deferred follow-up request so the heavy
     * withCount query doesn't block the initial paint. Resolves owned
     * character IDs via a single JOIN to skip re-loading every character.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildKeywordStats(User $user): array
    {
        $ownedCharacterIds = DB::table('user_miniatures')
            ->join('miniatures', 'miniatures.id', '=', 'user_miniatures.miniature_id')
            ->where('user_miniatures.user_id', $user->id)
            ->pluck('miniatures.character_id')
            ->unique()
            ->all();

        $allKeywords = Keyword::standard()->withCount([
            'characters',
            'characters as owned_characters_count' => fn ($q) => $q->whereIn('characters.id', $ownedCharacterIds),
        ])->orderBy('name')->get();

        $stats = [];
        foreach ($allKeywords as $keyword) {
            if ($keyword->characters_count > 0) {
                $stats[] = [
                    'name' => $keyword->name,
                    'slug' => $keyword->slug,
                    'total' => $keyword->characters_count,
                    'owned' => $keyword->owned_characters_count,
                    'percent' => round(($keyword->owned_characters_count / $keyword->characters_count) * 100, 1),
                ];
            }
        }

        return $stats;
    }

    /**
     * Owned-package summary — deferred since it's only visible on the
     * Packages tab.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildOwnedPackages(User $user): array
    {
        $packages = $user->collectionPackages()
            ->whereIn('game_system', [GameSystemEnum::Malifaux, GameSystemEnum::Both])
            ->with('characters')
            ->get();

        // The stage of every model the user owns, by character, furthest along first.
        $mine = DB::table('collection_models')
            ->join('miniatures', 'miniatures.id', '=', 'collection_models.miniature_id')
            ->where('collection_models.user_id', $user->id)
            ->get(['miniatures.character_id', 'collection_models.stage'])
            ->groupBy('character_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => ModelStageEnum::from($r->stage))->sortByDesc(fn (ModelStageEnum $s) => $s->rank())->values());

        return $packages
            ->map(function (Package $p) use ($mine) {
                $contents = $p->characters->map(function (Character $c) use ($mine) {
                    $needed = max(1, (int) ($c->pivot->quantity ?? 1));
                    // The models of this character stand in for what the package holds, best ones first.
                    $have = ($mine[$c->id] ?? collect())->take($needed);

                    return [
                        'slug' => $c->slug,
                        'name' => $c->display_name,
                        'quantity' => $needed,
                        'owned' => $have->count(),
                        'built' => $have->filter(fn (ModelStageEnum $s) => $s->isBuilt())->count(),
                        'painted' => $have->filter(fn (ModelStageEnum $s) => $s->isPainted())->count(),
                    ];
                })->sortBy('name')->values();

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'front_image' => $p->front_image,
                    'factions' => collect($p->factions ?? [])->map(fn (string $f) => [
                        'value' => $f,
                        'label' => FactionEnum::from($f)->label(),
                        'color' => FactionEnum::from($f)->color(),
                        'logo' => FactionEnum::from($f)->logo(),
                    ]),
                    'models' => $contents->sum('quantity'),
                    'owned' => $contents->sum('owned'),
                    'built' => $contents->sum('built'),
                    'painted' => $contents->sum('painted'),
                    'contents' => $contents->all(),
                ];
            })
            ->all();
    }
}
