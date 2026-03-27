<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\SchemeResource;
use App\Models\Scheme;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Schemes
 */
class SchemeController extends Controller
{
    /**
     * List all schemes
     *
     * Returns a paginated list of schemes, optionally filtered by season or name.
     *
     * @queryParam search string Filter schemes by name. Example: Assassinate
     * @queryParam season string Filter by season. Example: 1
     * @queryParam per_page int Number of results per page (max 100). Example: 15
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $schemes = Scheme::query()
            ->when($request->query('season'), fn ($q, $season) => $q->where('season', $season))
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return SchemeResource::collection($schemes);
    }

    /**
     * Get a single scheme
     *
     * Returns a single scheme by its ID.
     */
    public function show(Scheme $scheme): SchemeResource
    {
        return new SchemeResource($scheme);
    }
}
