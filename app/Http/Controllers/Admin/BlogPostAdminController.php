<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BlogPostStatusEnum;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Admin\Concerns\ManagesBlogPostForm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlogPostRequest;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\Request;

class BlogPostAdminController extends Controller
{
    use ManagesBlogPostForm;

    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        $query = BlogPost::excludingNews()
            ->select('id', 'title', 'slug', 'status', 'user_id', 'blog_category_id', 'published_at', 'created_at')
            ->with(['author:id,name', 'category:id,name'])
            ->orderBy('created_at', 'DESC');

        if (! $request->user()->can(PermissionEnum::ManageAllPosts->value)) {
            $query->where('user_id', $request->user()->id);
        }

        return inertia('Admin/Blog/Posts/Index', [
            'posts' => $query->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Blog/Posts/BlogPostForm', $this->getFormData());
    }

    public function edit(Request $request, BlogPost $blogPost)
    {
        $this->authorizePostAccess($blogPost, $request);

        $blogPost->loadMissing(['author', 'category', 'characters', 'keywords', 'upgrades']);
        $postData = $blogPost->toArray();
        $postData['entities'] = $blogPost->getUnifiedEntities();

        return inertia('Admin/Blog/Posts/BlogPostForm', array_merge(
            ['post' => $postData],
            $this->getFormData(),
        ));
    }

    public function preview(Request $request, BlogPost $blogPost)
    {
        $this->authorizePostAccess($blogPost, $request);

        $blogPost->loadMissing(['author', 'category', 'characters', 'keywords', 'upgrades']);

        return inertia('Blog/View', [
            'post' => $blogPost,
            'isPreview' => true,
        ]);
    }

    public function store(BlogPostRequest $request)
    {
        $post = $this->validateAndSave($request);

        return redirect()->route('admin.blog.posts.index')->withMessage("{$post->title} created successfully.");
    }

    public function update(BlogPostRequest $request, BlogPost $blogPost)
    {
        $this->authorizePostAccess($blogPost, $request);

        $post = $this->validateAndSave($request, $blogPost);

        return redirect()->route('admin.blog.posts.index')->withMessage("{$post->title} has been updated.");
    }

    public function delete(Request $request, BlogPost $blogPost)
    {
        $this->authorizePostAccess($blogPost, $request);

        $title = $blogPost->title;
        $blogPost->delete();

        return redirect()->route('admin.blog.posts.index')->withMessage("{$title} has been deleted.");
    }

    /**
     * Blog admin routes are gated on create_posts|edit_posts, which doesn't
     * distinguish "this specific post's category" — so a news post reached
     * by direct URL must still be blocked here even though the outer
     * middleware already let the request through.
     */
    private function authorizePostAccess(BlogPost $blogPost, Request $request): void
    {
        $blogPost->loadMissing('category');
        if ($blogPost->category?->is_news) {
            abort(404);
        }

        if (! $request->user()->can(PermissionEnum::ManageAllPosts->value)
            && $blogPost->user_id !== $request->user()->id) {
            abort(403, 'You can only manage your own posts.');
        }
    }

    private function getFormData(): array
    {
        return [
            'categories' => BlogCategory::excludingNews()->toSelectOptions('name', 'id'),
            'statuses' => BlogPostStatusEnum::toSelectOptions(),
        ];
    }

    protected function canPublish(Request $request): bool
    {
        return $request->user()->can(PermissionEnum::PublishPosts->value);
    }
}
