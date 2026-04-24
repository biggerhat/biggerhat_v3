<?php

namespace App\Http\Controllers\TOS\Database;

use App\Enums\TOS\SpecialUnitRuleEnum;
use App\Http\Controllers\Controller;
use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(Request $request, ?string $rule = null)
    {
        // The friendly per-type alias routes (/tos/commanders etc.) call this
        // method with $rule pre-set; the canonical /tos/units route reads it
        // from the query string (so useListFiltering on the client can
        // partial-reload without changing URL segments).
        $rule = $rule ?? ($request->filled('rule') ? (string) $request->get('rule') : null);
        $nameSearch = $request->filled('name_search') ? trim((string) $request->get('name_search')) : null;
        $pageView = $request->get('page_view', 'cards');
        $perPage = $pageView === 'table' ? 50 : 24;

        // Browse surfaces every unit — Combined Arms child cards (rulebook
        // p. 11) ARE browseable in the database (users need to read Komainu's
        // card even though it's hired via Lien). The "only-parent" filter
        // belongs in the crew builder, not the reference library.
        $query = Unit::query()
            ->with(['sides', 'allegiances', 'specialUnitRules', 'sculpts'])
            ->orderBy('name');

        if ($rule !== null && $rule !== '') {
            $query->whereHas('specialUnitRules', fn ($q) => $q->where('slug', $rule));
        }

        if ($nameSearch) {
            $query->where(function ($q) use ($nameSearch) {
                $q->where('name', 'LIKE', "%{$nameSearch}%")
                    ->orWhere('title', 'LIKE', "%{$nameSearch}%");
            });
        }

        return inertia('TOS/Units/Index', [
            'units' => $query->paginate($perPage)->withQueryString(),
            'rule_filter' => $rule,
            'name_search' => $nameSearch,
            'page_view' => $pageView,
            'special_rules' => SpecialUnitRuleEnum::toSelectOptions(),
        ]);
    }

    public function view(UnitSculpt $sculpt)
    {
        $unit = $sculpt->unit()->with([
            'sides.abilities',
            'sides.actions.triggers',
            'sides.actions.typeLinks',
            'allegiances',
            'specialUnitRules',
            'sculpts',
            'combinedArmsChild',
        ])->firstOrFail();

        return inertia('TOS/Units/View', [
            'unit' => $unit,
            'active_sculpt' => $sculpt,
        ]);
    }
}
