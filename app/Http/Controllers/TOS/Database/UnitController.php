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

        // Exclude Combined Arms embedded child cards (rulebook p. 11): those
        // come into play automatically with their parent unit and aren't
        // hired as standalone rows, so they'd mislead in a top-level browse.
        $query = Unit::query()
            ->with(['sides', 'allegiances', 'specialUnitRules', 'sculpts'])
            ->whereDoesntHave('combinedArmsParent')
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
            'units' => $query->get(),
            'rule_filter' => $rule,
            'name_search' => $nameSearch,
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
