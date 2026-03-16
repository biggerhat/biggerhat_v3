<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\PackageResource;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PackageController extends Controller
{
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

    public function show(Package $package): PackageResource
    {
        $package->loadMissing(['characters', 'keywords', 'miniatures', 'blueprints', 'storeLinks']);

        return new PackageResource($package);
    }
}
