<?php

namespace App\Http\Resources\API;

use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BlogPost */
class BlogPostApiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'published_at' => $this->published_at?->toIso8601String(),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ]),
            'characters' => $this->whenLoaded('characters', fn () => $this->characters->map(fn ($c) => [
                'id' => $c->id,
                'display_name' => $c->display_name,
                'slug' => $c->slug,
            ])),
            'keywords' => $this->whenLoaded('keywords', fn () => $this->keywords->map(fn ($k) => [
                'id' => $k->id,
                'name' => $k->name,
                'slug' => $k->slug,
            ])),
            'url' => route('blog.view', ['blogPost' => $this->slug]),
        ];
    }
}
