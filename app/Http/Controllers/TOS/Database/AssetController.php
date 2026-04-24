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

        $query = Asset::with('allegiances:id,name', 'limits', 'abilities:id,name', 'actions:id,name')->orderBy('name');
        if ($nameSearch) {
            $query->where('name', 'LIKE', "%{$nameSearch}%");
        }

        return inertia('TOS/Assets/Index', [
            'assets' => $query->get(),
            'name_search' => $nameSearch,
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
