<?php

namespace App\Http\Controllers\TOS\Database;

use App\Http\Controllers\Controller;
use App\Models\TOS\Asset;
use App\Models\TOS\Unit;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $nameSearch = $request->filled('name_search') ? trim((string) $request->get('name_search')) : null;
        $pageView = $request->get('page_view', 'cards');
        $perPage = $pageView === 'table' ? 50 : 24;

        // Asset Index card/table view consumes only name, scrip_cost, limits,
        // and allegiances. Body + abilities + actions are loaded on the view
        // page only — slim payload keeps the list responsive.
        $query = Asset::query()
            ->select(['id', 'slug', 'name', 'scrip_cost', 'image_path', 'sort_order'])
            ->with('allegiances:id,slug,name', 'limits')
            ->orderBy('name');
        if ($nameSearch) {
            $query->where('name', 'LIKE', "%{$nameSearch}%");
        }

        return inertia('TOS/Assets/Index', [
            'assets' => $query->paginate($perPage)->withQueryString(),
            'name_search' => $nameSearch,
            'page_view' => $pageView,
        ]);
    }

    public function view(Asset $asset)
    {
        $asset->load(['allegiances', 'limits.parameterUnit', 'limits.parameterAllegiance', 'abilities', 'actions.triggers', 'actions.typeLinks']);

        // Compatible units — every Unit the rules say can carry this Asset.
        // We pre-filter to units that share at least one of the Asset's
        // allegiances (or any unit when the Asset is universal) so we don't
        // walk the entire roster, then apply the Asset's per-limit checks
        // via the model helper.
        $candidateQuery = Unit::query()
            ->notCombinedArmsChild()
            ->with(['specialUnitRules:id,slug,name', 'allegiances:id,name,slug', 'sculpts:id,unit_id,slug,combination_image,front_image']);

        if ($asset->allegiances->isNotEmpty()) {
            $allegianceIds = $asset->allegiances->pluck('id');
            $candidateQuery->whereHas('allegiances', fn ($q) => $q->whereIn('tos_allegiances.id', $allegianceIds));
        }

        $compatibleUnits = $candidateQuery
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'title', 'scrip'])
            ->filter(fn (Unit $u) => $asset->canAttachTo($u))
            ->values();

        return inertia('TOS/Assets/View', [
            'asset' => $asset,
            'compatible_units' => $compatibleUnits,
        ]);
    }
}
