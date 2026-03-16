<?php

namespace App\Http\Controllers\Database;

use App\Enums\CharacterSortOptionsEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\PageViewOptionsEnum;
use App\Enums\SortTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\KeywordResource;
use App\Models\Character;
use App\Models\Characteristic;
use App\Models\Keyword;
use Illuminate\Http\Request;

class KeywordController extends Controller
{
    public function index(Request $request)
    {
        $keywords = Keyword::orderBy('name', 'ASC')->with(['masters.crewUpgrades', 'characters'])->get();

        return inertia('Keywords/Index', [
            'keywords' => KeywordResource::collection($keywords)->toArray($request),
        ]);
    }

    public function view(Request $request, Keyword $keyword)
    {
        $query = Character::with('keywords', 'standardMiniatures', 'miniatures', 'characteristics', 'crewUpgrades', 'totem.standardMiniatures', 'isTotemFor.standardMiniatures', 'actions.triggers')
            ->whereHas('standardMiniatures')
            ->whereHas('keywords', function ($query) use ($keyword) {
                $query->where('slug', $keyword->slug);
            });

        //        $factions = Keyword::whereHas('characters', function ($query) use ($factionEnum) {
        //            $query->where('faction', $factionEnum->value);
        //        })->orderBy('name', 'ASC')->get();

        //        $characteristics = Characteristic::whereHas('characters', function ($query) use ($factionEnum) {
        //            $query->where('faction', $factionEnum->value);
        //        })->orderBy('name', 'ASC')->get();

        if ($request->get('faction')) {
            $query->where('faction', $request->get('faction'));
        }

        if ($request->get('station')) {
            $query->where('station', $request->get('station'));
        }

        if ($request->get('characteristic')) {
            $query->whereHas('characteristics', function ($query) use ($request) {
                $query->where('slug', $request->get('characteristic'));
            });
        }

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

        $characters = $query->orderBy($sort, $sortType)->get();

        $factions = $characters->pluck('faction')->flatten()->unique()->map(function (FactionEnum $faction) {
            return [
                'value' => $faction,
                'name' => $faction->label(),
            ];
        });

        $characteristics = $characters->pluck('characteristics')->flatten(2)->unique('id');

        $masters = $characters->where('station', CharacterStationEnum::Master)->values();
        $nonMasters = $characters->reject(fn ($c) => $c->station === CharacterStationEnum::Master)->values();

        $keywordBreakdown = [
            'keyword' => $keyword,
            'masters' => $masters,
            'characters' => $nonMasters,
        ];

        $totalUnique = $characters->whereNull('station')->count();

        $suitCounts = [];
        foreach ($characters as $character) {
            foreach ($character->actions as $action) {
                foreach ($action->triggers as $trigger) {
                    if ($trigger->suits) {
                        $suit = strtolower($trigger->suits);
                        $suitCounts[$suit] = ($suitCounts[$suit] ?? 0) + 1;
                    } elseif ($trigger->stone_cost > 0) {
                        $suitCounts['soulstone'] = ($suitCounts['soulstone'] ?? 0) + 1;
                    }
                }
            }
        }

        $statistics = [
            'total_characters' => $characters->count(),
            'total_masters' => $masters->count(),
            'total_henchmen' => $characters->where('station', CharacterStationEnum::Henchman)->count(),
            'total_unique' => $totalUnique,
            'total_minions' => $characters->where('station', CharacterStationEnum::Minion)->count(),
            'total_peons' => $characters->where('station', CharacterStationEnum::Peon)->count(),
            'avg_cost' => round($nonMasters->avg('cost'), 1),
            'avg_health' => round($nonMasters->avg('health'), 1),
            'avg_speed' => round($nonMasters->avg('speed'), 1),
            'avg_defense' => round($nonMasters->avg('defense'), 1),
            'avg_willpower' => round($nonMasters->avg('willpower'), 1),
            'factions' => $characters->pluck('faction')->unique(fn (FactionEnum $f) => $f->value)->values()->map(fn (FactionEnum $f) => [
                'value' => $f->value,
                'name' => $f->label(),
            ]),
            'suit_counts' => $suitCounts,
        ];

        return inertia('Keywords/View', [
            'keyword' => $keyword,
            'characters' => $characters,
            //            'station_sort' => $characters->groupBy('station')->sortBy(function ($item, $key) {
            //                return array_search($key, CharacterStationEnum::sortOrder());
            //            }),
            'keyword_breakdown' => $keywordBreakdown,
            'factions' => $factions,
            'characteristics' => $characteristics,
            'statistics' => $statistics,
            'stations' => CharacterStationEnum::toSelectOptions(),
            'sort_options' => CharacterSortOptionsEnum::toSelectOptions(),
            'sort_types' => SortTypeEnum::toSelectOptions(),
            'view_options' => PageViewOptionsEnum::toSelectOptions(),
        ]);
    }
}
