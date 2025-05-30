<?php

namespace App\Models;

use App\Enums\BaseSizeEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\SculptVersionEnum;
use App\Enums\SuitEnum;
use App\Observers\CharacterObserver;
use App\Traits\UsesSelectOptionsScope;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

/**
 * @mixin IdeHelperCharacter
 */
#[ObservedBy(CharacterObserver::class)]
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
            'generates_stone' => 'boolean',
            'is_beta' => 'boolean',
            'is_unhirable' => 'boolean',
            'is_hidden' => 'boolean',
        ];
    }

    protected $appends = [
        'faction_color',
    ];

    public function getFactionColorAttribute(): string
    {
        return $this->faction->color();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function miniatures(): HasMany
    {
        return $this->hasMany(Miniature::class, 'character_id', 'id');
    }

    public function standardMiniatures(): HasMany
    {
        return $this->hasMany(Miniature::class, 'character_id', 'id')->whereIn('version', SculptVersionEnum::standardEditions());
    }

    public function promotionalMiniatures(): HasMany
    {
        return $this->hasMany(Miniature::class, 'character_id', 'id')->whereIn('version', SculptVersionEnum::promotionalEditions());
    }

    public function keywords(): MorphToMany
    {
        return $this->morphedByMany(Keyword::class, 'characterable');
    }

    public function actions(): MorphToMany
    {
        return $this->morphedByMany(Action::class, 'characterable')->withPivot('is_signature_action');
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

    public function crewUpgrades(): HasMany
    {
        return $this->hasMany(Upgrade::class, 'master_id', 'id');
    }

    public function totem(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'has_totem_id', 'id');
    }

    public function isTotemFor(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'id', 'has_totem_id');
    }
}
