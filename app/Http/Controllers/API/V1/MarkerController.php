<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\MarkerResource;
use App\Models\Marker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Markers
 */
class MarkerController extends Controller
{
    /**
     * List all markers
     *
     * Returns a paginated list of markers, optionally filtered by name.
     *
     * @queryParam search string Filter markers by name. Example: Scrap
     * @queryParam per_page int Number of results per page (max 100). Example: 15
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $markers = Marker::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return MarkerResource::collection($markers);
    }

    /**
     * Get a single marker
     *
     * Returns a single marker with its associated terrains.
     */
    public function show(Marker $marker): MarkerResource
    {
        $marker->loadMissing('terrains');

        return new MarkerResource($marker);
    }
}
