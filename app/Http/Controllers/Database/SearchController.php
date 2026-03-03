<?php

namespace App\Http\Controllers\Database;

use App\Enums\ActionRangeTypeEnum;
use App\Enums\ActionTypeEnum;
use App\Enums\BaseSizeEnum;
use App\Enums\CharacterSortOptionsEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\DefensiveAbilityTypeEnum;
use App\Enums\FactionEnum;
use App\Enums\ModifierTypeEnum;
use App\Enums\PageViewOptionsEnum;
use App\Enums\ResistanceTypeEnum;
use App\Enums\SortTypeEnum;
use App\Enums\SuitEnum;
use App\Http\Controllers\Controller;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Character;
use App\Models\Characteristic;
use App\Models\Keyword;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function view(Request $request)
    {
        // Conditional eager loading based on view mode
        $pageView = $request->get('page_view', 'images');
        $eagerLoads = match ($pageView) {
            'full' => ['standardMiniatures', 'miniatures', 'keywords', 'crewUpgrades', 'totem.standardMiniatures', 'isTotemFor.standardMiniatures'],
            default => ['standardMiniatures'],
        };

        $query = Character::with($eagerLoads)
            ->whereHas('standardMiniatures');

        // Text search across name, display_name, nicknames
        if ($request->filled('name')) {
            $search = $request->get('name');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('display_name', 'LIKE', "%{$search}%")
                    ->orWhere('nicknames', 'LIKE', "%{$search}%");
            });
        }

        // Faction multi-select (comma-separated values, matches either faction or second_faction)
        if ($request->filled('faction')) {
            $factions = array_filter(explode(',', $request->get('faction')));
            if (count($factions) > 0) {
                $query->where(function ($q) use ($factions) {
                    $q->whereIn('faction', $factions)
                        ->orWhereIn('second_faction', $factions);
                });
            }
        }

        if ($request->filled('station')) {
            $query->where('station', $request->get('station'));
        }

        if ($request->filled('base')) {
            $query->where('base', $request->get('base'));
        }

        if ($request->filled('defense_suit')) {
            $query->where('defense_suit', $request->get('defense_suit'));
        }

        if ($request->filled('willpower_suit')) {
            $query->where('willpower_suit', $request->get('willpower_suit'));
        }

        // Numeric range filters
        $rangeFields = ['cost', 'health', 'speed', 'defense', 'willpower', 'size'];
        foreach ($rangeFields as $field) {
            if ($request->filled("{$field}_min")) {
                $query->where($field, '>=', (int) $request->get("{$field}_min"));
            }
            if ($request->filled("{$field}_max")) {
                $query->where($field, '<=', (int) $request->get("{$field}_max"));
            }
        }

        // Boolean filters (three-state: absent=any, 'true', 'false')
        $booleanFields = ['generates_stone', 'is_unhirable', 'is_beta'];
        foreach ($booleanFields as $field) {
            if ($request->filled($field)) {
                $query->where($field, filter_var($request->get($field), FILTER_VALIDATE_BOOLEAN));
            }
        }

        // Relationship filters
        if ($request->filled('keyword')) {
            $query->whereHas('keywords', function ($q) use ($request) {
                $q->where('slug', $request->get('keyword'));
            });
        }

        if ($request->filled('characteristic')) {
            $query->whereHas('characteristics', function ($q) use ($request) {
                $q->where('slug', $request->get('characteristic'));
            });
        }

        // Action filters (grouped in a single whereHas so all conditions match the same action)
        $actionFilters = [
            'action', 'action_name', 'action_type', 'action_is_signature', 'action_costs_stone',
            'action_range_min', 'action_range_max', 'action_range_type',
            'action_stat_min', 'action_stat_max', 'action_stat_suits', 'action_stat_modifier',
            'action_resisted_by', 'action_tn_min', 'action_tn_max', 'action_target_suits',
            'action_damage', 'action_description',
        ];
        if ($request->hasAny($actionFilters) && collect($actionFilters)->contains(fn ($f) => $request->filled($f))) {
            $query->whereHas('actions', function ($q) use ($request) {
                if ($request->filled('action')) {
                    $q->where('name', $request->get('action'));
                }
                if ($request->filled('action_name')) {
                    $q->where('name', 'LIKE', '%'.$request->get('action_name').'%');
                }
                if ($request->filled('action_type')) {
                    $q->where('type', $request->get('action_type'));
                }
                if ($request->filled('action_is_signature')) {
                    $q->where('is_signature', filter_var($request->get('action_is_signature'), FILTER_VALIDATE_BOOLEAN));
                }
                if ($request->filled('action_costs_stone')) {
                    $q->where('costs_stone', filter_var($request->get('action_costs_stone'), FILTER_VALIDATE_BOOLEAN));
                }
                if ($request->filled('action_range_min')) {
                    $q->where('range', '>=', (int) $request->get('action_range_min'));
                }
                if ($request->filled('action_range_max')) {
                    $q->where('range', '<=', (int) $request->get('action_range_max'));
                }
                if ($request->filled('action_range_type')) {
                    $q->where('range_type', $request->get('action_range_type'));
                }
                if ($request->filled('action_stat_min')) {
                    $q->where('stat', '>=', (int) $request->get('action_stat_min'));
                }
                if ($request->filled('action_stat_max')) {
                    $q->where('stat', '<=', (int) $request->get('action_stat_max'));
                }
                if ($request->filled('action_stat_suits')) {
                    $q->where('stat_suits', 'LIKE', '%'.$request->get('action_stat_suits').'%');
                }
                if ($request->filled('action_stat_modifier')) {
                    $q->where('stat_modifier', $request->get('action_stat_modifier'));
                }
                if ($request->filled('action_resisted_by')) {
                    $q->where('resisted_by', $request->get('action_resisted_by'));
                }
                if ($request->filled('action_tn_min')) {
                    $q->where('target_number', '>=', (int) $request->get('action_tn_min'));
                }
                if ($request->filled('action_tn_max')) {
                    $q->where('target_number', '<=', (int) $request->get('action_tn_max'));
                }
                if ($request->filled('action_target_suits')) {
                    $q->where('target_suits', 'LIKE', '%'.$request->get('action_target_suits').'%');
                }
                if ($request->filled('action_damage')) {
                    $q->where('damage', 'LIKE', '%'.$request->get('action_damage').'%');
                }
                if ($request->filled('action_description')) {
                    $q->where('description', 'LIKE', '%'.$request->get('action_description').'%');
                }
            });
        }

        // Ability filters (grouped in a single whereHas so all conditions match the same ability)
        $abilityFilters = ['ability', 'ability_name', 'ability_suits', 'ability_defensive_type', 'ability_costs_stone', 'ability_description'];
        if ($request->hasAny($abilityFilters) && collect($abilityFilters)->contains(fn ($f) => $request->filled($f))) {
            $query->whereHas('abilities', function ($q) use ($request) {
                if ($request->filled('ability')) {
                    $q->where('name', $request->get('ability'));
                }
                if ($request->filled('ability_name')) {
                    $q->where('name', 'LIKE', '%'.$request->get('ability_name').'%');
                }
                if ($request->filled('ability_suits')) {
                    $q->where('suits', 'LIKE', '%'.$request->get('ability_suits').'%');
                }
                if ($request->filled('ability_defensive_type')) {
                    $q->where('defensive_ability_type', $request->get('ability_defensive_type'));
                }
                if ($request->filled('ability_costs_stone')) {
                    $q->where('costs_stone', filter_var($request->get('ability_costs_stone'), FILTER_VALIDATE_BOOLEAN));
                }
                if ($request->filled('ability_description')) {
                    $q->where('description', 'LIKE', '%'.$request->get('ability_description').'%');
                }
            });
        }

        // Sorting
        $sort = match ($request->get('sort')) {
            CharacterSortOptionsEnum::Cost->value => 'cost',
            CharacterSortOptionsEnum::Health->value => 'health',
            CharacterSortOptionsEnum::Speed->value => 'speed',
            CharacterSortOptionsEnum::Defense->value => 'defense',
            CharacterSortOptionsEnum::Willpower->value => 'willpower',
            CharacterSortOptionsEnum::Size->value => 'size',
            CharacterSortOptionsEnum::BaseSize->value => 'base',
            default => 'display_name',
        };

        $sortType = match ($request->get('sort_type')) {
            SortTypeEnum::Descending->value => 'DESC',
            default => 'ASC',
        };

        // Per-page varies by view mode
        $perPage = match ($pageView) {
            'table' => 50,
            'full' => 10,
            default => 24,
        };

        $characters = $query->orderBy($sort, $sortType)->paginate($perPage)->withQueryString();

        return inertia('Search/View', [
            'characters' => $characters,
            'result_count' => $characters->total(),
            'factions' => fn () => FactionEnum::toSelectOptions(),
            'stations' => fn () => CharacterStationEnum::toSelectOptions(),
            'suits' => fn () => SuitEnum::toSelectOptions(),
            'base_sizes' => fn () => BaseSizeEnum::toSelectOptions(),
            'keywords' => fn () => Keyword::orderBy('name', 'ASC')->get(),
            'characteristics' => fn () => Characteristic::orderBy('name', 'ASC')->get(),
            'actions' => fn () => Action::select('name')->distinct()->orderBy('name', 'ASC')->get()->map(fn ($a) => ['name' => $a->name, 'value' => $a->name]),
            'abilities' => fn () => Ability::select('name')->distinct()->orderBy('name', 'ASC')->get()->map(fn ($a) => ['name' => $a->name, 'value' => $a->name]),
            'action_types' => fn () => ActionTypeEnum::toSelectOptions(),
            'action_range_types' => fn () => ActionRangeTypeEnum::toSelectOptions(),
            'stat_modifiers' => fn () => ModifierTypeEnum::toSelectOptions(),
            'resistance_types' => fn () => ResistanceTypeEnum::toSelectOptions(),
            'defensive_ability_types' => fn () => DefensiveAbilityTypeEnum::toSelectOptions(),
            'sort_options' => fn () => CharacterSortOptionsEnum::toSelectOptions(),
            'sort_types' => fn () => SortTypeEnum::toSelectOptions(),
            'view_options' => fn () => PageViewOptionsEnum::toSelectOptions(),
        ]);
    }
}
