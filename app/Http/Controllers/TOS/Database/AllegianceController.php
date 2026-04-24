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
        // Unit::hireableInto returns units directly attached to this Allegiance
        // PLUS Neutral units with a matching `restriction` type (rulebook
        // "Neutral Earth" / "Neutral Malifaux" pool).
        $units = \App\Models\TOS\Unit::hireableInto($allegiance)
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
