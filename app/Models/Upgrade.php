<?php

namespace App\Models;

use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\GameModeTypeEnum;
use App\Enums\UpgradeDomainTypeEnum;
use App\Enums\UpgradeLimitationEnum;
use App\Enums\UpgradeTypeEnum;
use App\Traits\HasGameModeType;
use App\Traits\LogsAdminActivity;
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

    use HasGameModeType;
    use LogsAdminActivity;
    use UsesSelectOptionsScope;
    use UsesSlugName;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'game_mode_type' => GameModeTypeEnum::class,
            'domain' => UpgradeDomainTypeEnum::class,
            'faction' => FactionEnum::class,
            'type' => UpgradeTypeEnum::class,
            'limitations' => UpgradeLimitationEnum::class,
            'hiring_rules' => 'array',
            // Campaign boolean flags. Without these casts they serialize as ints
            // (0/1), and binding :checked="1" (a number) to the admin form's
            // checkbox breaks its toggle/state — so a flag like Traitor never
            // appears saved on reload. Cast so they round-trip as real booleans.
            'campaign_is_always_available' => 'boolean',
            'campaign_ttw_only' => 'boolean',
            'campaign_is_omens_mark' => 'boolean',
            'campaign_is_unique' => 'boolean',
            'campaign_leader_only' => 'boolean',
            'campaign_non_unique_only' => 'boolean',
            'campaign_annihilate_after_game' => 'boolean',
            'campaign_is_red_joker_entry' => 'boolean',
            'campaign_is_traitor' => 'boolean',
            'campaign_is_close_call' => 'boolean',
            'campaign_annihilates_model' => 'boolean',
            'campaign_reflip_if_no_triggers' => 'boolean',
            'campaign_reflip_if_master_or_totem' => 'boolean',
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
        return $this->morphedByMany(Action::class, 'upgradeable')->withPivot(['is_signature_action', 'restriction']);
    }

    public function abilities(): MorphToMany
    {
        return $this->morphedByMany(Ability::class, 'upgradeable')->withPivot('restriction');
    }

    public function triggers(): MorphToMany
    {
        return $this->morphedByMany(Trigger::class, 'upgradeable')->withPivot('restriction');
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

    public function podLinks(): MorphToMany
    {
        return $this->morphToMany(PodLink::class, 'taggable', 'pod_link_taggables');
    }
}
