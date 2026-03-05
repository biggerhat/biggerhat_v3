<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\StrategyResource;
use App\Models\Strategy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StrategyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $strategies = Strategy::query()
            ->when($request->query('season'), fn ($q, $season) => $q->where('season', $season))
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return StrategyResource::collection($strategies);
    }

    public function show(Strategy $strategy): StrategyResource
    {
        return new StrategyResource($strategy);
    }
}
