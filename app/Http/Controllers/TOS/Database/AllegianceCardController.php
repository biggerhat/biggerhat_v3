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

        $query = AllegianceCard::with('allegiance:id,name,slug,color_slug,type', 'abilities')->orderBy('name');
        if ($nameSearch) {
            $query->where('name', 'LIKE', "%{$nameSearch}%");
        }

        return inertia('TOS/AllegianceCards/Index', [
            'cards' => $query->get(),
            'name_search' => $nameSearch,
        ]);
    }

    public function view(AllegianceCard $card)
    {
        $card->load('allegiance', 'abilities');

        return inertia('TOS/AllegianceCards/View', [
            'card' => $card,
        ]);
    }
}
