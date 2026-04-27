<?php

namespace App\Http\Controllers\API\V1\TOS;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\TOS\AssetResource;
use App\Models\TOS\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags TOS Assets
 */
class AssetController extends Controller
{
    /**
     * List all TOS Assets
     *
     * Returns a paginated list of company-level Assets (vehicles, gear, mounts, etc).
     *
     * @queryParam search string Filter assets by name. Example: Earth Mover
     * @queryParam allegiance string Filter by attached allegiance slug. Example: kings-empire
     * @queryParam per_page int Number of results per page (max 100). Example: 24
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Asset::query()
            ->with(['allegiances', 'abilities', 'actions.triggers', 'limits'])
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->when($request->query('allegiance'), fn ($q, $slug) => $q->whereHas(
                'allegiances',
                fn ($qq) => $qq->where('tos_allegiances.slug', $slug)
            ))
            ->orderBy('scrip_cost')
            ->orderBy('name');

        return AssetResource::collection(
            $query->paginate(min((int) $request->query('per_page', 24), 100))
        );
    }

    /**
     * Get a single TOS Asset
     *
     * Returns a single asset with allegiances, abilities, actions (with triggers), and attachment limits.
     */
    public function show(Asset $asset): AssetResource
    {
        $asset->loadMissing(['allegiances', 'abilities', 'actions.triggers', 'limits']);

        return new AssetResource($asset);
    }
}
