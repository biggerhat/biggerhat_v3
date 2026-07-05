<?php

namespace App\Models;

use App\Traits\UsesSelectOptionsScope;
use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperBlogCategory
 */
class BlogCategory extends Model
{
    /** @use HasFactory<\Database\Factories\BlogCategoryFactory> */
    use HasFactory;

    use UsesSelectOptionsScope;
    use UsesSlugName;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'is_news' => 'boolean',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }

    /** Site News (pg N/A): categories pooled onto /news, hidden from /blog. */
    public function scopeNews(Builder $query): Builder
    {
        return $query->where('is_news', true);
    }

    /** Regular Blog/Article categories — everything not flagged as news. */
    public function scopeExcludingNews(Builder $query): Builder
    {
        return $query->where('is_news', false);
    }
}
