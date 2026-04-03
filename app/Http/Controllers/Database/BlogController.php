<?php

namespace App\Http\Controllers\Database;

use App\Enums\BlogPostStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $query = BlogPost::published()
            ->with(['author', 'category'])
            ->orderBy('published_at', 'DESC');

        if ($request->get('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->get('category'));
            });
        }

        if ($request->get('faction')) {
            $faction = $request->get('faction');
            $query->whereExists(function ($q) use ($faction) {
                $q->select(\DB::raw(1))
                    ->from('blog_post_faction')
                    ->whereColumn('blog_post_faction.blog_post_id', 'blog_posts.id')
                    ->where('blog_post_faction.faction', $faction);
            });
        }

        if ($request->get('author')) {
            $query->whereHas('author', fn ($q) => $q->where('name', $request->get('author')));
        }

        if ($request->get('character')) {
            $query->whereHas('characters', fn ($q) => $q->where('slug', $request->get('character')));
        }

        if ($request->get('keyword')) {
            $query->whereHas('keywords', fn ($q) => $q->where('slug', $request->get('keyword')));
        }

        return inertia('Blog/Index', [
            'posts' => $query->paginate(12),
            'categories' => fn () => BlogCategory::orderBy('name')->get(),
            'authors' => fn () => User::whereHas('blogPosts', fn ($q) => $q->where('status', BlogPostStatusEnum::Published->value))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $u) => ['name' => $u->name, 'value' => $u->name]),
            'tagged_characters' => fn () => \App\Models\Character::whereHas('blogPosts')
                ->orderBy('display_name')
                ->get(['id', 'display_name', 'slug'])
                ->map(fn ($c) => ['name' => $c->display_name, 'value' => $c->slug]),
            'tagged_keywords' => fn () => \App\Models\Keyword::whereHas('blogPosts')
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->map(fn ($k) => ['name' => $k->name, 'value' => $k->slug]),
            'active_category' => $request->get('category'),
            'active_faction' => $request->get('faction'),
            'active_author' => $request->get('author'),
            'active_character' => $request->get('character'),
            'active_keyword' => $request->get('keyword'),
        ]);
    }

    public function view(Request $request, BlogPost $blogPost)
    {
        if ($blogPost->status !== BlogPostStatusEnum::Published) {
            abort(404);
        }

        $blogPost->loadMissing(['author', 'category', 'characters.miniatures', 'keywords', 'upgrades']);

        return inertia('Blog/View', [
            'post' => $blogPost,
        ]);
    }
}
