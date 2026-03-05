<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\TokenResource;
use App\Models\Token;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TokenController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tokens = Token::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return TokenResource::collection($tokens);
    }

    public function show(Token $token): TokenResource
    {
        return new TokenResource($token);
    }
}
