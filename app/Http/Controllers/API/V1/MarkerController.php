<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\MarkerResource;
use App\Models\Marker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MarkerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $markers = Marker::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return MarkerResource::collection($markers);
    }

    public function show(Marker $marker): MarkerResource
    {
        $marker->loadMissing('terrains');

        return new MarkerResource($marker);
    }
}
