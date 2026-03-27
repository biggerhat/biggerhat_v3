<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\CharacteristicResource;
use App\Models\Characteristic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Characteristics
 */
class CharacteristicController extends Controller
{
    /**
     * List all characteristics
     *
     * Returns a paginated list of characteristics (e.g. Versatile, Ruthless).
     *
     * @queryParam search string Filter by name. Example: versatile
     * @queryParam per_page integer Results per page (max 100). Default: 15.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $characteristics = Characteristic::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return CharacteristicResource::collection($characteristics);
    }

    /**
     * Get a single characteristic
     *
     * Returns a characteristic by its slug.
     */
    public function show(Characteristic $characteristic): CharacteristicResource
    {
        return new CharacteristicResource($characteristic);
    }
}
