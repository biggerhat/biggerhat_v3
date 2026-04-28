<?php

namespace App\Http\Controllers\TOS\Database;

use App\Http\Controllers\Controller;
use App\Models\TOS\AllegianceCard;
use Illuminate\Http\Request;

class AllegianceCardController extends Controller
{
    public function index(Request $request)
    {
        $nameSearch = $request->filled('name_search') ? trim((string) $request->get('name_search')) : null;
        $pageView = $request->get('page_view', 'cards');
        $perPage = $pageView === 'table' ? 50 : 24;

        // secondary_type is loaded so consumers can call $card->allegiance->types()
        // without tripping strict-attribute mode (mirrors CompanyController::view).
        $query = AllegianceCard::with('allegiance:id,name,slug,color_slug,type,secondary_type', 'abilities')->orderBy('name');
        if ($nameSearch) {
            $query->where('name', 'LIKE', "%{$nameSearch}%");
        }

        return inertia('TOS/AllegianceCards/Index', [
            'cards' => $query->paginate($perPage)->withQueryString(),
            'name_search' => $nameSearch,
            'page_view' => $pageView,
        ]);
    }

    public function view(AllegianceCard $card)
    {
        // Eager-load both tiers — Standard and Primary each carry their own
        // ability/action/trigger lists per the new card layout.
        $card->load([
            // Lock the allegiance select so a future slim doesn't drop secondary_type
            // and re-introduce the strict-attribute prod 500 from the Apr 28 incident.
            'allegiance:id,slug,name,type,secondary_type,color_slug',
            'abilities', 'actions.triggers', 'actions.typeLinks', 'triggers',
            'primaryAbilities', 'primaryActions.triggers', 'primaryActions.typeLinks', 'primaryTriggers',
        ]);

        return inertia('TOS/AllegianceCards/View', [
            'card' => $card,
        ]);
    }
}
