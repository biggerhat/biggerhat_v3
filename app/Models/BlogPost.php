<?php

namespace App\Models;

use App\Enums\BlogPostStatusEnum;
use App\Enums\FactionEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @mixin IdeHelperBlogPost
 */
class BlogPost extends Model
{
    /** @use HasFactory<\Database\Factories\BlogPostFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = ['id'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->slug = Str::slug($model->title);
        });

        static::updating(function (self $model) {
            $model->slug = Str::slug($model->title);
        });
    }

    public function casts(): array
    {
        return [
            'content' => 'array',
            'status' => BlogPostStatusEnum::class,
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', BlogPostStatusEnum::Published->value)
            ->whereNotNull('published_at');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', BlogPostStatusEnum::Draft->value);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function characters(): MorphToMany
    {
        return $this->morphedByMany(Character::class, 'taggable', 'blog_post_taggables');
    }

    public function keywords(): MorphToMany
    {
        return $this->morphedByMany(Keyword::class, 'taggable', 'blog_post_taggables');
    }

    public function upgrades(): MorphToMany
    {
        return $this->morphedByMany(Upgrade::class, 'taggable', 'blog_post_taggables');
    }

    /**
     * @return array<string>
     */
    public function getFactionTagsAttribute(): array
    {
        return DB::table('blog_post_faction')
            ->where('blog_post_id', $this->id)
            ->pluck('faction')
            ->toArray();
    }

    public function syncFactionTags(array $factions): void
    {
        DB::table('blog_post_faction')->where('blog_post_id', $this->id)->delete();

        $rows = collect($factions)->map(fn (string $faction) => [
            'blog_post_id' => $this->id,
            'faction' => $faction,
        ])->toArray();

        if (! empty($rows)) {
            DB::table('blog_post_faction')->insert($rows);
        }
    }

    /**
     * @return array<int, array{entityType: string, entitySlug: string, displayName: string}>
     */
    public function getUnifiedEntities(): array
    {
        $entities = [];

        foreach ($this->characters as $character) {
            $entities[] = ['entityType' => 'character', 'entitySlug' => $character->slug, 'displayName' => $character->display_name];
        }

        foreach ($this->keywords as $keyword) {
            $entities[] = ['entityType' => 'keyword', 'entitySlug' => $keyword->slug, 'displayName' => $keyword->name];
        }

        foreach ($this->upgrades as $upgrade) {
            $entities[] = ['entityType' => 'upgrade', 'entitySlug' => $upgrade->slug, 'displayName' => $upgrade->name];
        }

        foreach ($this->faction_tags as $faction) {
            $factionEnum = FactionEnum::tryFrom($faction);
            $entities[] = ['entityType' => 'faction', 'entitySlug' => $faction, 'displayName' => $factionEnum?->label() ?? $faction];
        }

        return $entities;
    }

    /**
     * @param  array<string>  $entityRefs  Array of "type:slug" strings
     */
    public function syncEntities(array $entityRefs): void
    {
        $grouped = ['character' => [], 'keyword' => [], 'upgrade' => [], 'faction' => []];

        foreach ($entityRefs as $ref) {
            [$type, $slug] = explode(':', $ref, 2);
            if (isset($grouped[$type])) {
                $grouped[$type][] = $slug;
            }
        }

        $characters = Character::whereIn('slug', $grouped['character'])->pluck('id');
        $this->characters()->sync($characters);

        $keywords = Keyword::whereIn('slug', $grouped['keyword'])->pluck('id');
        $this->keywords()->sync($keywords);

        $upgrades = Upgrade::whereIn('slug', $grouped['upgrade'])->pluck('id');
        $this->upgrades()->sync($upgrades);

        $this->syncFactionTags($grouped['faction']);
    }
}
