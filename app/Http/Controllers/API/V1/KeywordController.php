<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\KeywordResource;
use App\Models\Keyword;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Keywords
 */
class KeywordController extends Controller
{
    /**
     * List all keywords
     *
     * Returns a paginated list of keywords, optionally filtered by name.
     *
     * @queryParam search string Filter keywords by name. Example: December
     * @queryParam per_page int Number of results per page (max 100). Example: 15
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $keywords = Keyword::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return KeywordResource::collection($keywords);
    }

    /**
     * Get a single keyword
     *
     * Returns a single keyword with its associated visible characters.
     */
    public function show(Keyword $keyword): KeywordResource
    {
        $keyword->loadMissing(['characters' => fn ($q) => $q->where('is_hidden', false)]);

        return new KeywordResource($keyword);
    }
}
