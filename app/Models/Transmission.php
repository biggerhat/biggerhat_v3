<?php

namespace App\Models;

use App\Enums\ContentTypeEnum;
use App\Enums\TransmissionTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Transmission extends Model
{
    use HasFactory;

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
            'factions' => 'array',
            'transmission_type' => TransmissionTypeEnum::class,
            'content_type' => ContentTypeEnum::class,
            'release_date' => 'date',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** @return MorphToMany<Character, $this> */
    public function characters(): MorphToMany
    {
        return $this->morphedByMany(Character::class, 'taggable', 'transmission_taggables');
    }

    /** @return MorphToMany<Keyword, $this> */
    public function keywords(): MorphToMany
    {
        return $this->morphedByMany(Keyword::class, 'taggable', 'transmission_taggables');
    }
}
