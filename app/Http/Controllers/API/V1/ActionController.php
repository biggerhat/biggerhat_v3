<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\ActionResource;
use App\Models\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $actions = Action::query()
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return ActionResource::collection($actions);
    }

    public function show(Action $action): ActionResource
    {
        $action->loadMissing('triggers');

        return new ActionResource($action);
    }
}
