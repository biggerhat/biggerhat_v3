<?php

namespace App\Models;

use App\Enums\BaseSizeEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\SuitEnum;
use App\Traits\UsesSelectOptionsScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Character extends Model
{
    /** @use HasFactory<\Database\Factories\CharacterFactory> */
    use HasFactory;

    use UsesSelectOptionsScope;

    /**
     * @var array<string>|bool
     */
    protected $guarded = [];

    public function casts(): array
    {
        return [
            'faction' => FactionEnum::class,
            'second_faction' => FactionEnum::class,
            'station' => CharacterStationEnum::class,
            'base' => BaseSizeEnum::class,
            'defense_suit' => SuitEnum::class,
            'willpower_suit' => SuitEnum::class,
        ];
    }

    protected static function bootSlugDisplayName(): void
    {
        static::creating(function (self $model) {
            $model->display_name = $model->name;
            if ($model->title) {
                $model->display_name .= ", {$model->title}";
            }

            $model->slug = Str::slug($model->display_name);
        });

        static::updating(function (self $model) {
            $model->display_name = $model->name;
            if ($model->title) {
                $model->display_name .= ", {$model->title}";
            }

            $model->slug = Str::slug($model->display_name);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function miniatures(): MorphToMany
    {
        return $this->morphedByMany(Miniature::class, 'characterable');
    }

    public function keywords(): MorphToMany
    {
        return $this->morphedByMany(Keyword::class, 'characterable');
    }

    public function actions(): MorphToMany
    {
        return $this->morphedByMany(Action::class, 'characterable');
    }

    public function abilities(): MorphToMany
    {
        return $this->morphedByMany(Ability::class, 'characterable');
    }

    public function characteristics(): MorphToMany
    {
        return $this->morphedByMany(Characteristic::class, 'characterable');
    }

    public function markers(): MorphToMany
    {
        return $this->morphedByMany(Marker::class, 'characterable');
    }

    public function tokens(): MorphToMany
    {
        return $this->morphedByMany(Token::class, 'characterable');
    }

    public function crewUpgrade(): HasOne
    {
        return $this->hasOne(Upgrade::class, 'master_id', 'id');
    }

    public function totem(): HasMany
    {
        return $this->hasMany(Character::class, 'has_totem_id');
    }
}
