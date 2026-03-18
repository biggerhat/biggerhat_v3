<?php

namespace App\Http\Controllers\Database;

use App\Enums\SuitEnum;
use App\Http\Controllers\Controller;
use App\Models\Trigger;
use Illuminate\Http\Request;

class TriggerController extends Controller
{
    public function index(Request $request)
    {
        $query = Trigger::with('actions')
            ->withCount('actions')
            ->with(['actions.characters' => fn ($q) => $q->select('characters.id', 'characters.display_name', 'characters.slug', 'characters.faction')
                ->with('standardMiniatures:id,slug,character_id')->limit(2),
            ])
            ->with(['actions.upgrades' => fn ($q) => $q->select('upgrades.id', 'upgrades.name', 'upgrades.slug')->limit(3)]);

        if ($request->filled('name')) {
            $query->where('name', $request->get('name'));
        }

        if ($request->filled('name_search')) {
            $query->where('name', 'LIKE', '%'.$request->get('name_search').'%');
        }

        if ($request->filled('suits')) {
            $query->where('suits', 'LIKE', '%'.$request->get('suits').'%');
        }

        if ($request->filled('costs_stone')) {
            $costsStone = filter_var($request->get('costs_stone'), FILTER_VALIDATE_BOOLEAN);
            $query->where('stone_cost', $costsStone ? '>' : '=', 0);
        }

        if ($request->filled('description')) {
            $query->where('description', 'LIKE', '%'.$request->get('description').'%');
        }

        $pageView = $request->get('page_view', 'cards');
        $perPage = $pageView === 'table' ? 50 : 24;

        $triggers = $query->orderBy('name', 'ASC')->paginate($perPage)->withQueryString();

        return inertia('Triggers/Index', [
            'triggers' => $triggers,
            'result_count' => $triggers->total(),
            'trigger_names' => fn () => Trigger::select('name')->distinct()->orderBy('name', 'ASC')
                ->get()->map(fn ($t) => ['name' => $t->name, 'value' => $t->name]),
            'suits' => fn () => SuitEnum::toSelectOptions(),
        ]);
    }
}
