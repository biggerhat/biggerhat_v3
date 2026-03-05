<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BlogPostStatusEnum;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BlogPostAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        $query = BlogPost::with(['author', 'category'])
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

    public function store(Request $request)
    {
        $post = $this->validateAndSave($request);

        return redirect()->route('admin.blog.posts.index')->withMessage("{$post->title} created successfully.");
    }

    public function update(Request $request, BlogPost $blogPost)
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

    private function authorizePostAccess(BlogPost $blogPost, Request $request): void
    {
        if (! $request->user()->can(PermissionEnum::ManageAllPosts->value)
            && $blogPost->user_id !== $request->user()->id) {
            abort(403, 'You can only manage your own posts.');
        }
    }

    private function getFormData(): array
    {
        return [
            'categories' => BlogCategory::toSelectOptions('name', 'id'),
            'statuses' => BlogPostStatusEnum::toSelectOptions(),
        ];
    }

    private function validateAndSave(Request $request, ?BlogPost $post = null): BlogPost
    {
        // Content arrives as a JSON string via FormData — decode it before validation
        if ($request->has('content') && is_string($request->input('content'))) {
            $request->merge(['content' => json_decode($request->input('content'), true)]);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'array'],
            'excerpt' => ['nullable', 'string'],
            'featured_image' => ['nullable', 'image', 'max:4096'],
            'status' => ['required', 'string', Rule::enum(BlogPostStatusEnum::class)],
            'blog_category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'entities' => ['nullable', 'array'],
        ]);

        // Enforce publish permission
        if ($validated['status'] === BlogPostStatusEnum::Published->value && ! $request->user()->can(PermissionEnum::PublishPosts->value)) {
            $validated['status'] = BlogPostStatusEnum::Draft->value;
        }

        // Handle featured image upload
        if ($request->hasFile('featured_image')) {
            if ($post && $post->featured_image) {
                Storage::disk('public')->delete($post->featured_image);
            }
            $validated['featured_image'] = $request->file('featured_image')->store('blog', 'public');
        } else {
            unset($validated['featured_image']);
        }

        // Set published_at on first publish
        if ($validated['status'] === BlogPostStatusEnum::Published->value && (! $post || ! $post->published_at)) {
            $validated['published_at'] = now();
        }

        // Extract entity refs
        $entityRefs = $validated['entities'] ?? [];
        unset($validated['entities']);

        if (! $post) {
            $validated['user_id'] = $request->user()->id;
            $post = BlogPost::create($validated);
        } else {
            $post->update($validated);
        }

        // Sync all entity relationships
        $post->syncEntities($entityRefs);

        return $post;
    }
}
