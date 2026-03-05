<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\SchemeResource;
use App\Models\Scheme;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SchemeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $schemes = Scheme::query()
            ->when($request->query('season'), fn ($q, $season) => $q->where('season', $season))
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return SchemeResource::collection($schemes);
    }

    public function show(Scheme $scheme): SchemeResource
    {
        return new SchemeResource($scheme);
    }
}
