<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\MiniatureResource;
use App\Models\Miniature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Miniatures
 */
class MiniatureController extends Controller
{
    /**
     * List all miniatures
     *
     * Returns a paginated list of miniature sculpts.
     *
     * @queryParam search string Filter by name. Example: rasputina
     * @queryParam version string Filter by sculpt version (e.g. first_edition, second_edition). Example: second_edition
     * @queryParam character_id integer Filter by character ID.
     * @queryParam per_page integer Results per page (max 100). Default: 15.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $miniatures = Miniature::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('display_name', 'LIKE', "%{$search}%");
            }))
            ->when($request->query('version'), fn ($q, $version) => $q->where('version', $version))
            ->when($request->query('character_id'), fn ($q, $id) => $q->where('character_id', $id))
            ->orderBy('display_name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return MiniatureResource::collection($miniatures);
    }

    /**
     * Get a single miniature
     *
     * Returns a miniature by its ID.
     */
    public function show(Miniature $miniature): MiniatureResource
    {
        return new MiniatureResource($miniature);
    }
}
