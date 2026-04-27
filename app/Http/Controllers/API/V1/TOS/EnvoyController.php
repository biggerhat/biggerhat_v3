<?php

namespace App\Http\Controllers\API\V1\TOS;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\TOS\EnvoyResource;
use App\Models\TOS\Envoy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags TOS Envoys
 */
class EnvoyController extends Controller
{
    /**
     * List all TOS Envoys
     *
     * Returns a paginated list of Envoys (Syndicate-attached units that bridge Allegiances).
     *
     * @queryParam search string Filter envoys by name. Example: Mr. Tannen
     * @queryParam allegiance string Filter by allegiance slug. Example: ten-thunders
     * @queryParam restriction string Filter by restriction (earth, malifaux, other). Example: earth
     * @queryParam per_page int Number of results per page (max 100). Example: 24
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Envoy::query()
            ->with(['allegiance', 'abilities'])
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->when($request->query('allegiance'), fn ($q, $slug) => $q->whereHas('allegiance', fn ($qq) => $qq->where('slug', $slug)))
            ->when($request->query('restriction'), fn ($q, $r) => $q->where('restriction', $r))
            ->orderBy('sort_order')
            ->orderBy('name');

        return EnvoyResource::collection(
            $query->paginate(min((int) $request->query('per_page', 24), 100))
        );
    }

    /**
     * Get a single TOS Envoy
     *
     * Returns a single envoy with allegiance and abilities.
     */
    public function show(Envoy $envoy): EnvoyResource
    {
        $envoy->loadMissing(['allegiance', 'abilities']);

        return new EnvoyResource($envoy);
    }
}
