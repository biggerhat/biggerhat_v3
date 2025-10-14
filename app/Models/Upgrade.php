<?php

namespace App\Models;

use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\UpgradeDomainTypeEnum;
use App\Enums\UpgradeLimitationEnum;
use App\Enums\UpgradeTypeEnum;
use App\Traits\UsesSelectOptionsScope;
use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @mixin IdeHelperUpgrade
 */
class Upgrade extends Model
{
    /** @use HasFactory<\Database\Factories\UpgradeFactory> */
    use HasFactory;

    use UsesSelectOptionsScope;
    use UsesSlugName;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'domain' => UpgradeDomainTypeEnum::class,
            'faction' => FactionEnum::class,
            'type' => UpgradeTypeEnum::class,
            'limitations' => UpgradeLimitationEnum::class,
        ];
    }

    public function scopeForCharacters(Builder $query): Builder
    {
        return $query->where('domain', UpgradeDomainTypeEnum::Character->value);
    }

    public function scopeForCrews(Builder $query): Builder
    {
        return $query->where('domain', UpgradeDomainTypeEnum::Crew->value);
    }

    public function markers(): MorphToMany
    {
        return $this->morphedByMany(Marker::class, 'upgradeable');
    }

    public function tokens(): MorphToMany
    {
        return $this->morphedByMany(Token::class, 'upgradeable');
    }

    public function actions(): MorphToMany
    {
        return $this->morphedByMany(Action::class, 'upgradeable')->withPivot('is_signature_action');
    }

    public function abilities(): MorphToMany
    {
        return $this->morphedByMany(Ability::class, 'upgradeable');
    }

    public function triggers(): MorphToMany
    {
        return $this->morphedByMany(Trigger::class, 'upgradeable');
    }

    public function keywords(): MorphToMany
    {
        return $this->morphedByMany(Keyword::class, 'upgradeable');
    }

    public function characters(): MorphToMany
    {
        return $this->morphedByMany(Character::class, 'upgradeable');
    }

    public function masters(): MorphToMany
    {
        return $this->characters()->where('station', CharacterStationEnum::Master->value);
    }
}
