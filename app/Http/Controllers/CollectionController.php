<?php

namespace App\Http\Controllers;

use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\Package;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

        $data = $this->buildCollectionData($user);

        return inertia('Collection/Index', [
            ...$data,
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

        $data = $this->buildCollectionData($user);

        return inertia('Collection/Index', [
            ...$data,
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

        $miniatureIds = $character->standardMiniatures->pluck('id');
        $existing = $user->collectionMiniatures()->whereIn('miniature_id', $miniatureIds)->pluck('miniature_id');
        $toAttach = $miniatureIds->diff($existing)->mapWithKeys(fn ($id) => [$id => ['quantity' => 1]]);

        if ($toAttach->isNotEmpty()) {
            $user->collectionMiniatures()->attach($toAttach);
        }

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

    public function remove(Request $request)
    {
        $validated = $request->validate([
            'miniature_id' => 'required|exists:miniatures,id',
        ]);

        Auth::user()->collectionMiniatures()->detach($validated['miniature_id']);

        return back();
    }

    /**
     * Build collection data for a given user.
     *
     * @return array<string, mixed>
     */
    private function buildCollectionData(User $user): array
    {
        $ownedMiniatureIds = $user->collectionMiniatures()->pluck('miniatures.id')->toArray();
        $ownedPackageIds = $user->collectionPackages()->pluck('packages.id')->toArray();

        $allCharacters = Character::with('miniatures:id,character_id')
            ->where('is_hidden', false)
            ->get();

        $ownedCharacters = $allCharacters->filter(function ($character) use ($ownedMiniatureIds) {
            return $character->miniatures->pluck('id')->intersect($ownedMiniatureIds)->isNotEmpty();
        });

        // Faction stats
        $factionStats = [];
        foreach (FactionEnum::cases() as $faction) {
            $factionChars = $allCharacters->where('faction', $faction);
            $ownedFactionChars = $ownedCharacters->where('faction', $faction);
            $total = $factionChars->count();
            $owned = $ownedFactionChars->count();
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

        // Keyword stats — eager load characters to avoid N+1
        $keywordStats = [];
        $ownedCharacterIds = $ownedCharacters->pluck('id');
        $allKeywords = Keyword::with('characters:id')->withCount('characters')->orderBy('name')->get();
        foreach ($allKeywords as $keyword) {
            if ($keyword->characters_count > 0) {
                $ownedCount = $keyword->characters->whereIn('id', $ownedCharacterIds)->count();
                $keywordStats[] = [
                    'name' => $keyword->name,
                    'slug' => $keyword->slug,
                    'total' => $keyword->characters_count,
                    'owned' => $ownedCount,
                    'percent' => round(($ownedCount / $keyword->characters_count) * 100, 1),
                ];
            }
        }

        // Station breakdown
        $stationStats = [];
        foreach (CharacterStationEnum::cases() as $station) {
            $total = $allCharacters->where('station', $station)->count();
            $owned = $ownedCharacters->where('station', $station)->count();
            if ($total > 0) {
                $stationStats[] = [
                    'station' => $station->value,
                    'name' => $station->label(),
                    'total' => $total,
                    'owned' => $owned,
                    'percent' => round(($owned / $total) * 100, 1),
                ];
            }
        }

        // Collection items
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
                    'standard_miniature_id' => $character->standardMiniatures->first()?->id,
                ];
            });

        // Owned packages
        $ownedPackages = Package::whereIn('id', $ownedPackageIds)
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
            ]);

        return [
            'collection' => $collectionItems,
            'owned_packages' => $ownedPackages,
            'faction_stats' => $factionStats,
            'keyword_stats' => $keywordStats,
            'station_stats' => $stationStats,
            'totals' => [
                'characters' => $allCharacters->count(),
                'owned_characters' => $ownedCharacters->count(),
                'owned_miniatures' => (int) $user->collectionMiniatures()->sum('quantity'),
                'owned_packages' => count($ownedPackageIds),
                'percent' => $allCharacters->count() > 0
                    ? round(($ownedCharacters->count() / $allCharacters->count()) * 100, 1)
                    : 0,
            ],
            'factions' => FactionEnum::buildDetails(),
        ];
    }
}
