<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\TerrainResource;
use App\Models\Terrain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Terrains
 */
class TerrainController extends Controller
{
    /**
     * List all terrains
     *
     * Returns a paginated list of terrains, optionally filtered by name.
     *
     * @queryParam search string Filter terrains by name. Example: Forest
     * @queryParam per_page int Number of results per page (max 100). Example: 15
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $terrains = Terrain::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return TerrainResource::collection($terrains);
    }

    /**
     * Get a single terrain
     *
     * Returns a single terrain with its associated markers.
     */
    public function show(Terrain $terrain): TerrainResource
    {
        $terrain->loadMissing('markers');

        return new TerrainResource($terrain);
    }
}
