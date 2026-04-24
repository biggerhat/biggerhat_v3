<?php

namespace App\Http\Controllers\TOS\Database;

use App\Enums\TOS\ActionTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\TOS\Action;
use Illuminate\Http\Request;

class ActionController extends Controller
{
    public function index(Request $request)
    {
        $nameSearch = $request->filled('name_search') ? trim((string) $request->get('name_search')) : null;
        $pageView = $request->get('page_view', 'cards');
        $perPage = $pageView === 'table' ? 50 : 24;

        $query = Action::with('triggers', 'typeLinks')->orderBy('name');
        if ($nameSearch) {
            $query->where('name', 'LIKE', "%{$nameSearch}%");
        }

        return inertia('TOS/Actions/Index', [
            'actions' => $query->paginate($perPage)->withQueryString(),
            'name_search' => $nameSearch,
            'page_view' => $pageView,
            'action_types' => ActionTypeEnum::toSelectOptions(),
        ]);
    }
}
