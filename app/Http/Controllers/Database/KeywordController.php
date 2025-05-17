<?php

namespace App\Http\Controllers\Database;

use App\Enums\CharacterSortOptionsEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\PageViewOptionsEnum;
use App\Enums\SortTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Characteristic;
use App\Models\Keyword;
use Illuminate\Http\Request;

class KeywordController extends Controller
{
    public function view(Request $request, Keyword $keyword)
    {
        $query = Character::with('keywords', 'standardMiniatures', 'miniatures', 'characteristics', 'crewUpgrades', 'totem.standardMiniatures', 'isTotemFor.standardMiniatures')
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

        $keywordBreakdown = [
            'keyword' => $keyword,
            'masters' => $characters->where('station', CharacterStationEnum::Master)->values(),
            'characters' => $characters->where('station', '!==', CharacterStationEnum::Master->value),
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
            'statistics' => [],
            'stations' => CharacterStationEnum::toSelectOptions(),
            'sort_options' => CharacterSortOptionsEnum::toSelectOptions(),
            'sort_types' => SortTypeEnum::toSelectOptions(),
            'view_options' => PageViewOptionsEnum::toSelectOptions(),
        ]);
    }
}
