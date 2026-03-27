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
        $tokenName = null;
        $tokenSlug = null;
        if ($request->filled('token')) {
            $tokenSlug = $request->get('token');
            $tokenName = Token::where('slug', $tokenSlug)->value('name');
        }

        $markerName = null;
        $markerSlug = null;
        if ($request->filled('marker')) {
            $markerSlug = $request->get('marker');
            $markerName = Marker::where('slug', $markerSlug)->value('name');
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

        foreach (['cost', 'health', 'speed', 'defense', 'willpower', 'size'] as $field) {
            if ($request->filled("{$field}_min")) {
                $query->where($field, '>=', (int) $request->get("{$field}_min"));
            }
            if ($request->filled("{$field}_max")) {
                $query->where($field, '<=', (int) $request->get("{$field}_max"));
            }
        }

        if ($request->filled('keyword')) {
            $query->whereHas('keywords', fn ($q) => $q->where('slug', $request->get('keyword')));
        }

        if ($request->filled('characteristic')) {
            $query->whereHas('characteristics', fn ($q) => $q->where('slug', $request->get('characteristic')));
        }

        if ($hasActionFilter) {
            $query->whereHas('actions', fn ($q) => $this->applyActionFilters($q, $request));
        }

        if ($hasAbilityFilter) {
            $query->whereHas('abilities', fn ($q) => $this->applyAbilityFilters($q, $request));
        }

        // Trigger filters (grouped so all conditions match the same trigger)
        $hasTriggerFilter = $this->anyFilled($request, ['trigger', 'trigger_suits', 'trigger_description']);
        if ($hasTriggerFilter) {
            $query->whereHas('actions', fn ($q) => $q->whereHas('triggers', fn ($tq) => $this->applyTriggerFilters($tq, $request)));
        }

        if ($tokenName) {
            $this->applyTokenFilter($query, $tokenSlug, $tokenName);
        }

        if ($markerName) {
            $this->applyMarkerFilter($query, $markerSlug, $markerName);
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

        $hasUpgradeFilter = $request->filled('name') || $hasActionFilter || $hasAbilityFilter
            || $hasTriggerFilter || $tokenName || $markerName;

        $upgradeResults = collect();
        if ($hasUpgradeFilter) {
            $upgradeQuery = Upgrade::whereNotNull('front_image');

            if ($request->filled('name')) {
                $upgradeQuery->where('name', 'LIKE', '%'.$request->get('name').'%');
            }

            if ($hasActionFilter) {
                $upgradeQuery->whereHas('actions', fn ($q) => $this->applyActionFilters($q, $request));
            }

            if ($hasAbilityFilter) {
                $upgradeQuery->whereHas('abilities', fn ($q) => $this->applyAbilityFilters($q, $request));
            }

            if ($hasTriggerFilter) {
                $upgradeQuery->whereHas('actions', fn ($q) => $q->whereHas('triggers', fn ($tq) => $this->applyTriggerFilters($tq, $request)));
            }

            if ($tokenName) {
                $this->applyTokenFilter($upgradeQuery, $tokenSlug, $tokenName);
            }

            if ($markerName) {
                $this->applyMarkerFilter($upgradeQuery, $markerSlug, $markerName);
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

            $totalResults = $results->total();
        }

        return inertia('Search/View', [
            'results' => $results,
            'result_count' => $totalResults,
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
        ]);
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

    private function applyTokenFilter($query, string $tokenSlug, string $tokenName): void
    {
        $query->where(function ($q) use ($tokenSlug, $tokenName) {
            $q->whereHas('tokens', fn ($tq) => $tq->where('slug', $tokenSlug))
                ->orWhereHas('actions', fn ($aq) => $aq->where('description', 'LIKE', "%{$tokenName}%"))
                ->orWhereHas('abilities', fn ($aq) => $aq->where('description', 'LIKE', "%{$tokenName}%"))
                ->orWhereHas('actions', fn ($aq) => $aq->whereHas('triggers', fn ($tq) => $tq->where('description', 'LIKE', "%{$tokenName}%")));
        });
    }

    private function applyMarkerFilter($query, string $markerSlug, string $markerName): void
    {
        $query->where(function ($q) use ($markerSlug, $markerName) {
            $q->whereHas('markers', fn ($mq) => $mq->where('slug', $markerSlug))
                ->orWhereHas('actions', fn ($aq) => $aq->where('description', 'LIKE', "%{$markerName}%"))
                ->orWhereHas('abilities', fn ($aq) => $aq->where('description', 'LIKE', "%{$markerName}%"))
                ->orWhereHas('actions', fn ($aq) => $aq->whereHas('triggers', fn ($tq) => $tq->where('description', 'LIKE', "%{$markerName}%")));
        });
    }

    private function applyActionFilters($q, Request $request): void
    {
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

    private function applyAbilityFilters($q, Request $request): void
    {
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
    }

    private function applyTriggerFilters($q, Request $request): void
    {
        if ($request->filled('trigger')) {
            $q->where('name', $request->get('trigger'));
        }
        if ($request->filled('trigger_suits')) {
            $q->where('suits', 'LIKE', '%'.$request->get('trigger_suits').'%');
        }
        if ($request->filled('trigger_description')) {
            $q->where('description', 'LIKE', '%'.$request->get('trigger_description').'%');
        }
    }
}
