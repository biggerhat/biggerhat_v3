<?php

namespace App\Http\Controllers\TOS\Database;

use App\Enums\TOS\ActionTypeEnum;
use App\Enums\TOS\AllegianceTypeEnum;
use App\Enums\TOS\UsageLimitEnum;
use App\Http\Controllers\Controller;
use App\Models\TOS\Ability;
use App\Models\TOS\Action;
use App\Models\TOS\Allegiance;
use App\Models\TOS\AllegianceCard;
use App\Models\TOS\Asset;
use App\Models\TOS\SpecialUnitRule;
use App\Models\TOS\Stratagem;
use App\Models\TOS\Trigger;
use App\Models\TOS\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * TOS Advanced Search — mirrors `App\Http\Controllers\Database\SearchController`
 * (the Malifaux Character search) for TOS Units, with supplementary Asset and
 * Stratagem result rows when their fields match.
 *
 * Filter surface (URL params):
 *   name                 — LIKE across name/title/description
 *   allegiance           — comma-separated slug list
 *   allegiance_logic     — and|or  (default and)
 *   allegiance_exclude   — comma-separated slug list
 *   restriction          — earth|malifaux  (Neutral pool toggle)
 *   special_rule         — comma-separated slug list
 *   special_rule_logic   — and|or  (default and)
 *   special_rule_exclude — comma-separated slug list
 *   scrip_min/max        — integer range (signed)
 *   tactics              — string equality match (Standard side)
 *   glory_tactics        — Glory-side equality (matches glory_tactics OR
 *                          falls through to `tactics` when no override set)
 *   side                 — standard|glory|both  (which side stat filters target)
 *   speed_min/max, defense_min/max, willpower_min/max, armor_min/max
 *   action_name          — LIKE on action name
 *   action_type          — comma-separated ActionTypeEnum values
 *   action_av_min/max    — integer range
 *   action_strength_min/max
 *   action_range         — substring match
 *   action_usage_limit   — UsageLimitEnum value
 *   action_is_piercing/accurate/area — boolean (truthy)
 *   action_description   — LIKE on action body
 *   ability_name         — LIKE on ability name
 *   ability_description  — LIKE on ability body
 *   trigger_name, trigger_suits, trigger_description
 *   has                  — comma-separated boolean checks (commander, sculpts,
 *                          combined_arms, abilities, actions, triggers, neutral)
 *   description          — cross-field LIKE (action body, ability body, trigger body)
 *   sort                 — name|scrip|tactics
 *   sort_type            — ascending|descending
 *   page_view            — cards|table|full
 *
 * Asset and Stratagem rows roll into the result list ONLY when at least one
 * filter-relevant field is present (name / description / scrip-range), keeping
 * pure unit-shape filters from leaking irrelevant rows.
 */
