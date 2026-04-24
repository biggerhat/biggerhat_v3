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

        $query = Allegiance::query()
            ->orderBy('is_syndicate')
            ->orderBy('sort_order')
            ->orderBy('name');
        if ($nameSearch) {
            $query->where('name', 'LIKE', "%{$nameSearch}%");
        }

        return inertia('TOS/Allegiances/Index', [
            'allegiances' => $query->get(),
            'name_search' => $nameSearch,
            'allegiance_types' => AllegianceTypeEnum::toSelectOptions(),
        ]);
    }

    public function view(Allegiance $allegiance)
    {
        return inertia('TOS/Allegiances/View', [
            'allegiance' => $allegiance,
        ]);
    }
}
