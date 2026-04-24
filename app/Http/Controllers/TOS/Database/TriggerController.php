<?php

namespace App\Http\Controllers\TOS\Database;

use App\Http\Controllers\Controller;
use App\Models\TOS\Trigger;
use Illuminate\Http\Request;

class TriggerController extends Controller
{
    public function index(Request $request)
    {
        $nameSearch = $request->filled('name_search') ? trim((string) $request->get('name_search')) : null;

        $query = Trigger::with('action')->orderBy('name');
        if ($nameSearch) {
            $query->where('name', 'LIKE', "%{$nameSearch}%");
        }

        return inertia('TOS/Triggers/Index', [
            'triggers' => $query->get(),
            'name_search' => $nameSearch,
        ]);
    }
}
