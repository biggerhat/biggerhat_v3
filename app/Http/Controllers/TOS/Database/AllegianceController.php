<?php

namespace App\Http\Controllers\TOS\Database;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\TOS\Allegiance;
use App\Models\TOS\SpecialUnitRule;
use Illuminate\Http\Request;

class AllegianceController extends Controller
{
    public function index(Request $request)
    {
        $nameSearch = $request->filled('name_search') ? trim((string) $request->get('name_search')) : null;
        $pageView = $request->get('page_view', 'cards');
        $perPage = $pageView === 'table' ? 50 : 24;

        $query = Allegiance::query()
            ->orderBy('is_syndicate')
            ->orderBy('sort_order')
            ->orderBy('name');
        if ($nameSearch) {
            $query->where('name', 'LIKE', "%{$nameSearch}%");
        }

        return inertia('TOS/Allegiances/Index', [
            'allegiances' => $query->paginate($perPage)->withQueryString(),
            'name_search' => $nameSearch,
            'page_view' => $pageView,
        ]);
    }

    /**
     * Type-pooled roster page: shows every Unit hireable into ANY Allegiance
     * of the given type (`earth` or `malifaux`), plus the matching Neutral
     * pool. Mounts on the same `TOS/Allegiances/View` Vue component the
     * single-allegiance view uses, with a synthetic "allegiance" payload so
     * the page header renders sensibly.
     */
    public function viewByType(Request $request, string $type)
    {
        $enum = AllegianceTypeEnum::tryFrom($type);
        if ($enum === null) {
            abort(404);
        }

        $specialRule = $request->filled('special_rule') ? (string) $request->get('special_rule') : null;
        $sort = (string) $request->get('sort', 'name');
        $sortType = (string) $request->get('sort_type', 'ascending');
        $pageView = (string) $request->get('page_view', 'cards');

        if (! in_array($sort, ['name', 'scrip', 'tactics'], true)) {
            $sort = 'name';
        }
        $direction = $sortType === 'descending' ? 'desc' : 'asc';

        // Pool of Allegiance ids of the given type — each Unit only needs to
        // belong to ONE of them OR carry a matching `restriction` to make the
        // roster (mirrors Unit::hireableInto for an arbitrary set).
        $allegianceIds = Allegiance::ofType($enum)->pluck('id');
        $allegianceCount = $allegianceIds->count();

        $query = \App\Models\TOS\Unit::query()
            ->with([
                'sides:id,unit_id,side,speed,defense,willpower,armor',
                'sculpts',
                'specialUnitRules:id,name,slug',
                'allegiances:id,slug,name',
            ])
            ->where(function ($q) use ($allegianceIds, $enum) {
                $q->whereHas('allegiances', fn ($inner) => $inner->whereIn('tos_allegiances.id', $allegianceIds))
                    ->orWhere('restriction', $enum->value);
            });

        if ($specialRule !== null && $specialRule !== '') {
            $query->whereHas('specialUnitRules', fn ($q) => $q->where('slug', $specialRule));
        }

        $units = $query->orderBy($sort, $direction)->orderBy('name')->get();

        /** @var array<int, \App\Models\TOS\Unit> $unitsArray */
        $unitsArray = $units->all();
        $statistics = $this->statisticsFor($unitsArray);

        // Synthetic allegiance object for the View page header. The slug is
        // namespaced (e.g. `type-earth`) so AllegianceLogo's tos_allegiance_info
        // lookup falls through to the Shield fallback rather than mistakenly
        // resolving a real allegiance.
        $synthetic = (object) [
            'id' => 0,
            'slug' => "type-{$enum->value}",
            'name' => $enum->label().' Side',
            'short_name' => null,
            'type' => $enum->value,
            'is_syndicate' => false,
            'description' => "All Units that hire into any {$enum->label()} Allegiance, plus the Neutral ({$enum->label()}) pool. {$allegianceCount} {$enum->label()} ".($allegianceCount === 1 ? 'allegiance' : 'allegiances').' represented.',
            'logo_path' => null,
            'color_slug' => null,
        ];

        return inertia('TOS/Allegiances/View', [
            'allegiance' => $synthetic,
            'units' => $units,
            'statistics' => $statistics,
            'special_rule' => $specialRule,
            'page_view' => $pageView,
            'sort' => $sort,
            'sort_type' => $sortType,
            'special_rules' => SpecialUnitRule::query()
                ->orderBy('name')
                ->get(['id', 'slug', 'name'])
                ->map(fn ($r) => ['name' => $r->name, 'value' => $r->slug]),
            'sort_options' => [
                ['name' => 'Name', 'value' => 'name'],
                ['name' => 'Scrip', 'value' => 'scrip'],
                ['name' => 'Tactics', 'value' => 'tactics'],
            ],
            'sort_types' => [
                ['name' => 'Ascending', 'value' => 'ascending'],
                ['name' => 'Descending', 'value' => 'descending'],
            ],
        ]);
    }

