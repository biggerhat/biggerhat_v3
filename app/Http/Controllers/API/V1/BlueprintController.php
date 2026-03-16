<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\BlueprintResource;
use App\Models\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BlueprintController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $blueprints = Blueprint::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->when($request->boolean('has_image'), fn ($q) => $q->withImage())
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return BlueprintResource::collection($blueprints);
    }

    public function show(Blueprint $blueprint): BlueprintResource
    {
        $blueprint->loadMissing(['characters', 'miniatures', 'packages']);

        return new BlueprintResource($blueprint);
    }
}
