<?php

namespace App\Http\Controllers\TOS\Database;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\TOS\Allegiance;
use Illuminate\Http\Request;

class AllegianceController extends Controller
{
    public function index(Request $request)
    {
        $nameSearch = $request->filled('name_search') ? trim((string) $request->get('name_search')) : null;
        $pageView = $request->get('page_view', 'cards');
        $perPage = $pageView === 'table' ? 50 : 24;

        $query = Allegiance::query()
            ->orderBy('is_syndicate')
            ->orderBy('sort_order')
            ->orderBy('name');
        if ($nameSearch) {
            $query->where('name', 'LIKE', "%{$nameSearch}%");
        }

        return inertia('TOS/Allegiances/Index', [
            'allegiances' => $query->paginate($perPage)->withQueryString(),
            'name_search' => $nameSearch,
            'page_view' => $pageView,
            'allegiance_types' => AllegianceTypeEnum::toSelectOptions(),
        ]);
    }

    public function view(Allegiance $allegiance)
    {
        // Allegiance page lists only units directly attached via the
        // tos_allegiance_unit pivot — the cross-type Neutral pool stays
        // out of the per-allegiance roster (browse it via the Units index
        // or surface it in the crew builder).
        $units = $allegiance->units()
            ->with(['sides:id,unit_id,side,speed,defense,willpower,armor', 'sculpts', 'specialUnitRules:id,name,slug', 'allegiances:id,slug'])
            ->orderBy('scrip')
            ->orderBy('name')
            ->get();

        return inertia('TOS/Allegiances/View', [
            'allegiance' => $allegiance,
            'units' => $units,
        ]);
    }
}
