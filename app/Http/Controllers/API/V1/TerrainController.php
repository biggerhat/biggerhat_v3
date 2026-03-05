<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\TerrainResource;
use App\Models\Terrain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TerrainController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $terrains = Terrain::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return TerrainResource::collection($terrains);
    }

    public function show(Terrain $terrain): TerrainResource
    {
        $terrain->loadMissing('markers');

        return new TerrainResource($terrain);
    }
}
