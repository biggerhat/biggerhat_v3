<?php

namespace App\Http\Controllers\API\V1\TOS;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\TOS\AllegianceCardResource;
use App\Models\TOS\AllegianceCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags TOS Allegiance Cards
 */
class AllegianceCardController extends Controller
{
    /**
     * List all TOS Allegiance Cards
     *
     * Returns a paginated list of allegiance cards (the rules card attached to each Allegiance).
     *
     * @queryParam search string Filter by name. Example: King's Empire
     * @queryParam allegiance string Filter by allegiance slug. Example: kings-empire
     * @queryParam type string Filter by allegiance type (earth, malifaux). Example: earth
     * @queryParam per_page int Number of results per page (max 100). Example: 24
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AllegianceCard::query()
            ->with(['allegiance', 'abilities'])
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->when($request->query('allegiance'), fn ($q, $slug) => $q->whereHas('allegiance', fn ($qq) => $qq->where('slug', $slug)))
            ->when($request->query('type'), function ($q, $type) {
                $enum = AllegianceTypeEnum::tryFrom($type);

                return $q->where('type', $enum !== null ? $enum->value : $type);
            })
            ->orderBy('sort_order')
            ->orderBy('name');

        return AllegianceCardResource::collection(
            $query->paginate(min((int) $request->query('per_page', 24), 100))
        );
    }

    /**
     * Get a single TOS Allegiance Card
     *
     * Returns a single allegiance card with its parent allegiance and abilities.
     */
    public function show(AllegianceCard $allegianceCard): AllegianceCardResource
    {
        $allegianceCard->loadMissing(['allegiance', 'abilities']);

        return new AllegianceCardResource($allegianceCard);
    }
}
