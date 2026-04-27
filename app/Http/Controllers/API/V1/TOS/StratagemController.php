<?php

namespace App\Http\Controllers\API\V1\TOS;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\TOS\StratagemResource;
use App\Models\TOS\Stratagem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags TOS Stratagems
 */
class StratagemController extends Controller
{
    /**
     * List all TOS Stratagems
     *
     * Returns a paginated list of Stratagems. Filterable by Allegiance or Allegiance Type.
     *
     * @queryParam search string Filter stratagems by name. Example: Forced March
     * @queryParam allegiance string Filter by exact allegiance slug. Example: kings-empire
     * @queryParam allegiance_type string Filter by allegiance type (earth, malifaux). Example: earth
     * @queryParam per_page int Number of results per page (max 100). Example: 24
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Stratagem::query()
            ->with('allegiance')
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->when($request->query('allegiance'), fn ($q, $slug) => $q->whereHas('allegiance', fn ($qq) => $qq->where('slug', $slug)))
            ->when($request->query('allegiance_type'), function ($q, $type) {
                $enum = AllegianceTypeEnum::tryFrom($type);

                return $q->where('allegiance_type', $enum !== null ? $enum->value : $type);
            })
            ->orderBy('tactical_cost')
            ->orderBy('name');

        return StratagemResource::collection(
            $query->paginate(min((int) $request->query('per_page', 24), 100))
        );
    }

    /**
     * Get a single TOS Stratagem
     *
     * Returns a single stratagem with its parent allegiance (if any).
     */
    public function show(Stratagem $stratagem): StratagemResource
    {
        $stratagem->loadMissing('allegiance');

        return new StratagemResource($stratagem);
    }
}