class SearchController extends Controller
{
    public function view(Request $request)
    {
        $pageView = (string) $request->get('page_view', 'cards');
        $perPage = match ($pageView) {
            'table' => 50,
            'full' => 10,
            default => 24,
        };

        $eagerLoads = match ($pageView) {
            'full' => [
                'sculpts',
                'allegiances:id,slug,name,color_slug',
                'specialUnitRules:id,slug,name',
                'sides',
                'sides.abilities:id,name,body',
                'sides.actions.triggers:id,name,suits,margin_cost,timing,body',
                'sides.actions.typeLinks',
            ],
            default => [
                'sculpts',
                'allegiances:id,slug,name',
                'specialUnitRules:id,slug,name',
                'sides:id,unit_id,side,speed,defense,willpower,armor',
            ],
        };

        $query = Unit::query()->with($eagerLoads)->notCombinedArmsChild();

        $this->applyUnitFilters($query, $request);

        $sort = match ($request->get('sort')) {
            'scrip' => 'scrip',
            'tactics' => 'tactics',
            default => 'name',
        };
        $direction = $request->get('sort_type') === 'descending' ? 'desc' : 'asc';

        // Asset + Stratagem supplementary lists (only when a text/cost field
        // could match either). Mirrors the Malifaux Search pattern of merging
        // Upgrade rows alongside Character rows.
        $hasTextOrCost = $request->filled('name') || $request->filled('description')
            || $request->filled('scrip_min') || $request->filled('scrip_max');
        $assetResults = collect();
        $stratagemResults = collect();
        $allegianceCardResults = collect();
        if ($hasTextOrCost) {
            $assetResults = $this->assetMatches($request);
            $stratagemResults = $this->stratagemMatches($request);
            $allegianceCardResults = $this->allegianceCardMatches($request);
        }

        $hasMixed = $assetResults->isNotEmpty()
            || $stratagemResults->isNotEmpty()
            || $allegianceCardResults->isNotEmpty();

        if ($hasMixed) {
            // Mixed result type — pull all Units to allow unified sort across
            // Unit + Asset + Stratagem rows.
            $allUnits = $query->orderBy($sort, $direction)->orderBy('name')->get();
            $rows = [];
            foreach ($allUnits as $u) {
                $rows[] = array_merge($u->toArray(), [
                    'result_type' => 'unit',
                    '_sort_key' => $this->unitSortKey($u, $sort),
                ]);
            }
            foreach ($assetResults as $a) {
                $rows[] = [
                    'result_type' => 'asset',
                    'id' => $a->id,
                    'slug' => $a->slug,
                    'name' => $a->name,
                    'scrip_cost' => $a->scrip_cost,
                    'body' => $a->body,
                    '_sort_key' => $sort === 'scrip' ? sprintf('%012d', $a->scrip_cost ?? 0) : strtolower((string) $a->name),
                ];
            }
            foreach ($stratagemResults as $s) {
                $rows[] = [
                    'result_type' => 'stratagem',
                    'id' => $s->id,
                    'slug' => $s->slug,
                    'name' => $s->name,
                    'tactical_cost' => $s->tactical_cost,
                    'effect' => $s->effect,
                    '_sort_key' => $sort === 'scrip' ? sprintf('%012d', $s->tactical_cost ?? 0) : strtolower((string) $s->name),
                ];
            }
            foreach ($allegianceCardResults as $c) {
                $rows[] = [
                    'result_type' => 'allegiance_card',
                    'id' => $c->id,
                    'slug' => $c->slug,
                    'name' => $c->name,
                    'body' => $c->body,
                    'image_path' => $c->image_path,
                    'allegiance' => $c->relationLoaded('allegiance') && $c->allegiance ? [
                        'id' => $c->allegiance->id,
                        'slug' => $c->allegiance->slug,
                        'name' => $c->allegiance->name,
                    ] : null,
                    // No native cost field — sort cards by name on numeric sorts
                    // so they don't collapse to a single bucket at zero.
                    '_sort_key' => strtolower((string) $c->name),
                ];
            }

            usort($rows, fn (array $a, array $b) => $direction === 'desc'
                ? strcmp($b['_sort_key'], $a['_sort_key'])
                : strcmp($a['_sort_key'], $b['_sort_key']));

            foreach ($rows as &$r) {
                unset($r['_sort_key']);
            }
            unset($r);

            $page = max(1, (int) $request->get('page', 1));
            $totalResults = count($rows);
            $results = new LengthAwarePaginator(
                array_values(array_slice($rows, ($page - 1) * $perPage, $perPage)),
                $totalResults,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            $unitCount = $allUnits->count();
            $assetCount = $assetResults->count();
            $stratagemCount = $stratagemResults->count();
            $allegianceCardCount = $allegianceCardResults->count();
        } else {
            $results = $query->orderBy($sort, $direction)->orderBy('name')->paginate($perPage)->withQueryString();
            $results->setCollection($results->getCollection()->map(function ($u) { // @phpstan-ignore argument.type
                $row = $u->toArray();
                $row['result_type'] = 'unit';

                return $row;
            }));
            $unitCount = $totalResults = $results->total();
            $assetCount = 0;
            $stratagemCount = 0;
            $allegianceCardCount = 0;
        }

        return inertia('TOS/Search/Index', [
            'results' => $results,
            'result_count' => $totalResults,
            'result_breakdown' => [
                'units' => $unitCount,
                'assets' => $assetCount,
                'stratagems' => $stratagemCount,
                'allegiance_cards' => $allegianceCardCount,
            ],
            // Filter dropdowns rarely change between requests — cache for 5
            // minutes keyed by the parent table's max(updated_at) so admin
            // edits invalidate naturally.
            'allegiances' => fn () => $this->cachedSelect('tos.search.allegiances', Allegiance::class, fn () => Allegiance::query()->orderBy('name')->get(['id', 'slug', 'name', 'is_syndicate'])
                ->map(fn ($a) => ['name' => $a->name, 'value' => $a->slug])->all()),
            'special_rules' => fn () => $this->cachedSelect('tos.search.special_rules', SpecialUnitRule::class, fn () => SpecialUnitRule::query()->orderBy('name')->get(['slug', 'name'])
                ->map(fn ($r) => ['name' => $r->name, 'value' => $r->slug])->all()),
            'restriction_options' => fn () => AllegianceTypeEnum::toSelectOptions(),
            'action_types' => fn () => ActionTypeEnum::toSelectOptions(),
            'usage_limits' => fn () => UsageLimitEnum::toSelectOptions(),
            'actions_list' => fn () => $this->cachedSelect('tos.search.actions', Action::class, fn () => Action::query()->select('name')->distinct()->orderBy('name')->get()
                ->map(fn ($a) => ['name' => $a->name, 'value' => $a->name])->all()),
            'abilities_list' => fn () => $this->cachedSelect('tos.search.abilities', Ability::class, fn () => Ability::query()->select('name')->distinct()->orderBy('name')->get()
                ->map(fn ($a) => ['name' => $a->name, 'value' => $a->name])->all()),
            'triggers_list' => fn () => $this->cachedSelect('tos.search.triggers', Trigger::class, fn () => Trigger::query()->select('name')->distinct()->orderBy('name')->get()
                ->map(fn ($t) => ['name' => $t->name, 'value' => $t->name])->all()),
            'sort_options' => [
                ['name' => 'Name', 'value' => 'name'],
                ['name' => 'Scrip', 'value' => 'scrip'],
                ['name' => 'Tactics', 'value' => 'tactics'],
            ],
            'sort_types' => [
                ['name' => 'Ascending', 'value' => 'ascending'],
                ['name' => 'Descending', 'value' => 'descending'],
            ],
            'page_view' => $pageView,
            'view_options' => [
                ['name' => 'Cards', 'value' => 'cards'],
                ['name' => 'Table', 'value' => 'table'],
                ['name' => 'Full', 'value' => 'full'],
            ],
            'saved_searches' => fn () => $request->user()
                ? \App\Models\SavedSearch::query()
                    ->where('user_id', $request->user()->id)
                    ->where('game_system', 'tos')
                    ->orderBy('name')
                    ->get(['id', 'name', 'query_params'])
                : [],
        ]);
    }

    /**
     * Cache a search-page filter dropdown for 5 minutes, keyed off the parent
     * table's max(updated_at) so admin CRUD invalidates the entry transparently.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  callable(): array<int, array<string, string>>  $resolver
     * @return array<int, array<string, string>>
     */
    private function cachedSelect(string $key, string $model, callable $resolver): array
    {
        $stamp = $model::query()->max('updated_at') ?? 'empty';
        $cacheKey = "{$key}.{$stamp}";

        return Cache::remember($cacheKey, 300, $resolver);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = Unit::query()->with(['allegiances:id,name', 'specialUnitRules:id,name', 'sides'])->notCombinedArmsChild();
        $this->applyUnitFilters($query, $request);
        $units = $query->orderBy('name')->get();

        return response()->streamDownload(function () use ($units) {
            $fh = fopen('php://output', 'wb');
            fputcsv($fh, [
                'Name', 'Title', 'Allegiances', 'Special Rules', 'Scrip',
                'Tactics (Std)', 'Tactics (Glory)',
                'Std Sp', 'Std Df', 'Std Wp', 'Std Ar',
                'Glory Sp', 'Glory Df', 'Glory Wp', 'Glory Ar',
            ]);
            foreach ($units as $u) {
                $std = $u->sides->firstWhere('side', \App\Enums\TOS\UnitSideEnum::Standard);
                $glory = $u->sides->firstWhere('side', \App\Enums\TOS\UnitSideEnum::Glory);
                fputcsv($fh, [
                    $u->name,
                    $u->title,
                    $u->allegiances->pluck('name')->implode('|'),
                    $u->specialUnitRules->pluck('name')->implode('|'),
                    $u->scrip,
                    $u->tactics,
                    $u->effectiveGloryTactics(),
                    $std?->speed, $std?->defense, $std?->willpower, $std?->armor,
                    $glory?->speed, $glory?->defense, $glory?->willpower, $glory?->armor,
                ]);
            }
            fclose($fh);
        }, 'tos-search-results.csv', ['Content-Type' => 'text/csv']);
    }

    public function saveSearch(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'query_params' => ['required', 'array'],
        ]);

