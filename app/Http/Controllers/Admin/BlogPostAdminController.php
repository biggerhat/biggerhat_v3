<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BlogPostStatusEnum;
use App\Enums\FactionEnum;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\Ability;
use App\Models\Action;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Upgrade;
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

        return inertia('Admin/Blog/Posts/BlogPostForm', array_merge(
            ['post' => $blogPost->loadMissing(['author', 'category', 'characters', 'keywords', 'upgrades', 'actions', 'abilities'])->append('faction_tags')],
            $this->getFormData(),
        ));
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
            'factions' => FactionEnum::toSelectOptions(),
            'characters' => Character::toSelectOptions('display_name', 'slug'),
            'keywords' => Keyword::toSelectOptions('name', 'slug'),
            'upgrades' => Upgrade::toSelectOptions('name', 'slug'),
            'actions' => Action::all()->map(function (Action $action) {
                return [
                    'slug' => $action->slug,
                    'name' => sprintf('%s %s', $action->id, $action->name),
                ];
            }),
            'abilities' => Ability::toSelectOptions('name', 'slug'),
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
            'characters' => ['nullable', 'array'],
            'keywords' => ['nullable', 'array'],
            'upgrades' => ['nullable', 'array'],
            'actions' => ['nullable', 'array'],
            'abilities' => ['nullable', 'array'],
            'factions' => ['nullable', 'array'],
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

        // Extract relationship data
        $characterSlugs = $validated['characters'] ?? [];
        $keywordNames = $validated['keywords'] ?? [];
        $upgradeSlugs = $validated['upgrades'] ?? [];
        $actionRefs = $validated['actions'] ?? [];
        $abilityNames = $validated['abilities'] ?? [];
        $factions = $validated['factions'] ?? [];

        unset($validated['characters'], $validated['keywords'], $validated['upgrades'], $validated['actions'], $validated['abilities'], $validated['factions']);

        if (! $post) {
            $validated['user_id'] = $request->user()->id;
            $post = BlogPost::create($validated);
        } else {
            $post->update($validated);
        }

        // Sync taggable relationships
        $characters = Character::whereIn('slug', $characterSlugs)->get();
        $post->characters()->sync($characters->pluck('id'));

        $keywords = Keyword::whereIn('name', $keywordNames)->get();
        $post->keywords()->sync($keywords->pluck('id'));

        $upgrades = Upgrade::whereIn('slug', $upgradeSlugs)->get();
        $post->upgrades()->sync($upgrades->pluck('id'));

        $actionIds = [];
        foreach ($actionRefs as $actionRef) {
            $arrayed = explode(' ', $actionRef);
            $actionIds[] = $arrayed[0];
        }
        $actions = Action::whereIn('id', $actionIds)->get();
        $post->actions()->sync($actions->pluck('id'));

        $abilities = Ability::whereIn('name', $abilityNames)->get();
        $post->abilities()->sync($abilities->pluck('id'));

        // Sync faction tags
        $post->syncFactionTags($factions);

        return $post;
    }
}
