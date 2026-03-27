<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\PackageResource;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Packages
 */
class PackageController extends Controller
{
    /**
     * List all packages
     *
     * Returns a paginated list of packages, optionally filtered by name, category, or faction.
     *
     * @queryParam search string Filter packages by name. Example: Rasputina Core Box
     * @queryParam category string Filter by package category. Example: core_box
     * @queryParam faction string Filter by faction (matches within the factions JSON array). Example: arcanists
     * @queryParam per_page int Number of results per page (max 100). Example: 15
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $packages = Package::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->when($request->query('category'), fn ($q, $category) => $q->where('category', $category))
            ->when($request->query('faction'), fn ($q, $faction) => $q->whereJsonContains('factions', $faction))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return PackageResource::collection($packages);
    }

    /**
     * Get a single package
     *
     * Returns a single package with its associated characters, keywords, miniatures, blueprints, and store links.
     */
    public function show(Package $package): PackageResource
    {
        $package->loadMissing(['characters', 'keywords', 'miniatures', 'blueprints', 'storeLinks']);

        return new PackageResource($package);
    }
}
