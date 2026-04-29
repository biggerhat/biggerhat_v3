<?php

namespace App\Http\Controllers\TOS\Database;

use App\Http\Controllers\Controller;
use App\Models\TOS\Ability;
use Illuminate\Http\Request;

class AbilityController extends Controller
{
    public function index(Request $request)
    {
        $nameSearch = $request->filled('name_search') ? trim((string) $request->get('name_search')) : null;
        $pageView = $request->get('page_view', 'cards');
        $perPage = $pageView === 'table' ? 50 : 24;

        // Surface a usage count so the index page can show "used by N unit
        // sides" — counts pivot rows; a unit with the ability on both
        // Standard and Glory contributes 2.
        $query = Ability::query()
            ->withCount('unitSides')
            ->orderBy('name');
        if ($nameSearch) {
            $query->where('name', 'LIKE', "%{$nameSearch}%");
        }

        return inertia('TOS/Abilities/Index', [
            'abilities' => $query->paginate($perPage)->withQueryString(),
            'name_search' => $nameSearch,
            'page_view' => $pageView,
        ]);
    }
}
