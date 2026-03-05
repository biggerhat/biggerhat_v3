<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\TriggerResource;
use App\Models\Trigger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TriggerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $triggers = Trigger::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return TriggerResource::collection($triggers);
    }

    public function show(Trigger $trigger): TriggerResource
    {
        $trigger->loadMissing('actions');

        return new TriggerResource($trigger);
    }
}
