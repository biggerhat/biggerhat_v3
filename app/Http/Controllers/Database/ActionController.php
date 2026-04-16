<?php

namespace App\Http\Controllers\Database;

use App\Enums\ActionRangeTypeEnum;
use App\Enums\ActionTypeEnum;
use App\Enums\ModifierTypeEnum;
use App\Enums\ResistanceTypeEnum;
use App\Enums\SuitEnum;
use App\Http\Controllers\Controller;
use App\Models\Action;
use App\Models\Trigger;
use Illuminate\Http\Request;

class ActionController extends Controller
{
    public function index(Request $request)
    {
        $query = Action::standard()->with('triggers')
            ->withCount('characters')
            ->with(['characters' => fn ($q) => $q->select('characters.id', 'characters.display_name', 'characters.slug', 'characters.faction')->with('standardMiniatures:id,slug,character_id')->limit(2)])
            ->with(['upgrades' => fn ($q) => $q->select('upgrades.id', 'upgrades.name', 'upgrades.slug')->limit(3)]);

        // Name select (exact match)
        if ($request->filled('name')) {
            $query->where('name', $request->get('name'));
        }

        // Name text search (LIKE)
        if ($request->filled('name_search')) {
            $query->where('name', 'LIKE', '%'.$request->get('name_search').'%');
        }

        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }

        // Boolean filters
        if ($request->filled('is_signature')) {
            $query->where('is_signature', filter_var($request->get('is_signature'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('costs_stone')) {
            $costsStone = filter_var($request->get('costs_stone'), FILTER_VALIDATE_BOOLEAN);
            $query->where('stone_cost', $costsStone ? '>' : '=', 0);
        }

        // Range (numeric min/max)
        if ($request->filled('range_min')) {
            $query->where('range', '>=', (int) $request->get('range_min'));
        }
        if ($request->filled('range_max')) {
            $query->where('range', '<=', (int) $request->get('range_max'));
        }

        if ($request->filled('range_type')) {
            $query->where('range_type', $request->get('range_type'));
        }

        // Stat (numeric min/max)
        if ($request->filled('stat_min')) {
            $query->where('stat', '>=', (int) $request->get('stat_min'));
        }
        if ($request->filled('stat_max')) {
            $query->where('stat', '<=', (int) $request->get('stat_max'));
        }

        if ($request->filled('stat_suits')) {
            $query->where('stat_suits', 'LIKE', '%'.$request->get('stat_suits').'%');
        }

        if ($request->filled('stat_modifier')) {
            $query->where('stat_modifier', $request->get('stat_modifier'));
        }

        if ($request->filled('resisted_by')) {
            $query->where('resisted_by', $request->get('resisted_by'));
        }

        // Target number (numeric min/max)
        if ($request->filled('tn_min')) {
            $query->where('target_number', '>=', (int) $request->get('tn_min'));
        }
        if ($request->filled('tn_max')) {
            $query->where('target_number', '<=', (int) $request->get('tn_max'));
        }

        if ($request->filled('target_suits')) {
            $query->where('target_suits', 'LIKE', '%'.$request->get('target_suits').'%');
        }

        if ($request->filled('damage')) {
            $query->where('damage', 'LIKE', '%'.$request->get('damage').'%');
        }

        if ($request->filled('description')) {
            $query->where('description', 'LIKE', '%'.$request->get('description').'%');
        }

        if ($request->filled('trigger')) {
            $query->whereHas('triggers', fn ($q) => $q->where('name', $request->get('trigger')));
        }

        $pageView = $request->get('page_view', 'cards');
        $perPage = $pageView === 'table' ? 50 : 24;

        $actions = $query->orderBy('name', 'ASC')->paginate($perPage)->withQueryString();

        return inertia('Actions/Index', [
            'actions' => $actions,
            'result_count' => $actions->total(),
            'action_names' => fn () => Action::standard()->select('name')->distinct()->orderBy('name', 'ASC')->get()->map(fn ($a) => ['name' => $a->name, 'value' => $a->name]),
            'action_types' => fn () => ActionTypeEnum::toSelectOptions(),
            'action_range_types' => fn () => ActionRangeTypeEnum::toSelectOptions(),
            'suits' => fn () => SuitEnum::toSelectOptions(),
            'stat_modifiers' => fn () => ModifierTypeEnum::toSelectOptions(),
            'resistance_types' => fn () => ResistanceTypeEnum::toSelectOptions(),
            'trigger_names' => fn () => Trigger::select('name')->distinct()->orderBy('name', 'ASC')
                ->get()->map(fn ($t) => ['name' => $t->name, 'value' => $t->name]),
        ]);
    }
}
