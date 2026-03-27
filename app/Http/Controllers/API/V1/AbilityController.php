<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\AbilityResource;
use App\Models\Ability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Abilities
 */
class AbilityController extends Controller
{
    /**
     * List all abilities
     *
     * Returns a paginated list of abilities, optionally filtered by name.
     *
     * @queryParam search string Filter abilities by name. Example: Hard to Kill
     * @queryParam per_page int Number of results per page (max 100). Example: 15
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $abilities = Ability::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return AbilityResource::collection($abilities);
    }

    /**
     * Get a single ability
     *
     * Returns a single ability by its ID.
     */
    public function show(Ability $ability): AbilityResource
    {
        return new AbilityResource($ability);
    }
}
