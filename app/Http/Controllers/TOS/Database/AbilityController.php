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

        $query = Ability::with('allegiance')->orderBy('name');
        if ($nameSearch) {
            $query->where('name', 'LIKE', "%{$nameSearch}%");
        }

        return inertia('TOS/Abilities/Index', [
            'abilities' => $query->get(),
            'name_search' => $nameSearch,
        ]);
    }
}