        \App\Models\SavedSearch::create([
            'user_id' => $request->user()->id,
            'game_system' => 'tos',
            'name' => $validated['name'],
            'query_params' => $validated['query_params'],
        ]);

        return back()->withMessage('Search saved.');
    }

    public function deleteSavedSearch(Request $request, \App\Models\SavedSearch $savedSearch): \Illuminate\Http\RedirectResponse
    {
        abort_unless($savedSearch->user_id === $request->user()->id && $savedSearch->game_system === 'tos', 403);
        $savedSearch->delete();

        return back()->withMessage('Search deleted.');
    }

    /**
     * Apply every Unit-targeting filter from the request onto the query.
     * Extracted so it can be reused by both `view()` and `export()` without
     * drifting between the two surfaces.
     */
    private function applyUnitFilters(Builder $query, Request $request): void
    {
        if ($request->filled('name')) {
            $name = (string) $request->get('name');
            $query->where(function ($q) use ($name) {
                $q->where('name', 'LIKE', "%{$name}%")
                    ->orWhere('title', 'LIKE', "%{$name}%")
                    ->orWhere('description', 'LIKE', "%{$name}%");
            });
        }

        // Allegiance multi (AND/OR + exclude).
        if ($request->filled('allegiance')) {
            $slugs = array_filter(explode(',', (string) $request->get('allegiance')));
            if ($slugs) {
                if ($request->get('allegiance_logic') === 'or') {
                    $query->whereHas('allegiances', fn ($q) => $q->whereIn('slug', $slugs));
                } else {
                    foreach ($slugs as $slug) {
                        $query->whereHas('allegiances', fn ($q) => $q->where('slug', $slug));
                    }
                }
            }
        }
        if ($request->filled('allegiance_exclude')) {
            $excluded = array_filter(explode(',', (string) $request->get('allegiance_exclude')));
            if ($excluded) {
                $query->whereDoesntHave('allegiances', fn ($q) => $q->whereIn('slug', $excluded));
            }
        }

        // Restriction (Neutral pool — null-or-match-type filter).
        if ($request->filled('restriction')) {
            $query->where('restriction', $request->get('restriction'));
        }

        // Special rule multi (AND/OR + exclude).
        if ($request->filled('special_rule')) {
            $slugs = array_filter(explode(',', (string) $request->get('special_rule')));
            if ($slugs) {
                if ($request->get('special_rule_logic') === 'or') {
                    $query->whereHas('specialUnitRules', fn ($q) => $q->whereIn('slug', $slugs));
                } else {
                    foreach ($slugs as $slug) {
                        $query->whereHas('specialUnitRules', fn ($q) => $q->where('slug', $slug));
                    }
                }
            }
        }
        if ($request->filled('special_rule_exclude')) {
            $excluded = array_filter(explode(',', (string) $request->get('special_rule_exclude')));
            if ($excluded) {
                $query->whereDoesntHave('specialUnitRules', fn ($q) => $q->whereIn('slug', $excluded));
            }
        }

        // Scrip range (signed — Commanders carry negative scrip per the
        // sign convention in `tos_units.scrip`).
        if ($request->filled('scrip_min')) {
            $query->where('scrip', '>=', (int) $request->get('scrip_min'));
        }
        if ($request->filled('scrip_max')) {
            $query->where('scrip', '<=', (int) $request->get('scrip_max'));
        }

        if ($request->filled('tactics')) {
            $query->where('tactics', $request->get('tactics'));
        }

        // Glory-side tactics filter. When `glory_tactics` is null on a row
        // the unit's effective Glory Tactics value is its `tactics` column,
        // so the filter has to match either: an explicit override OR the
        // standard column when no override is set.
        if ($request->filled('glory_tactics')) {
            $value = (string) $request->get('glory_tactics');
            $query->where(function ($q) use ($value) {
                $q->where('glory_tactics', $value)
                    ->orWhere(function ($qq) use ($value) {
                        $qq->whereNull('glory_tactics')->where('tactics', $value);
                    });
            });
        }

        // Side stat filters — `side=standard|glory|both` controls which
        // tos_unit_sides rows are matched. Default 'both' = matches if EITHER
        // side has a row meeting the constraint.
        $statFilters = [];
        foreach (['speed', 'defense', 'willpower', 'armor'] as $stat) {
            if ($request->filled("{$stat}_min")) {
                $statFilters[$stat]['>='] = (int) $request->get("{$stat}_min");
            }
            if ($request->filled("{$stat}_max")) {
                $statFilters[$stat]['<='] = (int) $request->get("{$stat}_max");
            }
        }
        if ($statFilters) {
            $sideScope = $request->get('side', 'both');
            $sideFilter = function (Builder $sq) use ($statFilters, $sideScope) {
                if (in_array($sideScope, ['standard', 'glory'], true)) {
                    $sq->where('side', $sideScope);
                }
                foreach ($statFilters as $stat => $ops) {
                    foreach ($ops as $op => $val) {
                        $sq->where($stat, $op, $val);
                    }
                }
            };
            $query->whereHas('sides', $sideFilter);
        }

        // Action filters — grouped through whereHas so all match the same
        // action row, not one match across N joined rows.
        $actionFilterKeys = [
            'action_name', 'action_type', 'action_av_min', 'action_av_max',
            'action_strength_min', 'action_strength_max', 'action_range',
            'action_usage_limit', 'action_description',
            'action_is_piercing', 'action_is_accurate', 'action_is_area',
        ];
        if ($this->anyFilled($request, $actionFilterKeys)) {
            $query->whereHas('sides.actions', function ($q) use ($request) {
                if ($request->filled('action_name')) {
                    $q->where('name', 'LIKE', '%'.$request->get('action_name').'%');
                }
                if ($request->filled('action_type')) {
                    $types = array_filter(explode(',', (string) $request->get('action_type')));
                    if ($types) {
                        $q->whereHas('typeLinks', fn ($qq) => $qq->whereIn('type', $types));
                    }
                }
                if ($request->filled('action_av_min')) {
                    $q->where('av', '>=', (int) $request->get('action_av_min'));
                }
                if ($request->filled('action_av_max')) {
                    $q->where('av', '<=', (int) $request->get('action_av_max'));
                }
                if ($request->filled('action_strength_min')) {
                    $q->where('strength', '>=', (int) $request->get('action_strength_min'));
                }
                if ($request->filled('action_strength_max')) {
                    $q->where('strength', '<=', (int) $request->get('action_strength_max'));
                }
                if ($request->filled('action_range')) {
                    $q->where('range', 'LIKE', '%'.$request->get('action_range').'%');
                }
                if ($request->filled('action_usage_limit')) {
                    $q->where('usage_limit', $request->get('action_usage_limit'));
                }
                if ($request->filled('action_description')) {
                    $q->where('body', 'LIKE', '%'.$request->get('action_description').'%');
                }
                foreach (['is_piercing', 'is_accurate', 'is_area'] as $bool) {
                    if ($request->filled("action_{$bool}")) {
                        $q->where($bool, filter_var($request->get("action_{$bool}"), FILTER_VALIDATE_BOOL));
                    }
                }
            });
        }

        // Ability filters.
        if ($this->anyFilled($request, ['ability_name', 'ability_description'])) {
            $query->whereHas('sides.abilities', function ($q) use ($request) {
                if ($request->filled('ability_name')) {
                    $q->where('name', 'LIKE', '%'.$request->get('ability_name').'%');
                }
                if ($request->filled('ability_description')) {
                    $q->where('body', 'LIKE', '%'.$request->get('ability_description').'%');
                }
            });
        }

        // Trigger filters (target the trigger row directly via the
        // sides.actions.triggers chain).
        if ($this->anyFilled($request, ['trigger_name', 'trigger_suits', 'trigger_description'])) {
            $query->whereHas('sides.actions.triggers', function ($q) use ($request) {
                if ($request->filled('trigger_name')) {
                    $q->where('name', 'LIKE', '%'.$request->get('trigger_name').'%');
                }
                if ($request->filled('trigger_suits')) {
                    $q->where('suits', 'LIKE', '%'.$request->get('trigger_suits').'%');
                }
                if ($request->filled('trigger_description')) {
                    $q->where('body', 'LIKE', '%'.$request->get('trigger_description').'%');
                }
            });
        }

        // Cross-field description search (matches text in actions, abilities,
        // OR triggers — the common rulebook-keyword case).
        if ($request->filled('description')) {
            $desc = (string) $request->get('description');
            $query->where(function ($q) use ($desc) {
                $q->where('description', 'LIKE', "%{$desc}%")
                    ->orWhereHas('sides.actions', fn ($aq) => $aq->where('body', 'LIKE', "%{$desc}%"))
                    ->orWhereHas('sides.abilities', fn ($aq) => $aq->where('body', 'LIKE', "%{$desc}%"))
                    ->orWhereHas('sides.actions.triggers', fn ($tq) => $tq->where('body', 'LIKE', "%{$desc}%"));
            });
        }

        // Boolean has: filters.
        if ($request->filled('has')) {
            foreach (array_filter(explode(',', (string) $request->get('has'))) as $hasFilter) {
                match ($hasFilter) {
                    'commander' => $query->whereHas('specialUnitRules', fn ($q) => $q->where('slug', 'commander')),
                    'sculpts' => $query->whereHas('sculpts'),
                    'combined_arms' => $query->whereNotNull('combined_arms_child_id'),
                    'abilities' => $query->whereHas('sides.abilities'),
                    'actions' => $query->whereHas('sides.actions'),
                    'triggers' => $query->whereHas('sides.actions.triggers'),
                    'neutral' => $query->whereNotNull('restriction'),
                    default => null,
                };
            }
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Asset>
     */
    private function assetMatches(Request $request): \Illuminate\Database\Eloquent\Collection
    {
        $q = Asset::query()->select(['id', 'slug', 'name', 'scrip_cost', 'body', 'image_path']);
        $applied = false;
        if ($request->filled('name')) {
            $q->where(function ($qq) use ($request) {
                $name = (string) $request->get('name');
                $qq->where('name', 'LIKE', "%{$name}%")->orWhere('body', 'LIKE', "%{$name}%");
            });
            $applied = true;
        }
        if ($request->filled('description')) {
            $q->where('body', 'LIKE', '%'.$request->get('description').'%');
            $applied = true;
        }
        if ($request->filled('scrip_min')) {
            $q->where('scrip_cost', '>=', (int) $request->get('scrip_min'));
            $applied = true;
        }
        if ($request->filled('scrip_max')) {
            $q->where('scrip_cost', '<=', (int) $request->get('scrip_max'));
            $applied = true;
        }

        return $applied ? $q->get() : collect();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Stratagem>
     */
    private function stratagemMatches(Request $request): \Illuminate\Database\Eloquent\Collection
    {
        $q = Stratagem::query()->select(['id', 'slug', 'name', 'tactical_cost', 'effect', 'image_path']);
        $applied = false;
        if ($request->filled('name')) {
            $q->where(function ($qq) use ($request) {
                $name = (string) $request->get('name');
                $qq->where('name', 'LIKE', "%{$name}%")->orWhere('effect', 'LIKE', "%{$name}%");
            });
            $applied = true;
        }
        if ($request->filled('description')) {
            $q->where('effect', 'LIKE', '%'.$request->get('description').'%');
            $applied = true;
        }
        // Stratagems carry tactical_cost (tactics tokens), NOT scrip — but the
        // user thinks of them as "expensive cards", so map scrip filters here.
        if ($request->filled('scrip_min')) {
            $q->where('tactical_cost', '>=', (int) $request->get('scrip_min'));
            $applied = true;
        }
        if ($request->filled('scrip_max')) {
            $q->where('tactical_cost', '<=', (int) $request->get('scrip_max'));
            $applied = true;
        }

        return $applied ? $q->get() : collect();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, AllegianceCard>
     */
    private function allegianceCardMatches(Request $request): \Illuminate\Database\Eloquent\Collection
    {
        $q = AllegianceCard::query()
            ->select(['id', 'slug', 'name', 'body', 'image_path', 'allegiance_id'])
            ->with('allegiance:id,slug,name');
        $applied = false;
        if ($request->filled('name')) {
            $q->where(function ($qq) use ($request) {
                $name = (string) $request->get('name');
                $qq->where('name', 'LIKE', "%{$name}%")->orWhere('body', 'LIKE', "%{$name}%");
            });
            $applied = true;
        }
        if ($request->filled('description')) {
            $q->where('body', 'LIKE', '%'.$request->get('description').'%');
            $applied = true;
        }

        // Allegiance Cards have no scrip/tactical-cost field — scrip-range
        // filters are silently inapplicable. They join the result list only
        // when name/description hit. Return an Eloquent collection in both
        // branches so the declared return type holds (Support\Collection
        // is the parent class, not a subtype, so `collect()` would mismatch).
        return $applied ? $q->get() : AllegianceCard::query()->whereRaw('1=0')->get();
    }

    private function unitSortKey(Unit $unit, string $sort): string
    {
        return match ($sort) {
            'scrip' => sprintf('%012d', $unit->scrip ?? 0),
            'tactics' => str_pad((string) ($unit->tactics ?? ''), 8, ' ', STR_PAD_LEFT),
            default => strtolower($unit->name),
        };
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function anyFilled(Request $request, array $keys): bool
    {
        foreach ($keys as $key) {
            if ($request->filled($key)) {
                return true;
            }
        }

        return false;
    }
}
