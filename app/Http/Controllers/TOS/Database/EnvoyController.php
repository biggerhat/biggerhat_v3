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
        $pageView = $request->get('page_view', 'cards');
        $perPage = $pageView === 'table' ? 50 : 24;

        // secondary_type included so any code path that calls
        // Envoy::hireableInto($envoy->allegiance) (which reads types())
        // doesn't trip strict-attribute mode. Same defensive pattern as
        // CompanyController::view.
        $query = Envoy::with('allegiance:id,name,slug,is_syndicate,type,secondary_type', 'abilities')->orderBy('name');
        if ($nameSearch) {
            $query->where('name', 'LIKE', "%{$nameSearch}%");
        }

        return inertia('TOS/Envoys/Index', [
            'envoys' => $query->paginate($perPage)->withQueryString(),
            'name_search' => $nameSearch,
            'page_view' => $pageView,
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
