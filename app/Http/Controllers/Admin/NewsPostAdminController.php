<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BlogPostStatusEnum;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Admin\Concerns\ManagesBlogPostForm;
use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Site News admin — reuses the Blog post editor/admin infrastructure
 * (BlogPost/BlogCategory models, BlogPostForm.vue) scoped to categories
 * flagged is_news. Gated entirely on the single bundled `manage_news`
 * permission (no per-user ownership split like Blog's manage_all_posts).
 */
class NewsPostAdminController extends Controller
{
    use ManagesBlogPostForm;

    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        $posts = BlogPost::news()
            ->select('id', 'title', 'slug', 'status', 'user_id', 'blog_category_id', 'published_at', 'created_at')
            ->with(['author:id,name', 'category:id,name'])
            ->orderBy('created_at', 'DESC')
            ->get();

        return inertia('Admin/Blog/Posts/Index', [
            'posts' => $posts,
            'postType' => 'news',
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Blog/Posts/BlogPostForm', array_merge($this->getFormData(), ['postType' => 'news']));
    }

    public function edit(Request $request, BlogPost $blogPost)
    {
        $this->authorizePostAccess($blogPost);

        $blogPost->loadMissing(['author', 'category', 'characters', 'keywords', 'upgrades']);
        $postData = $blogPost->toArray();
        $postData['entities'] = $blogPost->getUnifiedEntities();

        return inertia('Admin/Blog/Posts/BlogPostForm', array_merge(
            ['post' => $postData, 'postType' => 'news'],
            $this->getFormData(),
        ));
    }

    public function preview(Request $request, BlogPost $blogPost)
    {
        $this->authorizePostAccess($blogPost);

        $blogPost->loadMissing(['author', 'category', 'characters', 'keywords', 'upgrades']);

        return inertia('News/View', [
            'post' => $blogPost,
            'isPreview' => true,
        ]);
    }

    public function store(Request $request)
    {
        $post = $this->validateAndSave($request);

        return redirect()->route('admin.news.posts.index')->withMessage("{$post->title} created successfully.");
    }

    public function update(Request $request, BlogPost $blogPost)
    {
        $this->authorizePostAccess($blogPost);

        $post = $this->validateAndSave($request, $blogPost);

        return redirect()->route('admin.news.posts.index')->withMessage("{$post->title} has been updated.");
    }

    public function delete(Request $request, BlogPost $blogPost)
    {
        $this->authorizePostAccess($blogPost);

        $title = $blogPost->title;
        $blogPost->delete();

        return redirect()->route('admin.news.posts.index')->withMessage("{$title} has been deleted.");
    }

    /**
     * manage_news is bundled/all-or-nothing (no ownership split), so the
     * only thing to guard against is a Blog-only post reached via a News
     * admin URL.
     */
    private function authorizePostAccess(BlogPost $blogPost): void
    {
        $blogPost->loadMissing('category');
        if (! $blogPost->category?->is_news) {
            abort(404);
        }
    }

    private function getFormData(): array
    {
        return [
            'categories' => BlogCategory::news()->toSelectOptions('name', 'id'),
            'statuses' => BlogPostStatusEnum::toSelectOptions(),
        ];
    }

    protected function canPublish(Request $request): bool
    {
        return $request->user()->can(PermissionEnum::ManageNews->value);
    }

    protected function categoryIdExistsRule(): Exists
    {
        return Rule::exists('blog_categories', 'id')->where('is_news', true);
    }
}
