<?php

namespace App\Http\Controllers\API\V1\TOS;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\TOS\AllegianceResource;
use App\Models\TOS\Allegiance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags TOS Allegiances
 */
class AllegianceController extends Controller
{
    /**
     * List all TOS Allegiances
     *
     * Returns a paginated list of The Other Side allegiances (Earth/Malifaux factions and syndicates).
     *
     * @queryParam search string Filter allegiances by name. Example: King's Empire
     * @queryParam type string Filter by allegiance type (earth, malifaux). Example: earth
     * @queryParam is_syndicate bool Filter by syndicate flag. Example: false
     * @queryParam per_page int Number of results per page (max 100). Example: 24
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Allegiance::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->when($request->query('type'), function ($q, $type) {
                $enum = AllegianceTypeEnum::tryFrom($type);

                return $q->where('type', $enum !== null ? $enum->value : $type);
            })
            ->when($request->has('is_syndicate'), fn ($q) => $q->where('is_syndicate', $request->boolean('is_syndicate')))
            ->orderBy('is_syndicate')
            ->orderBy('sort_order')
            ->orderBy('name');

        return AllegianceResource::collection(
            $query->paginate(min((int) $request->query('per_page', 24), 100))
        );
    }

    /**
     * Get a single TOS Allegiance
     *
     * Returns a single allegiance by its slug.
     */
    public function show(Allegiance $allegiance): AllegianceResource
    {
        return new AllegianceResource($allegiance);
    }
}
