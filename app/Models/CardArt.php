<?php

namespace App\Models;

use App\Enums\FactionEnum;
use Database\Factories\CardArtFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One piece of admin-curated character art that Custom Card Creator characters can pick.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property FactionEnum|null $faction
 * @property string|null $category
 * @property string $image_path path on the public disk (e.g. card_arts/foo_uuid.webp)
 * @property string|null $credit
 * @property bool $is_active
 * @property int $sort_order
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CustomCharacter> $customCharacters
 */
class CardArt extends Model
{
    /** @use HasFactory<CardArtFactory> */
    use HasFactory;

    protected $table = 'card_arts';

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'faction' => FactionEnum::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<CardArt>  $query
     * @return Builder<CardArt>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @return HasMany<CustomCharacter, $this> */
    public function customCharacters(): HasMany
    {
        return $this->hasMany(CustomCharacter::class, 'card_art_id');
    }
}
