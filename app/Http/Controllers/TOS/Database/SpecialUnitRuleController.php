<?php

namespace App\Http\Controllers\TOS\Database;

use App\Http\Controllers\Controller;
use App\Models\TOS\SpecialUnitRule;
use Illuminate\Http\Request;

class SpecialUnitRuleController extends Controller
{
    public function index(Request $request)
    {
        $nameSearch = $request->filled('name_search') ? trim((string) $request->get('name_search')) : null;

        $query = SpecialUnitRule::orderBy('sort_order')->orderBy('name');
        if ($nameSearch) {
            $query->where('name', 'LIKE', "%{$nameSearch}%");
        }

        return inertia('TOS/SpecialRules/Index', [
            'rules' => $query->get(),
            'name_search' => $nameSearch,
        ]);
    }
}
