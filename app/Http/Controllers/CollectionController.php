<?php

namespace App\Http\Controllers;

use App\Enums\FactionEnum;
use App\Enums\GameSystemEnum;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\Package;
use App\Models\User;
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
            ...$this->collectionPropsFor($user),
            'is_owner' => true,
            'share_code' => $user->collection_share_code,
            'is_public' => (bool) $user->collection_is_public,
        ]);
    }

    public function share(string $shareCode)
    {
        $user = User::where('collection_share_code', $shareCode)->firstOrFail();

        if (! $user->collection_is_public && Auth::id() !== $user->id) {
            abort(403, 'This collection is private.');
        }

        return inertia('Collection/Index', [
            ...$this->collectionPropsFor($user),
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

    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'miniature_id' => 'required|exists:miniatures,id',
            'quantity' => 'nullable|integer|min:0',
        ]);

        $user = Auth::user();
        $miniatureId = $validated['miniature_id'];
        $quantity = $validated['quantity'] ?? null;

        $existing = $user->collectionMiniatures()->where('miniature_id', $miniatureId)->first();

        if ($quantity === 0) {
            $user->collectionMiniatures()->detach($miniatureId);

            return back();
        }

        if ($existing) {
            if ($quantity !== null) {
                $user->collectionMiniatures()->updateExistingPivot($miniatureId, ['quantity' => $quantity]);
            } else {
                $user->collectionMiniatures()->detach($miniatureId);
            }
        } else {
            $user->collectionMiniatures()->attach($miniatureId, ['quantity' => $quantity ?? 1]);
        }

        return back();
    }

    public function addCharacter(Request $request)
    {
        $validated = $request->validate([
            'character_id' => 'required|exists:characters,id',
        ]);

        $user = Auth::user();
        $character = Character::with('standardMiniatures')->findOrFail($validated['character_id']);

        // Wrap in a transaction so a half-applied attach can't leave the
        // collection with orphan rows on retry — matches addCharacters and
        // addPackage which already do this.
        DB::transaction(function () use ($user, $character) {
            $miniatureIds = $character->standardMiniatures->pluck('id');
            $existing = $user->collectionMiniatures()->whereIn('miniature_id', $miniatureIds)->pluck('miniature_id');
            $toAttach = $miniatureIds->diff($existing)->mapWithKeys(fn ($id) => [$id => ['quantity' => 1]]);

            if ($toAttach->isNotEmpty()) {
                $user->collectionMiniatures()->attach($toAttach);
            }
        });

        return back();
    }

    public function addCharacters(Request $request)
    {
        $validated = $request->validate([
            'character_ids' => 'required|array',
            'character_ids.*' => 'integer|exists:characters,id',
        ]);

        $user = Auth::user();

        DB::transaction(function () use ($user, $validated) {
            $characters = Character::with('standardMiniatures')->whereIn('id', $validated['character_ids'])->get();
            $miniatureIds = $characters->flatMap(fn ($c) => $c->standardMiniatures->pluck('id'));
            $existing = $user->collectionMiniatures()->whereIn('miniature_id', $miniatureIds)->pluck('miniature_id');
            $toAttach = $miniatureIds->diff($existing)->mapWithKeys(fn ($id) => [$id => ['quantity' => 1]]);

            if ($toAttach->isNotEmpty()) {
                $user->collectionMiniatures()->attach($toAttach);
            }
        });

        return back();
    }

    public function addPackage(Request $request)
    {
        $validated = $request->validate([
            'package_id' => 'required|exists:packages,id',
        ]);

        $user = Auth::user();
        $package = Package::with('characters.standardMiniatures')->findOrFail($validated['package_id']);

        DB::transaction(function () use ($user, $package) {
            if (! $user->collectionPackages()->where('package_id', $package->id)->exists()) {
                $user->collectionPackages()->attach($package->id);
            }

            $miniatureIds = $package->characters->flatMap(fn ($c) => $c->standardMiniatures->pluck('id'));
            $existing = $user->collectionMiniatures()->whereIn('miniature_id', $miniatureIds)->pluck('miniature_id');
            $toAttach = $miniatureIds->diff($existing)->mapWithKeys(fn ($id) => [$id => ['quantity' => 1]]);

            if ($toAttach->isNotEmpty()) {
                $user->collectionMiniatures()->attach($toAttach);
            }
        });

        return back();
    }

    public function togglePackage(Request $request)
    {
        $validated = $request->validate([
            'package_id' => 'required|exists:packages,id',
        ]);

        $user = Auth::user();
        $packageId = $validated['package_id'];

        if ($user->collectionPackages()->where('package_id', $packageId)->exists()) {
            $user->collectionPackages()->detach($packageId);
        } else {
            $user->collectionPackages()->attach($packageId);
        }

        return back();
    }

    public function updateStatus(Request $request)
    {
        $validated = $request->validate([
            'miniature_id' => 'required|exists:miniatures,id',
            'is_built' => 'nullable|boolean',
            'is_painted' => 'nullable|boolean',
        ]);

        $user = Auth::user();
        $miniatureId = $validated['miniature_id'];

        $data = [];
        if (isset($validated['is_built'])) {
            $data['is_built'] = $validated['is_built'];
        }
        if (isset($validated['is_painted'])) {
            $data['is_painted'] = $validated['is_painted'];
        }

        if (! empty($data)) {
            $user->collectionMiniatures()->updateExistingPivot($miniatureId, $data);
        }

        return back();
    }

    public function remove(Request $request)
    {
        $validated = $request->validate([
            'miniature_id' => 'required|exists:miniatures,id',
        ]);

        Auth::user()->collectionMiniatures()->detach($validated['miniature_id']);

        return back();
    }

    public function removeBulk(Request $request)
    {
        $validated = $request->validate([
            'miniature_ids' => 'required|array|min:1',
            'miniature_ids.*' => 'integer|exists:miniatures,id',
        ]);

        Auth::user()->collectionMiniatures()->detach($validated['miniature_ids']);

        return back();
    }

    public function updateStatusBulk(Request $request)
    {
        $validated = $request->validate([
            'miniature_ids' => 'required|array|min:1',
            'miniature_ids.*' => 'integer|exists:miniatures,id',
            'is_built' => 'nullable|boolean',
            'is_painted' => 'nullable|boolean',
        ]);

        $data = array_filter(
            ['is_built' => $validated['is_built'] ?? null, 'is_painted' => $validated['is_painted'] ?? null],
            fn ($v) => $v !== null,
        );

        if (empty($data)) {
            return back();
        }

        // Single UPDATE against the pivot — much cheaper than looping
        // updateExistingPivot when bulk-marking dozens of rows.
        DB::table('user_miniatures')
            ->where('user_id', Auth::id())
            ->whereIn('miniature_id', $validated['miniature_ids'])
            ->update($data);

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
    private function collectionPropsFor(User $user): array
    {
        return [
            ...$this->buildCoreCollectionData($user),
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
    private function buildCoreCollectionData(User $user): array
    {
        $ownedMiniatureIds = $user->collectionMiniatures()->pluck('miniatures.id')->toArray();

        $allCharacters = Character::standard()->with('miniatures:id,character_id')
            ->where('is_hidden', false)
            ->get();

        $ownedCharacters = $allCharacters->filter(function ($character) use ($ownedMiniatureIds) {
            return $character->miniatures->pluck('id')->intersect($ownedMiniatureIds)->isNotEmpty();
        });

        $factionStats = [];
        foreach (FactionEnum::cases() as $faction) {
            $total = $allCharacters->where('faction', $faction)->count();
            $owned = $ownedCharacters->where('faction', $faction)->count();
            $factionStats[] = [
                'faction' => $faction->value,
                'name' => $faction->label(),
                'color' => $faction->color(),
                'logo' => $faction->logo(),
                'total' => $total,
                'owned' => $owned,
                'percent' => $total > 0 ? round(($owned / $total) * 100, 1) : 0,
            ];
        }

        $collectionItems = $user->collectionMiniatures()
            ->with(['character.keywords', 'character.standardMiniatures'])
            ->get()
            ->map(function (Miniature $m) {
                $character = $m->character;

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
                    'quantity' => $m->pivot->quantity ?? 1,
                    'is_built' => (bool) ($m->pivot->is_built ?? false),
                    'is_painted' => (bool) ($m->pivot->is_painted ?? false),
                    'standard_miniature_id' => $character->standardMiniatures->first()?->id,
                ];
            });

        $totalMiniatures = $collectionItems->count();
        $builtCount = $collectionItems->where('is_built', '===', true)->count();
        $paintedCount = $collectionItems->where('is_painted', '===', true)->count();
        $ownedPackagesCount = $user->collectionPackages()->where('game_system', GameSystemEnum::Malifaux)->count();

        return [
            'collection' => $collectionItems,
            'faction_stats' => $factionStats,
            'totals' => [
                'characters' => $allCharacters->count(),
                'owned_characters' => $ownedCharacters->count(),
                'owned_miniatures' => (int) $user->collectionMiniatures()->sum('quantity'),
                'owned_packages' => $ownedPackagesCount,
                'percent' => $allCharacters->count() > 0
                    ? round(($ownedCharacters->count() / $allCharacters->count()) * 100, 1)
                    : 0,
                'built' => $builtCount,
                'painted' => $paintedCount,
                'built_percent' => $totalMiniatures > 0 ? round(($builtCount / $totalMiniatures) * 100, 1) : 0,
                'painted_percent' => $totalMiniatures > 0 ? round(($paintedCount / $totalMiniatures) * 100, 1) : 0,
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
        return $user->collectionPackages()
            ->where('game_system', GameSystemEnum::Malifaux)
            ->get()
            ->map(fn (Package $p) => [
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
            ])
            ->all();
    }
}
