<?php

namespace App\Models;

use App\Enums\CharacterStationEnum;
use App\Enums\GameModeTypeEnum;
use App\Traits\HasGameModeType;
use App\Traits\UsesCharacters;
use App\Traits\UsesPackages;
use App\Traits\UsesSelectOptionsScope;
use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property-read int|null $owned_characters_count
 *
 * @mixin IdeHelperKeyword
 */
class Keyword extends Model
{
    /** @use HasFactory<\Database\Factories\KeywordFactory> */
    use HasFactory;

    use HasGameModeType;
    use UsesCharacters;
    use UsesPackages;
    use UsesSelectOptionsScope;
    use UsesSlugName;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'game_mode_type' => GameModeTypeEnum::class,
        ];
    }

    public function masters(): MorphToMany
    {
        return $this->characters()->where('station', CharacterStationEnum::Master->value);
    }

    /** @return MorphToMany<BlogPost, $this> */
    public function blogPosts(): MorphToMany
    {
        return $this->morphToMany(BlogPost::class, 'taggable', 'blog_post_taggables');
    }

    public function podLinks(): MorphToMany
    {
        return $this->morphToMany(PodLink::class, 'taggable', 'pod_link_taggables');
    }
}
