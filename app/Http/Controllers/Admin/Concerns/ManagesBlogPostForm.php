<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Enums\BlogPostStatusEnum;
use App\Http\Requests\Admin\BlogPostRequest;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Shared save/upload logic for BlogPostAdminController and
 * NewsPostAdminController — the only behavioral difference between Blog and
 * News posts is who may publish and which categories are legal to assign,
 * both parameterized via abstract hooks below.
 */
trait ManagesBlogPostForm
{
    abstract protected function canPublish(Request $request): bool;

    private function validateAndSave(BlogPostRequest $request, ?BlogPost $post = null): BlogPost
    {
        $validated = $request->validated();

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
