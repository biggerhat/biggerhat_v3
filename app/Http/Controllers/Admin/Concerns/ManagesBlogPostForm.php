<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Enums\BlogPostStatusEnum;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Shared save/upload logic for BlogPostAdminController and
 * NewsPostAdminController — the only behavioral difference between Blog and
 * News posts is who may publish and which categories are legal to assign,
 * both parameterized via abstract hooks below.
 */
trait ManagesBlogPostForm
{
    abstract protected function canPublish(Request $request): bool;

    /** Restricts blog_category_id to the categories legal for this post type. */
    abstract protected function categoryIdExistsRule(): Exists;

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
            'blog_category_id' => ['nullable', 'integer', $this->categoryIdExistsRule()],
            'entities' => ['nullable', 'array'],
        ]);

        // Enforce publish permission
        if ($validated['status'] === BlogPostStatusEnum::Published->value && ! $this->canPublish($request)) {
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

    public function uploadImage(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ]);

        $path = $request->file('image')->store('blog/content', 'public');

        return response()->json([
            'url' => '/storage/'.$path,
        ]);
    }
}
