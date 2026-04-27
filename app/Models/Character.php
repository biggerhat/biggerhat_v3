<?php

namespace App\Models;

use App\Enums\BaseSizeEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\GameModeTypeEnum;
use App\Enums\SculptVersionEnum;
use App\Enums\SuitEnum;
use App\Observers\CharacterObserver;
use App\Traits\HasGameModeType;
use App\Traits\LogsAdminActivity;
use App\Traits\UsesPackages;
use App\Traits\UsesSelectOptionsScope;
use App\Traits\UsesUpgrades;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @mixin IdeHelperCharacter
 */
#[ObservedBy(CharacterObserver::class)]
class Character extends Model
{
    /** @use HasFactory<\Database\Factories\CharacterFactory> */
    use HasFactory;

    use HasGameModeType;
    use LogsAdminActivity;
    use UsesPackages;
    use UsesSelectOptionsScope;
    use UsesUpgrades;

    /**
     * @var array<string>|bool
     */
    protected $guarded = [];

    public function casts(): array
    {
        return [
            'game_mode_type' => GameModeTypeEnum::class,
            'faction' => FactionEnum::class,
            'second_faction' => FactionEnum::class,
            'station' => CharacterStationEnum::class,
            'base' => BaseSizeEnum::class,
            'defense_suit' => SuitEnum::class,
            'willpower_suit' => SuitEnum::class,
            'generates_stone' => 'boolean',
            'is_beta' => 'boolean',
            'is_unhirable' => 'boolean',
            'crew_upgrade_mode' => \App\Enums\CrewUpgradeModeEnum::class,
            'is_hidden' => 'boolean',
        ];
    }

    protected $appends = [
        'faction_color',
    ];

    public function scopeForStation(Builder $query, CharacterStationEnum $station): Builder
    {
        return $query->where('station', $station->value);
    }

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

    /** @return MorphToMany<Blueprint, $this> */
    public function blueprints(): MorphToMany
    {
        return $this->morphedByMany(Blueprint::class, 'characterable');
    }

    public function totem(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'has_totem_id', 'id');
    }

    public function isTotemFor(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'id', 'has_totem_id');
    }

    /** @return BelongsToMany<Lore, $this> */
    public function lores(): BelongsToMany
    {
        return $this->belongsToMany(Lore::class, 'character_lore');
    }

    /** @return MorphToMany<Transmission, $this> */
    public function transmissions(): MorphToMany
    {
        return $this->morphToMany(Transmission::class, 'taggable', 'transmission_taggables');
    }

    /** @return MorphToMany<BlogPost, $this> */
    public function blogPosts(): MorphToMany
    {
        return $this->morphToMany(BlogPost::class, 'taggable', 'blog_post_taggables');
    }

    /** Characters this model summons. */
    public function summons(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'character_links', 'character_id', 'linked_character_id')
            ->wherePivot('type', 'summons')
            ->withPivot('type', 'count')
            ->withTimestamps();
    }

    /** Characters that summon this model. */
    public function summonedBy(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'character_links', 'linked_character_id', 'character_id')
            ->wherePivot('type', 'summons')
            ->withPivot('type', 'count')
            ->withTimestamps();
    }

    /** Characters this model replaces into (general). */
    public function replacesInto(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'character_links', 'character_id', 'linked_character_id')
            ->wherePivot('type', 'replaces_into')
            ->withPivot('type', 'count')
            ->withTimestamps();
    }

    /** Characters that replace into this model. */
    public function replacedBy(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'character_links', 'linked_character_id', 'character_id')
            ->wherePivot('type', 'replaces_into')
            ->withPivot('type', 'count')
            ->withTimestamps();
    }

    /** Characters this model replaces into when killed/dying. */
    public function replacesOnDeath(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'character_links', 'character_id', 'linked_character_id')
            ->wherePivot('type', 'replaces_on_death')
            ->withPivot('type', 'count', 'health')
            ->withTimestamps();
    }
}