    public function view(Request $request, Allegiance $allegiance)
    {
        // Allegiance page lists only units directly attached via the
        // tos_allegiance_unit pivot — the cross-type Neutral pool stays
        // out of the per-allegiance roster (browse it via the Units index
        // or surface it in the company builder).
        $specialRule = $request->filled('special_rule') ? (string) $request->get('special_rule') : null;
        $sort = (string) $request->get('sort', 'name');
        $sortType = (string) $request->get('sort_type', 'ascending');
        $pageView = (string) $request->get('page_view', 'cards');

        $allowedSorts = ['name', 'scrip', 'tactics'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'name';
        }
        $direction = $sortType === 'descending' ? 'desc' : 'asc';

        $query = $allegiance->units()
            ->with([
                'sides:id,unit_id,side,speed,defense,willpower,armor',
                'sculpts',
                'specialUnitRules:id,name,slug',
                'allegiances:id,slug',
            ]);

        if ($specialRule !== null && $specialRule !== '') {
            $query->whereHas('specialUnitRules', fn ($q) => $q->where('slug', $specialRule));
        }

        $units = $query->orderBy($sort, $direction)->orderBy('name')->get();

        // Stats block — counts of units by special-unit-rule + average scrip.
        // Cheap because the units array is already in memory. all() drops the
        // collection wrapper into a plain array<Unit> so static analysis sees
        // the concrete element type.
        /** @var array<int, \App\Models\TOS\Unit> $unitsArray */
        $unitsArray = $units->all();
        $statistics = $this->statisticsFor($unitsArray);

        return inertia('TOS/Allegiances/View', [
            'allegiance' => $allegiance,
            'units' => $units,
            'statistics' => $statistics,
            'special_rule' => $specialRule,
            'page_view' => $pageView,
            'sort' => $sort,
            'sort_type' => $sortType,
            'special_rules' => SpecialUnitRule::query()
                ->orderBy('name')
                ->get(['id', 'slug', 'name'])
                ->map(fn ($r) => ['name' => $r->name, 'value' => $r->slug]),
            'sort_options' => [
                ['name' => 'Name', 'value' => 'name'],
                ['name' => 'Scrip', 'value' => 'scrip'],
                ['name' => 'Tactics', 'value' => 'tactics'],
            ],
            'sort_types' => [
                ['name' => 'Ascending', 'value' => 'ascending'],
                ['name' => 'Descending', 'value' => 'descending'],
            ],
        ]);
    }

    /**
     * Aggregate stats for the unit roster shown on an Allegiance page.
     * Returns counts by Special Unit Rule (so the UI can chip the roster
     * shape: 1 Commander, 3 Titans, 4 Fireteams, etc.) plus a sum/average
     * scrip cost.
     *
     * @param  iterable<\App\Models\TOS\Unit>  $units
     * @return array{total: int, total_scrip: int, average_scrip: float|null, by_rule: array<int, array{slug: string, name: string, count: int}>}
     */
    private function statisticsFor(iterable $units): array
    {
        $total = 0;
        $byRule = [];
        $totalScrip = 0;

        foreach ($units as $unit) {
            $total++;
            $totalScrip += (int) ($unit->scrip ?? 0);
            foreach ($unit->specialUnitRules ?? [] as $rule) {
                $key = $rule->slug;
                if (! isset($byRule[$key])) {
                    $byRule[$key] = ['slug' => $rule->slug, 'name' => $rule->name, 'count' => 0];
                }
                $byRule[$key]['count']++;
            }
        }

        usort($byRule, fn ($a, $b) => $b['count'] <=> $a['count']);

        return [
            'total' => $total,
            'total_scrip' => $totalScrip,
            'average_scrip' => $total > 0 ? round($totalScrip / $total, 1) : null,
            'by_rule' => $byRule,
        ];
    }
}
