<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\TokenResource;
use App\Models\Token;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Tokens
 */
class TokenController extends Controller
{
    /**
     * List all tokens
     *
     * Returns a paginated list of tokens, optionally filtered by name.
     *
     * @queryParam search string Filter tokens by name. Example: Focus
     * @queryParam per_page int Number of results per page (max 100). Example: 15
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $tokens = Token::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return TokenResource::collection($tokens);
    }

    /**
     * Get a single token
     *
     * Returns a single token by its ID.
     */
    public function show(Token $token): TokenResource
    {
        return new TokenResource($token);
    }
}
