<?php

namespace App\Http\Controllers\TOS;

use App\Enums\GameSystemEnum;
use App\Enums\TOS\AllegianceEnum;
use App\Http\Controllers\Controller;
use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;
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
        if (! $user->tos_collection_share_code) {
            $user->tos_collection_share_code = Str::random(12);
            $user->save();
        }

        return inertia('TOS/Collection/Index', [
            ...$this->collectionPropsFor($user),
            'is_owner' => true,
            'share_code' => $user->tos_collection_share_code,
            'is_public' => (bool) $user->tos_collection_is_public,
        ]);
    }

    public function share(string $shareCode)
    {
        $user = User::where('tos_collection_share_code', $shareCode)->firstOrFail();

        if (! $user->tos_collection_is_public && Auth::id() !== $user->id) {
            abort(403, 'This collection is private.');
        }

        return inertia('TOS/Collection/Index', [
            ...$this->collectionPropsFor($user),
            'is_owner' => Auth::id() === $user->id,
            'share_code' => $user->tos_collection_share_code,
            'is_public' => (bool) $user->tos_collection_is_public,
            'owner_name' => $user->name,
        ]);
    }

    public function togglePublic()
    {
        $user = Auth::user();
        $user->tos_collection_is_public = ! $user->tos_collection_is_public;

        if (! $user->tos_collection_share_code) {
            $user->tos_collection_share_code = Str::random(12);
        }

        $user->save();

        return back();
    }

    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'unit_sculpt_id' => 'required|exists:tos_unit_sculpts,id',
            'quantity' => 'nullable|integer|min:0',
        ]);

        $user = Auth::user();
        $sculptId = $validated['unit_sculpt_id'];
        $quantity = $validated['quantity'] ?? null;

        $existing = $user->collectionUnitSculpts()->where('unit_sculpt_id', $sculptId)->first();

        if ($quantity === 0) {
            $user->collectionUnitSculpts()->detach($sculptId);

            return back();
        }

        if ($existing) {
            if ($quantity !== null) {
                $user->collectionUnitSculpts()->updateExistingPivot($sculptId, ['quantity' => $quantity]);
            } else {
                $user->collectionUnitSculpts()->detach($sculptId);
            }
        } else {
            $user->collectionUnitSculpts()->attach($sculptId, ['quantity' => $quantity ?? 1]);
        }

        return back();
    }

    public function addUnit(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:tos_units,id',
        ]);

        $user = Auth::user();
        $unit = Unit::with('sculpts')->findOrFail($validated['unit_id']);

        DB::transaction(function () use ($user, $unit) {
            $sculpt = $unit->sculpts->sortBy('id')->first();
            if (! $sculpt) {
                return;
            }

            if (! $user->collectionUnitSculpts()->where('unit_sculpt_id', $sculpt->id)->exists()) {
                $user->collectionUnitSculpts()->attach($sculpt->id, ['quantity' => 1]);
            }
        });

        return back();
    }

    public function addUnits(Request $request)
    {
        $validated = $request->validate([
            'unit_ids' => 'required|array',
            'unit_ids.*' => 'integer|exists:tos_units,id',
        ]);

        $user = Auth::user();

        DB::transaction(function () use ($user, $validated) {
            $units = Unit::with('sculpts')->whereIn('id', $validated['unit_ids'])->get();
            $sculptIds = $units->map(fn (Unit $u) => $u->sculpts->sortBy('id')->first()?->id)->filter()->values();
            $existing = $user->collectionUnitSculpts()->whereIn('unit_sculpt_id', $sculptIds)->pluck('unit_sculpt_id');
            $toAttach = $sculptIds->diff($existing)->mapWithKeys(fn ($id) => [$id => ['quantity' => 1]]);

            if ($toAttach->isNotEmpty()) {
                $user->collectionUnitSculpts()->attach($toAttach);
            }
        });

        return back();
    }

    public function updateStatus(Request $request)
    {
        $validated = $request->validate([
            'unit_sculpt_id' => 'required|exists:tos_unit_sculpts,id',
            'is_built' => 'nullable|boolean',
            'is_painted' => 'nullable|boolean',
        ]);

        $user = Auth::user();
        $sculptId = $validated['unit_sculpt_id'];

        $data = [];
        if (isset($validated['is_built'])) {
            $data['is_built'] = $validated['is_built'];
        }
        if (isset($validated['is_painted'])) {
            $data['is_painted'] = $validated['is_painted'];
        }

        if (! empty($data)) {
            $user->collectionUnitSculpts()->updateExistingPivot($sculptId, $data);
        }

        return back();
    }

    public function removeBulk(Request $request)
    {
        $validated = $request->validate([
            'unit_sculpt_ids' => 'required|array|min:1',
            'unit_sculpt_ids.*' => 'integer|exists:tos_unit_sculpts,id',
        ]);

        Auth::user()->collectionUnitSculpts()->detach($validated['unit_sculpt_ids']);

        return back();
    }

    public function updateStatusBulk(Request $request)
    {
        $validated = $request->validate([
            'unit_sculpt_ids' => 'required|array|min:1',
            'unit_sculpt_ids.*' => 'integer|exists:tos_unit_sculpts,id',
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

        DB::table('user_unit_sculpts')
            ->where('user_id', Auth::id())
            ->whereIn('unit_sculpt_id', $validated['unit_sculpt_ids'])
            ->update($data);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function collectionPropsFor(User $user): array
    {
        return [
            ...$this->buildCoreCollectionData($user),
            'owned_packages' => Inertia::defer(fn () => $this->buildOwnedPackages($user), 'collection_extras'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCoreCollectionData(User $user): array
    {
        $ownedSculptIds = $user->collectionUnitSculpts()->pluck('tos_unit_sculpts.id')->toArray();

        $allUnits = Unit::notCombinedArmsChild()
            ->with(['sculpts:id,unit_id', 'allegiances:id,slug,name'])
            ->get();

        $ownedUnits = $allUnits->filter(function (Unit $unit) use ($ownedSculptIds) {
            return $unit->sculpts->pluck('id')->intersect($ownedSculptIds)->isNotEmpty();
        });

        $allegianceStats = [];
        foreach (AllegianceEnum::buildDetails() as $slug => $details) {
            $total = $allUnits->filter(fn (Unit $u) => $u->allegiances->contains('slug', $slug))->count();
            $owned = $ownedUnits->filter(fn (Unit $u) => $u->allegiances->contains('slug', $slug))->count();
            $allegianceStats[] = [
                'allegiance' => $slug,
                'name' => $details['name'],
                'color' => $details['color'],
                'logo' => $details['logo'],
                'total' => $total,
                'owned' => $owned,
                'percent' => $total > 0 ? round(($owned / $total) * 100, 1) : 0,
            ];
        }

        $collectionItems = $user->collectionUnitSculpts()
            ->with(['unit.allegiances'])
            ->get()
            ->map(function (UnitSculpt $s) {
                $unit = $s->unit;

                return [
                    'unit_sculpt_id' => $s->id,
                    'sculpt_name' => $s->name ?? $unit->name,
                    'sculpt_slug' => $s->slug,
                    'front_image' => $s->front_image,
                    'unit_id' => $unit->id,
                    'unit_name' => $unit->name,
                    'unit_slug' => $unit->slug,
                    'allegiances' => $unit->allegiances->map(fn ($a) => ['slug' => $a->slug, 'name' => $a->name])->toArray(),
                    'quantity' => $s->pivot->quantity ?? 1,
                    'is_built' => (bool) ($s->pivot->is_built ?? false),
                    'is_painted' => (bool) ($s->pivot->is_painted ?? false),
                ];
            });

        $totalSculpts = $collectionItems->count();
        $builtCount = $collectionItems->where('is_built', '===', true)->count();
        $paintedCount = $collectionItems->where('is_painted', '===', true)->count();
        $ownedPackagesCount = $user->collectionPackages()->whereIn('game_system', [GameSystemEnum::Tos, GameSystemEnum::Both])->count();

        return [
            'collection' => $collectionItems,
            'allegiance_stats' => $allegianceStats,
            'totals' => [
                'units' => $allUnits->count(),
                'owned_units' => $ownedUnits->count(),
                'owned_sculpts' => (int) $user->collectionUnitSculpts()->sum('quantity'),
                'owned_packages' => $ownedPackagesCount,
                'percent' => $allUnits->count() > 0
                    ? round(($ownedUnits->count() / $allUnits->count()) * 100, 1)
                    : 0,
                'built' => $builtCount,
                'painted' => $paintedCount,
                'built_percent' => $totalSculpts > 0 ? round(($builtCount / $totalSculpts) * 100, 1) : 0,
                'painted_percent' => $totalSculpts > 0 ? round(($paintedCount / $totalSculpts) * 100, 1) : 0,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildOwnedPackages(User $user): array
    {
        return $user->collectionPackages()
            ->whereIn('game_system', [GameSystemEnum::Tos, GameSystemEnum::Both])
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'front_image' => $p->front_image,
            ])
            ->all();
    }
}
