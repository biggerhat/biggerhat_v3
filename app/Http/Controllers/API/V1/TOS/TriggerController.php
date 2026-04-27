<?php

namespace App\Http\Controllers\API\V1\TOS;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\TOS\TriggerResource;
use App\Models\TOS\Trigger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags TOS Triggers
 */
class TriggerController extends Controller
{
    /**
     * List all TOS Triggers
     *
     * Returns a paginated list of Triggers. Triggers are shared across multiple Actions
     * via the `tos_action_trigger` pivot.
     *
     * @queryParam search string Filter by name. Example: Critical Strike
     * @queryParam timing string Filter by timing (e.g. before, after, generation). Example: after
     * @queryParam per_page int Number of results per page (max 100). Example: 50
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Trigger::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->when($request->query('timing'), fn ($q, $timing) => $q->where('timing', $timing))
            ->orderBy('name');

        return TriggerResource::collection(
            $query->paginate(min((int) $request->query('per_page', 50), 100))
        );
    }

    /**
     * Get a single TOS Trigger
     */
    public function show(Trigger $trigger): TriggerResource
    {
        return new TriggerResource($trigger);
    }
}
