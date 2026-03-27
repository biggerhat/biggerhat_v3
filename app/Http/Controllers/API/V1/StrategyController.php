<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\StrategyResource;
use App\Models\Strategy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Strategies
 */
class StrategyController extends Controller
{
    /**
     * List all strategies
     *
     * Returns a paginated list of strategies, optionally filtered by season or name.
     *
     * @queryParam search string Filter strategies by name. Example: Plant Explosives
     * @queryParam season string Filter by season. Example: 1
     * @queryParam per_page int Number of results per page (max 100). Example: 15
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $strategies = Strategy::query()
            ->when($request->query('season'), fn ($q, $season) => $q->where('season', $season))
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return StrategyResource::collection($strategies);
    }

    /**
     * Get a single strategy
     *
     * Returns a single strategy by its ID.
     */
    public function show(Strategy $strategy): StrategyResource
    {
        return new StrategyResource($strategy);
    }
}
