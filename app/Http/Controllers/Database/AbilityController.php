<?php

namespace App\Http\Controllers\Database;

use App\Enums\DefensiveAbilityTypeEnum;
use App\Enums\SuitEnum;
use App\Http\Controllers\Controller;
use App\Models\Ability;
use Illuminate\Http\Request;

class AbilityController extends Controller
{
    public function index(Request $request)
    {
        $query = Ability::withCount('characters')
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

        if ($request->filled('suits')) {
            $query->where('suits', 'LIKE', '%'.$request->get('suits').'%');
        }

        if ($request->filled('defensive_ability_type')) {
            $query->where('defensive_ability_type', $request->get('defensive_ability_type'));
        }

        if ($request->filled('costs_stone')) {
            $query->where('costs_stone', filter_var($request->get('costs_stone'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('description')) {
            $query->where('description', 'LIKE', '%'.$request->get('description').'%');
        }

        $pageView = $request->get('page_view', 'cards');
        $perPage = $pageView === 'table' ? 50 : 24;

        $abilities = $query->orderBy('name', 'ASC')->paginate($perPage)->withQueryString();

        return inertia('Abilities/Index', [
            'abilities' => $abilities,
            'result_count' => $abilities->total(),
            'ability_names' => fn () => Ability::select('name')->distinct()->orderBy('name', 'ASC')->get()->map(fn ($a) => ['name' => $a->name, 'value' => $a->name]),
            'suits' => fn () => SuitEnum::toSelectOptions(),
            'defensive_ability_types' => fn () => DefensiveAbilityTypeEnum::toSelectOptions(),
        ]);
    }
}
