<?php

namespace App\Http\Controllers\API\V1;

use App\Enums\LoreMediaTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\LoreMediaResource;
use App\Models\LoreMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Lore Media
 */
class LoreMediaController extends Controller
{
    /**
     * List all Lore Media
     *
     * Returns a paginated list of source media (novellas, articles, etc) that lore entries belong to.
     *
     * @queryParam search string Filter by name. Example: Twisted Lives
     * @queryParam type string Filter by lore media type (novella, article, etc). Example: novella
     * @queryParam per_page int Number of results per page (max 100). Example: 30
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = LoreMedia::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->when($request->query('type'), function ($q, $type) {
                $enum = LoreMediaTypeEnum::tryFrom($type);

                return $q->where('type', $enum !== null ? $enum->value : $type);
            })
            ->orderBy('name');

        return LoreMediaResource::collection(
            $query->paginate(min((int) $request->query('per_page', 30), 100))
        );
    }

    /**
     * Get a single Lore Media entry
     */
    public function show(LoreMedia $loreMedium): LoreMediaResource
    {
        return new LoreMediaResource($loreMedium);
    }
}
