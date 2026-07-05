<?php

namespace App\Http\Controllers\Database;

use App\Enums\BlogPostStatusEnum;
use App\Http\Controllers\Concerns\BuildsPageMeta;
use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\Request;

/**
 * Site News — the public /news surface. Reuses BlogPost/BlogCategory,
 * scoped to categories flagged is_news, kept intentionally trimmer than
 * BlogController (category-tab filter only, no author/faction/entity
 * filters — see Blog's plan doc for why those weren't ported here).
 */
class NewsController extends Controller
{
    use BuildsPageMeta;

    public function index(Request $request)
    {
        $query = BlogPost::published()
            ->news()
            ->with(['author', 'category'])
            ->orderBy('published_at', 'DESC');

        if ($request->get('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->get('category')));
        }

        return inertia('News/Index', [
            'posts' => $query->paginate(12),
            'categories' => fn () => BlogCategory::news()->orderBy('name')->get(),
            'active_category' => $request->get('category'),
        ]);
    }

    public function view(Request $request, BlogPost $blogPost)
    {
        $blogPost->loadMissing(['author', 'category', 'characters.miniatures', 'keywords', 'upgrades']);

        if ($blogPost->status !== BlogPostStatusEnum::Published || ! $blogPost->category?->is_news) {
            abort(404);
        }

        return inertia('News/View', [
            'post' => $blogPost,
        ])->withViewData([
            'page_meta' => $this->pageMeta(
                title: $blogPost->title,
                description: $blogPost->excerpt ?: $blogPost->plainTextContent(),
                image: $blogPost->featured_image,
                type: 'article',
            ),
        ]);
    }
}
