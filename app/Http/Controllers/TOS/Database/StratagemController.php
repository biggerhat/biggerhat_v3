<?php

namespace App\Http\Controllers\TOS\Database;

use App\Http\Controllers\Controller;
use App\Models\TOS\Stratagem;
use Illuminate\Http\Request;

class StratagemController extends Controller
{
    public function index(Request $request)
    {
        $nameSearch = $request->filled('name_search') ? trim((string) $request->get('name_search')) : null;

        $query = Stratagem::with('allegiance:id,name,slug')->orderBy('name');
        if ($nameSearch) {
            $query->where('name', 'LIKE', "%{$nameSearch}%");
        }

        return inertia('TOS/Stratagems/Index', [
            'stratagems' => $query->get(),
            'name_search' => $nameSearch,
        ]);
    }

    public function view(Stratagem $stratagem)
    {
        $stratagem->load('allegiance');

        return inertia('TOS/Stratagems/View', [
            'stratagem' => $stratagem,
        ]);
    }
}
