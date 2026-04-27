<?php

namespace App\Http\Controllers\API\V1\TOS;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\TOS\ActionResource;
use App\Models\TOS\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags TOS Actions
 */
class ActionController extends Controller
{
    /**
     * List all TOS Actions
     *
     * Returns a paginated list of TOS Actions with their action types and triggers.
     *
     * @queryParam search string Filter by name. Example: Combat
     * @queryParam type string Filter by action type (melee, shooting, tactical, etc). Example: melee
     * @queryParam per_page int Number of results per page (max 100). Example: 50
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Action::query()
            ->with(['typeLinks', 'triggers'])
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->when($request->query('type'), fn ($q, $type) => $q->whereHas(
                'typeLinks',
                fn ($qq) => $qq->where('type', $type)
            ))
            ->orderBy('name');

        return ActionResource::collection(
            $query->paginate(min((int) $request->query('per_page', 50), 100))
        );
    }

    /**
     * Get a single TOS Action
     */
    public function show(Action $action): ActionResource
    {
        $action->loadMissing(['typeLinks', 'triggers']);

        return new ActionResource($action);
    }
}
