<?php

namespace App\Http\Controllers\Database;

use App\Enums\CharacterSortOptionsEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\PageViewOptionsEnum;
use App\Enums\SortTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Character;
use App\Models\Characteristic;
use App\Models\Keyword;
use App\Models\PodLink;
use App\Models\Transmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FactionController extends Controller
{
    public function view(Request $request, FactionEnum $factionEnum)
    {
        $query = Character::with('keywords', 'standardMiniatures', 'miniatures', 'characteristics', 'crewUpgrades', 'totem.standardMiniatures', 'isTotemFor.standardMiniatures', 'actions.triggers')->whereHas('standardMiniatures')->where('faction', $factionEnum->value);

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
                CharacterSortOptionsEnum::Faction->value => 'faction',
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

        $masters = $characters->where('station', CharacterStationEnum::Master)->values();
        $nonMasters = $characters->reject(fn ($c) => $c->station === CharacterStationEnum::Master)->values();

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

        return inertia('Factions/View', [
            'faction' => ['name' => $factionEnum->label(), 'color' => $factionEnum->color(), 'logo' => config('app.url').$factionEnum->logo(), 'route' => $factionEnum->value],
            'characters' => $characters,
            'keyword_breakdown' => $keywordBreakdown,
            'keywords' => $keywords,
            'characteristics' => $characteristics,
            'statistics' => [
                'characters' => $characters->count(),
                'miniatures' => (int) $characters->sum('count'),
                'keywords' => $keywords->count(),
                'total_masters' => $masters->count(),
                'total_unique' => $characters->whereNull('station')->count(),
                'total_minions' => $characters->where('station', CharacterStationEnum::Minion)->count(),
                'total_peons' => $characters->where('station', CharacterStationEnum::Peon)->count(),
                'avg_cost' => round($nonMasters->avg('cost'), 1),
                'avg_health' => round($nonMasters->avg('health'), 1),
                'avg_speed' => round($nonMasters->avg('speed'), 1),
                'avg_defense' => round($nonMasters->avg('defense'), 1),
                'avg_willpower' => round($nonMasters->avg('willpower'), 1),
                'suit_counts' => $suitCounts,
            ],
            'stations' => CharacterStationEnum::toSelectOptions(),
            'sort_options' => CharacterSortOptionsEnum::toSelectOptions(),
            'sort_types' => SortTypeEnum::toSelectOptions(),
            'view_options' => PageViewOptionsEnum::toSelectOptions(),
            'resources' => fn () => $this->getFactionResources($factionEnum),
        ]);
    }

    private function getFactionResources(FactionEnum $faction): array
    {
        try {
            $articles = BlogPost::published()
                ->where(fn ($q) => $q
                    ->whereHas('characters', fn ($cq) => $cq->where('faction', $faction->value))
                    ->orWhereExists(fn ($sq) => $sq->select(DB::raw(1))->from('blog_post_faction')->whereColumn('blog_post_faction.blog_post_id', 'blog_posts.id')->where('faction', $faction->value))
                )
                ->with('category:id,name')
                ->latest('published_at')
                ->limit(5)
                ->get(['id', 'title', 'slug', 'published_at', 'blog_category_id']);
        } catch (\Throwable) {
            $articles = collect();
        }

        try {
            $transmissions = Transmission::whereJsonContains('factions', $faction->value)
                ->orWhereHas('characters', fn ($q) => $q->where('faction', $faction->value))
                ->with('channel:id,name,slug,image')
                ->latest('release_date')
                ->limit(5)
                ->get(['id', 'title', 'slug', 'url', 'factions', 'release_date', 'channel_id']);
        } catch (\Throwable) {
            $transmissions = collect();
        }

        try {
            $podLinks = PodLink::whereExists(fn ($q) => $q->select(DB::raw(1))->from('pod_link_faction')->whereColumn('pod_link_faction.pod_link_id', 'pod_links.id')->where('faction', $faction->value))
                ->latest()
                ->limit(10)
                ->get(['id', 'name', 'slug', 'source', 'url']);
        } catch (\Throwable) {
            $podLinks = collect();
        }

        return [
            'articles' => $articles,
            'transmissions' => $transmissions,
            'pod_links' => $podLinks,
        ];
    }
}
