<?php

namespace App\Http\Controllers;

use App\Http\Requests\MetaStoreRequest;
use App\Models\Meta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lookup + create endpoints for player Metas.
 *
 * The metas list is intentionally self-curating: any logged-in user can
 * type a new meta name when they don't see theirs in the list, and we
 * find-or-create on save. This avoids needing a separate admin curation
 * UI for what's essentially community-supplied tag data.
 */
class MetaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $query = Meta::query()->orderBy('name');
        if ($q !== '') {
            $escaped = addcslashes($q, '\\%_');
            $query->where('name', 'like', $escaped.'%');
        }

        return response()->json([
            'metas' => $query->limit(200)->get(['id', 'name', 'slug']),
        ]);
    }

    public function store(MetaStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $meta = Meta::findOrCreateByName($validated['name']);

        return response()->json([
            'meta' => $meta->only(['id', 'name', 'slug']),
        ]);
    }
}
