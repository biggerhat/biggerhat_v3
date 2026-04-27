<?php

namespace App\Http\Controllers\API\V1\TOS;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\TOS\SpecialUnitRuleResource;
use App\Models\TOS\SpecialUnitRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags TOS Special Unit Rules
 */
class SpecialUnitRuleController extends Controller
{
    /**
     * List all TOS Special Unit Rules
     *
     * Returns Special Unit Rules (Commander, Titan, Fireteam, Squad, Champion, Reserves, Combined Arms, etc).
     *
     * @queryParam search string Filter by name. Example: Commander
     * @queryParam per_page int Number of results per page (max 100). Example: 50
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SpecialUnitRule::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('sort_order')
            ->orderBy('name');

        return SpecialUnitRuleResource::collection(
            $query->paginate(min((int) $request->query('per_page', 50), 100))
        );
    }

    /**
     * Get a single Special Unit Rule
     */
    public function show(SpecialUnitRule $specialUnitRule): SpecialUnitRuleResource
    {
        return new SpecialUnitRuleResource($specialUnitRule);
    }
}
