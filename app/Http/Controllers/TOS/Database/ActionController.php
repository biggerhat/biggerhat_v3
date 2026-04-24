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

        $query = Action::with('triggers', 'typeLinks')->orderBy('name');
        if ($nameSearch) {
            $query->where('name', 'LIKE', "%{$nameSearch}%");
        }

        return inertia('TOS/Actions/Index', [
            'actions' => $query->get(),
            'name_search' => $nameSearch,
            'action_types' => ActionTypeEnum::toSelectOptions(),
        ]);
    }
}
