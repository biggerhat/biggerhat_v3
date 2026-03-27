<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\TriggerResource;
use App\Models\Trigger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Triggers
 */
class TriggerController extends Controller
{
    /**
     * List all triggers
     *
     * Returns a paginated list of triggers, optionally filtered by name.
     *
     * @queryParam search string Filter triggers by name. Example: Critical Strike
     * @queryParam per_page int Number of results per page (max 100). Example: 15
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $triggers = Trigger::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return TriggerResource::collection($triggers);
    }

    /**
     * Get a single trigger
     *
     * Returns a single trigger with its associated actions.
     */
    public function show(Trigger $trigger): TriggerResource
    {
        $trigger->loadMissing('actions');

        return new TriggerResource($trigger);
    }
}
