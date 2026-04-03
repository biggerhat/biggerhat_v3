<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\BlogPostApiResource;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BlogPostAPIController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = BlogPost::published()
            ->with(['category', 'author', 'characters', 'keywords'])
            ->latest('published_at');

        if ($character = $request->get('character')) {
            $query->whereHas('characters', fn ($q) => $q->where('display_name', 'LIKE', "%{$character}%"));
        }

        if ($keyword = $request->get('keyword')) {
            $query->whereHas('keywords', fn ($q) => $q->where('name', 'LIKE', "%{$keyword}%"));
        }

        return BlogPostApiResource::collection($query->limit(25)->get());
    }
}
