<?php

namespace App\Http\Controllers\TOS\Database;

use App\Http\Controllers\Controller;
use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;
use Illuminate\Http\Request;

class CompareController extends Controller
{
    /**
     * Side-by-side TOS unit comparator. Up to four units chosen by sculpt
     * slug (`?units=slug1,slug2,...`) — sculpt slugs are the canonical
     * shareable identifier on the Units/View page, so the URL composes
     * naturally from copy-paste links.
     *
     * Loads the same relation tree that powers `Units/View` so the comparator
     * cards can show both Standard + Glory AVs, abilities, actions, and
     * triggers without a second request.
     */
    public function index(Request $request)
    {
        $maxUnits = 4;
        $slugs = collect(explode(',', (string) $request->get('units', '')))
            ->filter()
            ->unique()
            ->take($maxUnits)
            ->values();

        $units = Unit::query()
            ->with([
                'sides.abilities:id,slug,name,body',
                'sides.actions.triggers:id,slug,name,suits,margin_cost,timing,body',
                'sides.actions.typeLinks',
                'allegiances:id,slug,name,color_slug',
                'specialUnitRules:id,slug,name',
                'sculpts',
            ])
            ->whereHas('sculpts', fn ($q) => $q->whereIn('slug', $slugs))
            ->get()
            // Preserve URL order — the comparator should keep the user's
            // chosen left-to-right sequence even if the DB returns them
            // in a different order. Match by ANY sculpt slug intersecting
            // the URL (a unit may have multiple sculpts, and the URL might
            // reference the second/third one rather than the first).
            ->sortBy(fn (Unit $u) => $slugs->search($u->sculpts->pluck('slug')->intersect($slugs)->first()))
            ->values();

        // Picker options — every sculpt with both a slug and a parent unit.
        // Lazy via a closure so partial reloads triggered by `units=` URL
        // changes don't refetch the list (mirrors the Malifaux admin pattern).
        $options = fn () => UnitSculpt::query()
            ->with(['unit:id,name,title'])
            ->orderBy('name')
            ->get()
            ->map(fn (UnitSculpt $s) => [
                'value' => $s->slug,
                'name' => trim(($s->unit->name ?? '').($s->name ? " — {$s->name}" : '')),
            ])
            ->values();

        return inertia('TOS/Compare/Index', [
            'units' => $units,
            'selected_slugs' => $slugs,
            'max_units' => $maxUnits,
            'sculpt_options' => $options,
        ]);
    }
}
