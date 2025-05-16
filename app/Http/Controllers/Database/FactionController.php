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

class FactionController extends Controller
{
    public function view(Request $request, FactionEnum $factionEnum)
    {
        $query = Character::with('keywords', 'standardMiniatures', 'miniatures', 'characteristics', 'crewUpgrades', 'totem.standardMiniatures', 'isTotemFor.standardMiniatures')->whereHas('standardMiniatures')->where('faction', $factionEnum->value);

        $keywords = Keyword::whereHas('characters', function ($query) use ($factionEnum) {
            $query->where('faction', $factionEnum->value);
        })->orderBy('name', 'ASC')->get();

        $characteristics = Characteristic::whereHas('characters', function ($query) use ($factionEnum) {
            $query->where('faction', $factionEnum->value);
        })->orderBy('name', 'ASC')->get();

        if ($request->get('keyword')) {
            $query->whereHas('keywords', function ($query) use ($request) {
                $query->where('slug', $request->get('keyword'));
            });
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

        $keywordBreakdown = [];
        if ($request->get('page_view') === PageViewOptionsEnum::KeywordBreakdown->value) {
            foreach ($keywords as $keyword) {
                $keywordCharacters = $characters->filter(function (Character $character) use ($keyword) {
                    return (bool) $character->keywords->filter(function (Keyword $keywordCheck) use ($keyword) {
                        return $keywordCheck->name === $keyword->name;
                    })->count();
                });

                $masters = $keywordCharacters->where('station', CharacterStationEnum::Master)->values();

                $keywordBreakdown[] = [
                    'keyword' => $keyword,
                    'masters' => $masters,
                    'characters' => $keywordCharacters->where('station', '!==', CharacterStationEnum::Master->value),
                ];
            }
        }

        return inertia('Factions/View', [
            'faction' => ['name' => $factionEnum->label(), 'color' => $factionEnum->color(), 'logo' => config('app.url').$factionEnum->logo(), 'route' => $factionEnum->value],
            'characters' => $characters,
            'station_sort' => $characters->groupBy('station')->sortBy(function ($item, $key) {
                return array_search($key, CharacterStationEnum::sortOrder());
            }),
            'keyword_breakdown' => $keywordBreakdown,
            'keywords' => $keywords,
            'characteristics' => $characteristics,
            'statistics' => $factionEnum->getCharacterStats(),
            'stations' => CharacterStationEnum::toSelectOptions(),
            'sort_options' => CharacterSortOptionsEnum::toSelectOptions(),
            'sort_types' => SortTypeEnum::toSelectOptions(),
            'view_options' => PageViewOptionsEnum::toSelectOptions(),
        ]);
    }
}
