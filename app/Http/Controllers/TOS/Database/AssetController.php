<?php

namespace App\Http\Controllers\TOS\Database;

use App\Http\Controllers\Controller;
use App\Models\TOS\Asset;
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

        return inertia('TOS/Assets/View', [
            'asset' => $asset,
        ]);
    }
}
