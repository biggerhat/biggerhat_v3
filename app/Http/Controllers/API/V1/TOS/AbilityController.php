<?php

namespace App\Http\Controllers\API\V1\TOS;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\TOS\AbilityResource;
use App\Models\TOS\Ability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags TOS Abilities
 */
class AbilityController extends Controller
{
    /**
     * List all TOS Abilities
     *
     * Returns a paginated list of TOS abilities. General abilities are shared across allegiances;
     * non-general abilities are tied to a specific Allegiance.
     *
     * @queryParam search string Filter by name. Example: Tough
     * @queryParam is_general bool Filter to general (true) or allegiance-specific (false) abilities. Example: true
     * @queryParam allegiance string Filter by allegiance slug (returns its specific abilities + general pool when combined with is_general). Example: kings-empire
     * @queryParam per_page int Number of results per page (max 100). Example: 50
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Ability::query()
            ->with('allegiance')
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->when($request->has('is_general'), fn ($q) => $q->where('is_general', $request->boolean('is_general')))
            ->when($request->query('allegiance'), fn ($q, $slug) => $q->whereHas('allegiance', fn ($qq) => $qq->where('slug', $slug)))
            ->orderBy('name');

        return AbilityResource::collection(
            $query->paginate(min((int) $request->query('per_page', 50), 100))
        );
    }

    /**
     * Get a single TOS Ability
     */
    public function show(Ability $ability): AbilityResource
    {
        $ability->loadMissing('allegiance');

        return new AbilityResource($ability);
    }
}
