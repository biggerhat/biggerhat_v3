<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\LoreResource;
use App\Models\Lore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Lore
 */
class LoreController extends Controller
{
    /**
     * List all Lore entries
     *
     * Returns a paginated list of Malifaux lore entries (novellas, fiction, articles) and the
     * characters/media associated with them.
     *
     * @queryParam search string Filter by lore entry name. Example: Crossroads
     * @queryParam media_type string Filter to entries that include a media item of this type (e.g. novella, article). Example: novella
     * @queryParam lore_media int Filter by exact LoreMedia id. Example: 12
     * @queryParam character string Filter to entries linked to a specific character (by slug). Example: rasputina
     * @queryParam per_page int Number of results per page (max 100). Example: 30
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Lore::query()
            ->with(['media', 'characters'])
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->when($request->query('media_type'), fn ($q, $type) => $q->whereHas('media', fn ($qq) => $qq->where('type', $type)))
            ->when($request->query('lore_media'), fn ($q, $id) => $q->whereHas('media', fn ($qq) => $qq->where('lore_media.id', $id)))
            ->when($request->query('character'), fn ($q, $slug) => $q->whereHas('characters', fn ($qq) => $qq->where('characters.slug', $slug)))
            ->orderBy('name');

        return LoreResource::collection(
            $query->paginate(min((int) $request->query('per_page', 30), 100))
        );
    }

    /**
     * Get a single Lore entry
     *
     * Returns one lore entry with its media and linked characters.
     */
    public function show(Lore $lore): LoreResource
    {
        $lore->loadMissing(['media', 'characters']);

        return new LoreResource($lore);
    }
}
