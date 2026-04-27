<?php

namespace App\Http\Controllers\API\V1\TOS;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\TOS\UnitResource;
use App\Models\TOS\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags TOS Units
 */
class UnitController extends Controller
{
    /**
     * List all TOS Units
     *
     * Returns a paginated list of units with sides, sculpts, allegiances, and special unit rules.
     * Combined Arms child units are excluded by default; pass `include_combined_arms_children=1` to include them.
     *
     * @queryParam search string Filter units by name or title. Example: Earth Elemental
     * @queryParam allegiance string Filter by allegiance slug. Example: kings-empire
     * @queryParam special_rule string Filter by special unit rule slug (e.g. commander, titan). Example: commander
     * @queryParam restriction string Filter by neutral restriction (earth, malifaux). Example: earth
     * @queryParam include_combined_arms_children bool Include child units of Combined Arms parents. Defaults to false. Example: false
     * @queryParam per_page int Number of results per page (max 100). Example: 24
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $includeChildren = $request->boolean('include_combined_arms_children');

        $query = Unit::query()
            ->with(['sides.abilities', 'sides.actions.triggers', 'sculpts', 'allegiances', 'specialUnitRules'])
            ->when(! $includeChildren, fn ($q) => $q->notCombinedArmsChild())
            ->when($request->query('search'), fn ($q, $search) => $q->where(function ($qq) use ($search) {
                $qq->where('name', 'LIKE', "%{$search}%")->orWhere('title', 'LIKE', "%{$search}%");
            }))
            ->when($request->query('allegiance'), fn ($q, $slug) => $q->whereHas(
                'allegiances',
                fn ($qq) => $qq->where('tos_allegiances.slug', $slug)
            ))
            ->when($request->query('special_rule'), fn ($q, $slug) => $q->whereHas(
                'specialUnitRules',
                fn ($qq) => $qq->where('tos_special_unit_rules.slug', $slug)
            ))
            ->when($request->query('restriction'), fn ($q, $r) => $q->where('restriction', $r))
            ->orderBy('scrip')
            ->orderBy('name');

        return UnitResource::collection(
            $query->paginate(min((int) $request->query('per_page', 24), 100))
        );
    }

    /**
     * Get a single TOS Unit
     *
     * Returns a single unit looked up by the Unit's own slug (Unit::getRouteKeyName) with full related data. The public web view uses sculpt slugs so users land on a specific art variant; the API exposes the canonical unit slug since responses already include every sculpt.
     */
    public function show(Unit $unit): UnitResource
    {
        $unit->loadMissing(['sides.abilities', 'sides.actions.triggers', 'sculpts', 'allegiances', 'specialUnitRules']);

        return new UnitResource($unit);
    }
}
