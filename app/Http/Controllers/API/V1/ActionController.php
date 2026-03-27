<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\ActionResource;
use App\Models\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Actions
 */
class ActionController extends Controller
{
    /**
     * List all actions
     *
     * Returns a paginated list of actions, optionally filtered by name or type.
     *
     * @queryParam search string Filter actions by name. Example: Bite
     * @queryParam type string Filter by action type. Example: melee
     * @queryParam per_page int Number of results per page (max 100). Example: 15
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $actions = Action::query()
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return ActionResource::collection($actions);
    }

    /**
     * Get a single action
     *
     * Returns a single action with its associated triggers.
     */
    public function show(Action $action): ActionResource
    {
        $action->loadMissing('triggers');

        return new ActionResource($action);
    }
}
