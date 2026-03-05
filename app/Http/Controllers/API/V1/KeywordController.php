<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\KeywordResource;
use App\Models\Keyword;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class KeywordController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $keywords = Keyword::query()
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return KeywordResource::collection($keywords);
    }

    public function show(Keyword $keyword): KeywordResource
    {
        $keyword->loadMissing(['characters' => fn ($q) => $q->where('is_hidden', false)]);

        return new KeywordResource($keyword);
    }
}
