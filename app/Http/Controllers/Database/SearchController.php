<?php

namespace App\Http\Controllers\Database;

use App\Enums\BaseSizeEnum;
use App\Enums\CharacterSortOptionsEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\PageViewOptionsEnum;
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

        if ($request->filled('action')) {
            $query->whereHas('actions', function ($q) use ($request) {
                $q->where('name', $request->get('action'));
            });
        }

        if ($request->filled('ability')) {
            $query->whereHas('abilities', function ($q) use ($request) {
                $q->where('name', $request->get('ability'));
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
            'sort_options' => fn () => CharacterSortOptionsEnum::toSelectOptions(),
            'sort_types' => fn () => SortTypeEnum::toSelectOptions(),
            'view_options' => fn () => PageViewOptionsEnum::toSelectOptions(),
        ]);
    }
}
