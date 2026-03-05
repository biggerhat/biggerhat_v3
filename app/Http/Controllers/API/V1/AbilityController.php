<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\AbilityResource;
use App\Models\Ability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AbilityController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $abilities = Ability::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return AbilityResource::collection($abilities);
    }

    public function show(Ability $ability): AbilityResource
    {
        return new AbilityResource($ability);
    }
}
