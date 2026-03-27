<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\BlueprintResource;
use App\Models\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Blueprints
 */
class BlueprintController extends Controller
{
    /**
     * List all blueprints
     *
     * Returns a paginated list of blueprints, optionally filtered by name or image availability.
     *
     * @queryParam search string Filter blueprints by name. Example: Ice Golem
     * @queryParam has_image bool Only return blueprints that have an image. Example: true
     * @queryParam per_page int Number of results per page (max 100). Example: 15
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $blueprints = Blueprint::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->when($request->boolean('has_image'), fn ($q) => $q->withImage())
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return BlueprintResource::collection($blueprints);
    }

    /**
     * Get a single blueprint
     *
     * Returns a single blueprint with its associated characters, miniatures, and packages.
     */
    public function show(Blueprint $blueprint): BlueprintResource
    {
        $blueprint->loadMissing(['characters', 'miniatures', 'packages']);

        return new BlueprintResource($blueprint);
    }
}
