<?php

namespace App\Http\Controllers\TOS\Database;

use App\Http\Controllers\Controller;
use App\Models\TOS\Envoy;
use Illuminate\Http\Request;

class EnvoyController extends Controller
{
    public function index(Request $request)
    {
        $nameSearch = $request->filled('name_search') ? trim((string) $request->get('name_search')) : null;

        $query = Envoy::with('allegiance:id,name,slug,is_syndicate,type', 'abilities')->orderBy('name');
        if ($nameSearch) {
            $query->where('name', 'LIKE', "%{$nameSearch}%");
        }

        return inertia('TOS/Envoys/Index', [
            'envoys' => $query->get(),
            'name_search' => $nameSearch,
        ]);
    }

    public function view(Envoy $envoy)
    {
        $envoy->load('allegiance', 'abilities');

        return inertia('TOS/Envoys/View', [
            'envoy' => $envoy,
        ]);
    }
}
