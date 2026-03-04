<?php

namespace App\Http\Controllers\Database;

use App\Enums\BlogPostStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
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

        return inertia('Blog/Index', [
            'posts' => $query->paginate(12),
            'categories' => BlogCategory::orderBy('name')->get(),
            'active_category' => $request->get('category'),
            'active_faction' => $request->get('faction'),
        ]);
    }

    public function view(Request $request, BlogPost $blogPost)
    {
        if ($blogPost->status !== BlogPostStatusEnum::Published) {
            abort(404);
        }

        $blogPost->loadMissing(['author', 'category', 'characters', 'keywords', 'upgrades']);

        return inertia('Blog/View', [
            'post' => $blogPost,
        ]);
    }
}
