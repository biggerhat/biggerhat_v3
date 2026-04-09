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
use App\Models\Marker;
use App\Models\Token;
use App\Models\Trigger;
use App\Models\Upgrade;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SearchController extends Controller
{
    public function view(Request $request)
    {
        $pageView = $request->get('page_view', 'images');
        $eagerLoads = match ($pageView) {
            'full' => ['standardMiniatures', 'miniatures', 'keywords', 'crewUpgrades', 'totem.standardMiniatures', 'isTotemFor.standardMiniatures'],
            default => ['standardMiniatures'],
        };

        $query = Character::with($eagerLoads)
            ->whereHas('standardMiniatures');

        // Collect active filter keys for action/ability checks (avoid creating collections repeatedly)
        $actionFilterKeys = [
            'action', 'action_name', 'action_type', 'action_is_signature', 'action_costs_stone',
            'action_range_min', 'action_range_max', 'action_range_type',
            'action_stat_min', 'action_stat_max', 'action_stat_suits', 'action_stat_modifier',
            'action_resisted_by', 'action_tn_min', 'action_tn_max', 'action_target_suits',
            'action_damage', 'action_description',
        ];
        $abilityFilterKeys = ['ability', 'ability_name', 'ability_suits', 'ability_defensive_type', 'ability_costs_stone', 'ability_description'];

        $hasActionFilter = $this->anyFilled($request, $actionFilterKeys);
        $hasAbilityFilter = $this->anyFilled($request, $abilityFilterKeys);

        // Resolve token/marker names once — reused by both character and upgrade queries
        $tokenEntries = [];
        if ($request->filled('token')) {
            $slugs = array_filter(explode(',', $request->get('token')));
            $tokens = Token::whereIn('slug', $slugs)->get(['slug', 'name']);
            foreach ($tokens as $t) {
                $tokenEntries[] = ['slug' => $t->slug, 'name' => $t->name];
            }
        }

        $markerEntries = [];
        if ($request->filled('marker')) {
            $slugs = array_filter(explode(',', $request->get('marker')));
            $markers = Marker::whereIn('slug', $slugs)->get(['slug', 'name']);
            foreach ($markers as $m) {
                $markerEntries[] = ['slug' => $m->slug, 'name' => $m->name];
            }
        }

        // --- Character filters ---

        if ($request->filled('name')) {
            $search = $request->get('name');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('display_name', 'LIKE', "%{$search}%")
                    ->orWhere('nicknames', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('faction')) {
            $factions = array_filter(explode(',', $request->get('faction')));
            if ($factions) {
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

        foreach (['cost', 'health', 'speed', 'defense', 'willpower', 'size', 'count'] as $field) {
            if ($request->filled("{$field}_min")) {
                $query->where($field, '>=', (int) $request->get("{$field}_min"));
            }
            if ($request->filled("{$field}_max")) {
                $query->where($field, '<=', (int) $request->get("{$field}_max"));
            }
        }

        if ($request->filled('keyword')) {
            $keywords = array_filter(explode(',', $request->get('keyword')));
            if ($request->get('keyword_logic') === 'or') {
                $query->whereHas('keywords', fn ($q) => $q->whereIn('slug', $keywords));
            } else {
                foreach ($keywords as $kw) {
                    $query->whereHas('keywords', fn ($q) => $q->where('slug', $kw));
                }
            }
        }

        if ($request->filled('keyword_exclude')) {
            $excluded = array_filter(explode(',', $request->get('keyword_exclude')));
            $query->whereDoesntHave('keywords', fn ($q) => $q->whereIn('slug', $excluded));
        }

        if ($request->filled('characteristic')) {
            $characteristics = array_filter(explode(',', $request->get('characteristic')));
            if ($request->get('characteristic_logic') === 'or') {
                $query->whereHas('characteristics', fn ($q) => $q->whereIn('slug', $characteristics));
            } else {
                foreach ($characteristics as $ch) {
                    $query->whereHas('characteristics', fn ($q) => $q->where('slug', $ch));
                }
            }
        }

        if ($request->filled('characteristic_exclude')) {
            $excluded = array_filter(explode(',', $request->get('characteristic_exclude')));
            $query->whereDoesntHave('characteristics', fn ($q) => $q->whereIn('slug', $excluded));
        }

        if ($request->filled('faction_exclude')) {
            $excluded = array_filter(explode(',', $request->get('faction_exclude')));
            $query->whereNotIn('faction', $excluded)
                ->where(fn ($q) => $q->whereNull('second_faction')->orWhereNotIn('second_faction', $excluded));
        }

        if ($request->filled('second_faction')) {
            $secondFactions = array_filter(explode(',', $request->get('second_faction')));
            if ($secondFactions) {
                $query->whereIn('second_faction', $secondFactions);
            }
        }

        // Boolean "is:" filters
        if ($request->filled('is')) {
            foreach (array_filter(explode(',', $request->get('is'))) as $isFilter) {
                match ($isFilter) {
                    'dual' => $query->whereNotNull('second_faction'),
                    'totem' => $query->whereExists(function ($sq) {
                        $sq->select(\DB::raw(1))->from('characters as c2')->whereColumn('c2.has_totem_id', 'characters.id');
                    }),
                    'versatile' => $query->whereHas('characteristics', fn ($q) => $q->where('slug', 'versatile')),
                    'beta' => $query->where('is_beta', true),
                    'unhirable' => $query->where('is_unhirable', true),
                    default => null,
                };
            }
        }

        // Boolean "has:" filters
        if ($request->filled('has')) {
            foreach (array_filter(explode(',', $request->get('has'))) as $hasFilter) {
                match ($hasFilter) {
                    'totem' => $query->whereNotNull('has_totem_id'),
                    'trigger' => $query->whereHas('actions', fn ($q) => $q->whereHas('triggers')),
                    'ability' => $query->whereHas('abilities'),
                    'action' => $query->whereHas('actions'),
                    'keyword' => $query->whereHas('keywords'),
                    'summon' => $query->whereHas('summons'),
                    default => null,
                };
            }
        }

        // Stat comparison: defense>willpower, health>=cost, etc.
        if ($request->filled('stat_compare')) {
            $validStats = ['cost', 'health', 'speed', 'defense', 'willpower', 'size', 'count'];
            foreach (array_filter(explode(',', $request->get('stat_compare'))) as $comparison) {
                if (preg_match('/^(\w+)(>=|<=|>|<|=)(\w+)$/', $comparison, $m)) {
                    if (in_array($m[1], $validStats) && in_array($m[3], $validStats)) {
                        $query->whereColumn($m[1], $m[2], $m[3]);
                    }
                }
            }
        }

        if ($hasActionFilter) {
            $this->applyActionFilterGroup($query, $request);
        }

        if ($hasAbilityFilter) {
            $this->applyAbilityFilterGroup($query, $request);
        }

        // Trigger filters (grouped so all conditions match the same trigger)
        $hasTriggerFilter = $this->anyFilled($request, ['trigger', 'trigger_suits', 'trigger_description']);
        if ($hasTriggerFilter) {
            $this->applyTriggerFilterGroup($query, $request);
        }

        if ($tokenEntries) {
            $this->applyTokenFilterGroup($query, $tokenEntries, $request->get('token_logic', 'or'));
        }

        if ($markerEntries) {
            $this->applyMarkerFilterGroup($query, $markerEntries, $request->get('marker_logic', 'or'));
        }

        // Cross-field description/rules text search
        if ($request->filled('description')) {
            $desc = $request->get('description');
            $query->where(function ($q) use ($desc) {
                $q->whereHas('actions', fn ($aq) => $aq->where('description', 'LIKE', "%{$desc}%"))
                    ->orWhereHas('abilities', fn ($aq) => $aq->where('description', 'LIKE', "%{$desc}%"))
                    ->orWhereHas('actions', fn ($aq) => $aq->whereHas('triggers', fn ($tq) => $tq->where('description', 'LIKE', "%{$desc}%")));
            });
        }

        // --- Sorting ---

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

        $perPage = match ($pageView) {
            'table' => 50,
            'full' => 10,
            default => 24,
        };

        // --- Upgrade query (only if upgrade-relevant filters are active) ---

        $hasUpgradeFilter = $request->filled('name') || $request->filled('description') || $hasActionFilter || $hasAbilityFilter
            || $hasTriggerFilter || $tokenEntries || $markerEntries;

        $upgradeResults = collect();
        if ($hasUpgradeFilter) {
            $upgradeQuery = Upgrade::whereNotNull('front_image');

            if ($request->filled('name')) {
                $upgradeQuery->where('name', 'LIKE', '%'.$request->get('name').'%');
            }

            if ($request->filled('description')) {
                $desc = $request->get('description');
                $upgradeQuery->where(function ($q) use ($desc) {
                    $q->whereHas('actions', fn ($aq) => $aq->where('description', 'LIKE', "%{$desc}%"))
                        ->orWhereHas('abilities', fn ($aq) => $aq->where('description', 'LIKE', "%{$desc}%"))
                        ->orWhereHas('triggers', fn ($tq) => $tq->where('description', 'LIKE', "%{$desc}%"));
                });
            }

            if ($hasActionFilter) {
                $this->applyActionFilterGroup($upgradeQuery, $request);
            }

            if ($hasAbilityFilter) {
                $this->applyAbilityFilterGroup($upgradeQuery, $request);
            }

            if ($hasTriggerFilter) {
                $this->applyTriggerFilterGroup($upgradeQuery, $request);
            }

            if ($tokenEntries) {
                $this->applyTokenFilterGroup($upgradeQuery, $tokenEntries, $request->get('token_logic', 'or'));
            }

            if ($markerEntries) {
                $this->applyMarkerFilterGroup($upgradeQuery, $markerEntries, $request->get('marker_logic', 'or'));
            }

            $upgradeResults = $upgradeQuery->get();
        }

        // --- Merge, sort, paginate ---

        $hasUpgrades = $upgradeResults->isNotEmpty();

        if ($hasUpgrades) {
            // Must fetch all characters to merge with upgrades for unified sort
            $allCharacters = $query->orderBy($sort, $sortType)->get();

            $allResults = [];
            foreach ($allCharacters as $c) {
                $data = $c->toArray();
                $data['result_type'] = 'character';
                $data['_sort_key'] = $this->characterSortKey($c, $sort);
                $allResults[] = $data;
            }

            foreach ($upgradeResults as $u) {
                $allResults[] = [
                    'result_type' => 'upgrade',
                    'id' => $u->id,
                    'name' => $u->name,
                    'slug' => $u->slug,
                    '_sort_key' => strtolower($u->name),
                    'domain' => $u->getRawOriginal('domain'),
                    'type' => $u->type?->label(),
                    'front_image' => $u->front_image ? "/storage/{$u->front_image}" : null,
                    'back_image' => $u->back_image ? "/storage/{$u->back_image}" : null,
                ];
            }

            usort($allResults, fn (array $a, array $b) => $sortType === 'DESC'
                ? strcmp($b['_sort_key'], $a['_sort_key'])
                : strcmp($a['_sort_key'], $b['_sort_key'])
            );

            // Strip sort key
            foreach ($allResults as &$item) {
                unset($item['_sort_key']);
            }
            unset($item);

            $characterCount = $allCharacters->count();
            $upgradeCount = $upgradeResults->count();
            $totalResults = count($allResults);
            $page = max(1, (int) $request->get('page', 1));
            $results = new LengthAwarePaginator(
                array_values(array_slice($allResults, ($page - 1) * $perPage, $perPage)),
                $totalResults,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            // No upgrades — use efficient DB-level pagination
            $results = $query->orderBy($sort, $sortType)->paginate($perPage)->withQueryString();

            // Wrap paginated characters with result_type for consistent frontend shape
            $results->setCollection($results->getCollection()->map(function ($c) { // @phpstan-ignore argument.type
                $data = $c->toArray();
                $data['result_type'] = 'character';

                return $data;
            }));

            $characterCount = $totalResults = $results->total();
            $upgradeCount = 0;
        }

        return inertia('Search/View', [
            'results' => $results,
            'result_count' => $totalResults,
            'result_breakdown' => ['characters' => $characterCount, 'upgrades' => $upgradeCount],
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
            'triggers' => fn () => Trigger::select('name')->distinct()->orderBy('name', 'ASC')->get()->map(fn ($t) => ['name' => $t->name, 'value' => $t->name]),
            'tokens_list' => fn () => Token::orderBy('name', 'ASC')->get(['name', 'slug'])->map(fn ($t) => ['name' => $t->name, 'value' => $t->slug]),
            'markers_list' => fn () => Marker::orderBy('name', 'ASC')->get(['name', 'slug'])->map(fn ($t) => ['name' => $t->name, 'value' => $t->slug]),
            'defensive_ability_types' => fn () => DefensiveAbilityTypeEnum::toSelectOptions(),
            'sort_options' => fn () => CharacterSortOptionsEnum::toSelectOptions(),
            'sort_types' => fn () => SortTypeEnum::toSelectOptions(),
            'view_options' => fn () => PageViewOptionsEnum::toSelectOptions(),
            'saved_searches' => fn () => $request->user()
                ? $request->user()->savedSearches()->select('id', 'name', 'query_params')->orderBy('name')->get()
                : [],
        ]);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = Character::with(['keywords', 'characteristics'])->whereHas('standardMiniatures');
        $this->applyCharacterFilters($query, $request);
        $characters = $query->orderBy('display_name')->limit(1000)->get();

        return response()->streamDownload(function () use ($characters) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Faction', 'Second Faction', 'Station', 'Cost', 'Health', 'Defense', 'Willpower', 'Speed', 'Size', 'Count', 'Keywords', 'Characteristics']);
            foreach ($characters as $c) {
                fputcsv($handle, [
                    $c->display_name ?? $c->name,
                    $c->faction->label(),
                    $c->second_faction?->label(),
                    $c->station?->label(),
                    $c->cost,
                    $c->health,
                    $c->defense,
                    $c->willpower,
                    $c->speed,
                    $c->size,
                    $c->count,
                    $c->keywords->pluck('name')->join(', '),
                    $c->characteristics->pluck('name')->join(', '),
                ]);
            }
            fclose($handle);
        }, 'malifaux-search-results.csv', ['Content-Type' => 'text/csv']);
    }

    public function saveSearch(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'query_params' => ['required', 'array'],
        ]);

        $request->user()->savedSearches()->create([
            'name' => $request->name,
            'query_params' => $request->query_params,
        ]);

        return back();
    }

    public function deleteSavedSearch(Request $request, \App\Models\SavedSearch $savedSearch): \Illuminate\Http\RedirectResponse
    {
        abort_unless($savedSearch->user_id === $request->user()->id, 403);
        $savedSearch->delete();

        return back();
    }

    private function applyCharacterFilters($query, Request $request): void
    {
        $actionFilterKeys = [
            'action', 'action_name', 'action_type', 'action_is_signature', 'action_costs_stone',
            'action_range_min', 'action_range_max', 'action_range_type',
            'action_stat_min', 'action_stat_max', 'action_stat_suits', 'action_stat_modifier',
            'action_resisted_by', 'action_tn_min', 'action_tn_max', 'action_target_suits',
            'action_damage', 'action_description',
        ];
        $abilityFilterKeys = ['ability', 'ability_name', 'ability_suits', 'ability_defensive_type', 'ability_costs_stone', 'ability_description'];

        if ($request->filled('name')) {
            $search = $request->get('name');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('display_name', 'LIKE', "%{$search}%")
                    ->orWhere('nicknames', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('faction')) {
            $factions = array_filter(explode(',', $request->get('faction')));
            if ($factions) {
                $query->where(function ($q) use ($factions) {
                    $q->whereIn('faction', $factions)->orWhereIn('second_faction', $factions);
                });
            }
        }

        if ($request->filled('station')) {
            $query->where('station', $request->get('station'));
        }

        foreach (['cost', 'health', 'speed', 'defense', 'willpower', 'size', 'count'] as $field) {
            if ($request->filled("{$field}_min")) {
                $query->where($field, '>=', (int) $request->get("{$field}_min"));
            }
            if ($request->filled("{$field}_max")) {
                $query->where($field, '<=', (int) $request->get("{$field}_max"));
            }
        }

        if ($request->filled('keyword')) {
            $keywords = array_filter(explode(',', $request->get('keyword')));
            if ($request->get('keyword_logic') === 'or') {
                $query->whereHas('keywords', fn ($q) => $q->whereIn('slug', $keywords));
            } else {
                foreach ($keywords as $kw) {
                    $query->whereHas('keywords', fn ($q) => $q->where('slug', $kw));
                }
            }
        }

        if ($request->filled('keyword_exclude')) {
            $excluded = array_filter(explode(',', $request->get('keyword_exclude')));
            $query->whereDoesntHave('keywords', fn ($q) => $q->whereIn('slug', $excluded));
        }

        if ($request->filled('characteristic')) {
            $characteristics = array_filter(explode(',', $request->get('characteristic')));
            if ($request->get('characteristic_logic') === 'or') {
                $query->whereHas('characteristics', fn ($q) => $q->whereIn('slug', $characteristics));
            } else {
                foreach ($characteristics as $ch) {
                    $query->whereHas('characteristics', fn ($q) => $q->where('slug', $ch));
                }
            }
        }

        if ($request->filled('characteristic_exclude')) {
            $excluded = array_filter(explode(',', $request->get('characteristic_exclude')));
            $query->whereDoesntHave('characteristics', fn ($q) => $q->whereIn('slug', $excluded));
        }

        if ($request->filled('second_faction')) {
            $secondFactions = array_filter(explode(',', $request->get('second_faction')));
            if ($secondFactions) {
                $query->whereIn('second_faction', $secondFactions);
            }
        }

        if ($request->filled('is')) {
            foreach (array_filter(explode(',', $request->get('is'))) as $isFilter) {
                match ($isFilter) {
                    'dual' => $query->whereNotNull('second_faction'),
                    'totem' => $query->whereExists(function ($sq) {
                        $sq->select(\DB::raw(1))->from('characters as c2')->whereColumn('c2.has_totem_id', 'characters.id');
                    }),
                    'versatile' => $query->whereHas('characteristics', fn ($q) => $q->where('slug', 'versatile')),
                    'beta' => $query->where('is_beta', true),
                    'unhirable' => $query->where('is_unhirable', true),
                    default => null,
                };
            }
        }

        if ($request->filled('has')) {
            foreach (array_filter(explode(',', $request->get('has'))) as $hasFilter) {
                match ($hasFilter) {
                    'totem' => $query->whereNotNull('has_totem_id'),
                    'trigger' => $query->whereHas('actions', fn ($q) => $q->whereHas('triggers')),
                    'ability' => $query->whereHas('abilities'),
                    'action' => $query->whereHas('actions'),
                    'keyword' => $query->whereHas('keywords'),
                    'summon' => $query->whereHas('summons'),
                    default => null,
                };
            }
        }

        if ($request->filled('stat_compare')) {
            $validStats = ['cost', 'health', 'speed', 'defense', 'willpower', 'size', 'count'];
            foreach (array_filter(explode(',', $request->get('stat_compare'))) as $comparison) {
                if (preg_match('/^(\w+)(>=|<=|>|<|=)(\w+)$/', $comparison, $m)) {
                    if (in_array($m[1], $validStats) && in_array($m[3], $validStats)) {
                        $query->whereColumn($m[1], $m[2], $m[3]);
                    }
                }
            }
        }

        if ($this->anyFilled($request, $actionFilterKeys)) {
            $this->applyActionFilterGroup($query, $request);
        }

        if ($this->anyFilled($request, $abilityFilterKeys)) {
            $this->applyAbilityFilterGroup($query, $request);
        }

        $hasTriggerFilter = $this->anyFilled($request, ['trigger', 'trigger_suits', 'trigger_description']);
        if ($hasTriggerFilter) {
            $this->applyTriggerFilterGroup($query, $request);
        }

        if ($request->filled('description')) {
            $desc = $request->get('description');
            $query->where(function ($q) use ($desc) {
                $q->whereHas('actions', fn ($aq) => $aq->where('description', 'LIKE', "%{$desc}%"))
                    ->orWhereHas('abilities', fn ($aq) => $aq->where('description', 'LIKE', "%{$desc}%"))
                    ->orWhereHas('actions', fn ($aq) => $aq->whereHas('triggers', fn ($tq) => $tq->where('description', 'LIKE', "%{$desc}%")));
            });
        }
    }

    private function anyFilled(Request $request, array $keys): bool
    {
        foreach ($keys as $key) {
            if ($request->filled($key)) {
                return true;
            }
        }

        return false;
    }

    private function characterSortKey(Character $c, string $sort): string
    {
        return match ($sort) {
            'faction' => strtolower($c->getRawOriginal('faction') ?? ''),
            'cost' => str_pad((string) $c->cost, 5, '0', STR_PAD_LEFT).strtolower($c->name),
            'health' => str_pad((string) $c->health, 5, '0', STR_PAD_LEFT).strtolower($c->name),
            'speed' => str_pad((string) $c->speed, 5, '0', STR_PAD_LEFT).strtolower($c->name),
            'defense' => str_pad((string) $c->defense, 5, '0', STR_PAD_LEFT).strtolower($c->name),
            'willpower' => str_pad((string) $c->willpower, 5, '0', STR_PAD_LEFT).strtolower($c->name),
            'size' => str_pad((string) $c->size, 5, '0', STR_PAD_LEFT).strtolower($c->name),
            'base' => ($c->base?->value ?? '').strtolower($c->name), // @phpstan-ignore nullsafe.neverNull
            default => strtolower($c->name),
        };
    }

    private function applySingleTokenFilter($query, string $slug, string $name): void
    {
        $query->where(function ($q) use ($slug, $name) {
            $q->whereHas('tokens', fn ($tq) => $tq->where('slug', $slug))
                ->orWhereHas('actions', fn ($aq) => $aq->where('description', 'LIKE', "%{$name}%"))
                ->orWhereHas('abilities', fn ($aq) => $aq->where('description', 'LIKE', "%{$name}%"))
                ->orWhereHas('actions', fn ($aq) => $aq->whereHas('triggers', fn ($tq) => $tq->where('description', 'LIKE', "%{$name}%")));
        });
    }

    private function applyTokenFilterGroup($query, array $entries, string $logic): void
    {
        if ($logic === 'and') {
            foreach ($entries as $entry) {
                $this->applySingleTokenFilter($query, $entry['slug'], $entry['name']);
            }
        } else {
            $query->where(function ($q) use ($entries) {
                foreach ($entries as $i => $entry) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $q->{$method}(function ($sq) use ($entry) {
                        $this->applySingleTokenFilter($sq, $entry['slug'], $entry['name']);
                    });
                }
            });
        }
    }

    private function applySingleMarkerFilter($query, string $slug, string $name): void
    {
        $query->where(function ($q) use ($slug, $name) {
            $q->whereHas('markers', fn ($mq) => $mq->where('slug', $slug))
                ->orWhereHas('actions', fn ($aq) => $aq->where('description', 'LIKE', "%{$name}%"))
                ->orWhereHas('abilities', fn ($aq) => $aq->where('description', 'LIKE', "%{$name}%"))
                ->orWhereHas('actions', fn ($aq) => $aq->whereHas('triggers', fn ($tq) => $tq->where('description', 'LIKE', "%{$name}%")));
        });
    }

    private function applyMarkerFilterGroup($query, array $entries, string $logic): void
    {
        if ($logic === 'and') {
            foreach ($entries as $entry) {
                $this->applySingleMarkerFilter($query, $entry['slug'], $entry['name']);
            }
        } else {
            $query->where(function ($q) use ($entries) {
                foreach ($entries as $i => $entry) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $q->{$method}(function ($sq) use ($entry) {
                        $this->applySingleMarkerFilter($sq, $entry['slug'], $entry['name']);
                    });
                }
            });
        }
    }

    private function applyActionFilterGroup($query, Request $request): void
    {
        $actions = $request->filled('action') ? array_filter(explode(',', $request->get('action'))) : [];
        $logic = $request->get('action_logic', 'or');

        if (count($actions) > 1 && $logic === 'and') {
            foreach ($actions as $actionName) {
                $query->whereHas('actions', function ($q) use ($actionName, $request) {
                    $q->where('name', $actionName);
                    $this->applyActionSubFilters($q, $request);
                });
            }
        } else {
            $query->whereHas('actions', function ($q) use ($actions, $request) {
                if (count($actions) === 1) {
                    $q->where('name', $actions[0]);
                } elseif (count($actions) > 1) {
                    $q->whereIn('name', $actions);
                }
                $this->applyActionSubFilters($q, $request);
            });
        }
    }

    private function applyAbilityFilterGroup($query, Request $request): void
    {
        $abilities = $request->filled('ability') ? array_filter(explode(',', $request->get('ability'))) : [];
        $logic = $request->get('ability_logic', 'or');

        if (count($abilities) > 1 && $logic === 'and') {
            foreach ($abilities as $abilityName) {
                $query->whereHas('abilities', function ($q) use ($abilityName, $request) {
                    $q->where('name', $abilityName);
                    $this->applyAbilitySubFilters($q, $request);
                });
            }
        } else {
            $query->whereHas('abilities', function ($q) use ($abilities, $request) {
                if (count($abilities) === 1) {
                    $q->where('name', $abilities[0]);
                } elseif (count($abilities) > 1) {
                    $q->whereIn('name', $abilities);
                }
                $this->applyAbilitySubFilters($q, $request);
            });
        }
    }

    private function applyTriggerFilterGroup($query, Request $request): void
    {
        $triggers = $request->filled('trigger') ? array_filter(explode(',', $request->get('trigger'))) : [];
        $logic = $request->get('trigger_logic', 'or');

        if (count($triggers) > 1 && $logic === 'and') {
            foreach ($triggers as $triggerName) {
                $query->whereHas('actions', fn ($aq) => $aq->whereHas('triggers', function ($q) use ($triggerName, $request) {
                    $q->where('name', $triggerName);
                    $this->applyTriggerSubFilters($q, $request);
                }));
            }
        } else {
            $query->whereHas('actions', fn ($aq) => $aq->whereHas('triggers', function ($q) use ($triggers, $request) {
                if (count($triggers) === 1) {
                    $q->where('name', $triggers[0]);
                } elseif (count($triggers) > 1) {
                    $q->whereIn('name', $triggers);
                }
                $this->applyTriggerSubFilters($q, $request);
            }));
        }
    }

    private function applyActionSubFilters($q, Request $request): void
    {
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
            $costsStone = filter_var($request->get('action_costs_stone'), FILTER_VALIDATE_BOOLEAN);
            $q->where('stone_cost', $costsStone ? '>' : '=', 0);
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
    }

    private function applyAbilitySubFilters($q, Request $request): void
    {
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
    }

    private function applyTriggerSubFilters($q, Request $request): void
    {
        if ($request->filled('trigger_suits')) {
            $q->where('suits', 'LIKE', '%'.$request->get('trigger_suits').'%');
        }
        if ($request->filled('trigger_description')) {
            $q->where('description', 'LIKE', '%'.$request->get('trigger_description').'%');
        }
    }
}
