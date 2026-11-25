<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BlogPostStatusEnum;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Admin\Concerns\ManagesBlogPostForm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlogPostRequest;
use App\Http\Requests\Admin\PostNewsToDiscordRequest;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Services\Discord\NewsBroadcast;
use App\Support\AdminAudit;
use Illuminate\Http\Request;

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
            ->select('id', 'title', 'slug', 'status', 'user_id', 'blog_category_id', 'published_at', 'discord_posted_at', 'created_at')
            ->with(['author:id,name', 'category:id,name'])
            ->orderBy('created_at', 'DESC')
            ->get();

        return inertia('Admin/Blog/Posts/Index', [
            'posts' => $posts,
            'postType' => 'news',
            'discord_ready' => app(NewsBroadcast::class)->configured(),
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

    public function store(BlogPostRequest $request)
    {
        $post = $this->validateAndSave($request);

        return redirect()->route('admin.news.posts.index')->withMessage("{$post->title} created successfully.");
    }

    public function update(BlogPostRequest $request, BlogPost $blogPost)
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
    /** Sends a published news post to the site's Discord channel, by hand. */
    public function discord(PostNewsToDiscordRequest $request, BlogPost $blogPost, NewsBroadcast $broadcast)
    {
        $this->authorizePostAccess($blogPost);

        if ($blogPost->status !== BlogPostStatusEnum::Published || $blogPost->published_at === null || $blogPost->published_at->isFuture()) {
            return back()->withMessage('Only a published news post can be posted to Discord.');
        }
        if (! $broadcast->configured()) {
            return back()->withMessage('Discord is not set up for the site yet (DISCORD_NEWS_WEBHOOK).');
        }
        if (! $broadcast->send($blogPost, $request->validated()['message'] ?? null)) {
            return back()->withMessage('Discord did not take the post. Try again in a moment.');
        }

        $blogPost->forceFill(['discord_posted_at' => now()])->save();
        AdminAudit::log('Posted news to Discord: '.$blogPost->title, $blogPost);

        return back()->withMessage('Posted to Discord.');
    }

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
}
