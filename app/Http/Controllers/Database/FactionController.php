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

        $isStationSort = ! $request->get('sort') || $request->get('sort') === CharacterSortOptionsEnum::Station->value;

        if (! $isStationSort) {
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
        } else {
            $characters = $query->orderBy('display_name')->get();

            $stationOrder = function (Character $c): int {
                $charSlugs = $c->characteristics->pluck('slug')->toArray();
                $isHenchman = in_array('henchman', $charSlugs);
                $isUnique = in_array('unique', $charSlugs);

                return match ($c->station) {
                    CharacterStationEnum::Master => 0,
                    default => match (true) {
                        $isHenchman && $isUnique => 1,
                        $isHenchman => 2,
                        $isUnique => 3,
                        $c->station === CharacterStationEnum::Minion => 4,
                        $c->station === CharacterStationEnum::Peon => 5,
                        default => 6,
                    },
                };
            };

            $descending = $request->get('sort_type') === SortTypeEnum::Descending->value;
            $characters = $characters->sortBy(function (Character $c) use ($stationOrder) {
                return [$stationOrder($c), $c->display_name];
            });

            if ($descending) {
                $characters = $characters->reverse();
            }

            $characters = $characters->values();
        }

        $keywordBreakdown = [];
        if ($request->get('page_view') === PageViewOptionsEnum::KeywordBreakdown->value) {
            $charactersByKeyword = [];
            foreach ($characters as $character) {
                foreach ($character->keywords as $kw) {
                    $charactersByKeyword[$kw->name][] = $character;
                }
            }

            foreach ($keywords as $keyword) {
                $keywordCharacters = collect($charactersByKeyword[$keyword->name] ?? []);
                $masters = $keywordCharacters->where('station', CharacterStationEnum::Master)->values();
                $nonMasters = $keywordCharacters->reject(fn ($c) => $c->station === CharacterStationEnum::Master)->values();

                $keywordBreakdown[] = [
                    'keyword' => $keyword,
                    'masters' => $masters,
                    'characters' => $nonMasters,
                    'statistics' => [
                        'total_characters' => $keywordCharacters->count(),
                        'total_masters' => $masters->count(),
                        'total_henchmen' => $keywordCharacters->where('station', CharacterStationEnum::Henchman)->count(),
                        'total_unique' => $keywordCharacters->whereNull('station')->count(),
                        'total_minions' => $keywordCharacters->where('station', CharacterStationEnum::Minion)->count(),
                        'total_peons' => $keywordCharacters->where('station', CharacterStationEnum::Peon)->count(),
                        'avg_cost' => round($nonMasters->avg('cost'), 1),
                        'avg_health' => round($nonMasters->avg('health'), 1),
                        'avg_speed' => round($nonMasters->avg('speed'), 1),
                        'avg_defense' => round($nonMasters->avg('defense'), 1),
                        'avg_willpower' => round($nonMasters->avg('willpower'), 1),
                    ],
                ];
            }
        }

        return inertia('Factions/View', [
            'faction' => ['name' => $factionEnum->label(), 'color' => $factionEnum->color(), 'logo' => config('app.url').$factionEnum->logo(), 'route' => $factionEnum->value],
            'characters' => $characters,
            //            'station_sort' => $characters->groupBy('station')->sortBy(function ($item, $key) {
            //                return array_search($key, CharacterStationEnum::sortOrder());
            //            }),
            'keyword_breakdown' => $keywordBreakdown,
            'keywords' => $keywords,
            'characteristics' => $characteristics,
            'statistics' => [
                'characters' => $characters->count(),
                'miniatures' => (int) $characters->sum('count'),
                'keywords' => $keywords->count(),
            ],
            'stations' => CharacterStationEnum::toSelectOptions(),
            'sort_options' => CharacterSortOptionsEnum::toSelectOptions(),
            'sort_types' => SortTypeEnum::toSelectOptions(),
            'view_options' => PageViewOptionsEnum::toSelectOptions(),
        ]);
    }
}
