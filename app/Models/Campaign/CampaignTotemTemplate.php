<?php

namespace App\Models\Campaign;

use App\Enums\BaseSizeEnum;
use App\Enums\FactionEnum;
use App\Enums\SuitEnum;
use App\Models\Ability;
use App\Models\Action;
use Database\Factories\Campaign\CampaignTotemTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A reusable Totem Template (pg 52) — Spirit Familiar / Sniveling Coward /
 * Mini Master, any Leader's generic pick via the Totem Advancement table.
 *
 * Deliberately ownerless — no `user_id` — unlike the `custom_characters`
 * rows these used to be. It's a shared admin-managed catalog any crew can
 * draw from, same shape as `CampaignCrewCard`. When a crew actually picks
 * one, LeaderAdvancementService::createTotemFromTemplate() clones the
 * relevant fields *by value* into a brand-new `CustomCharacter` row (the
 * crew's real, stateful Totem instance) — this row is never referenced
 * directly by a hired totem afterward, so editing or deleting a template
 * here can never affect an already-hired totem.
 *
 * @property int $id
 * @property string $name
 * @property string|null $title
 * @property FactionEnum|null $faction
 * @property string|null $station
 * @property int|null $cost
 * @property int $health
 * @property int $defense
 * @property SuitEnum|null $defense_suit
 * @property int $willpower
 * @property SuitEnum|null $willpower_suit
 * @property int $speed
 * @property int|null $size
 * @property BaseSizeEnum $base
 * @property string|null $notes
 * @property int|null $campaign_totem_flip_value
 * @property bool $campaign_is_black_joker_totem
 * @property bool $campaign_is_red_joker_totem
 * @property bool $campaign_totem_special_replace
 * @property bool $campaign_is_mini_master
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Action> $campaignTotemActions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Ability> $campaignTotemAbilities
 *
 * @mixin IdeHelperCampaignTotemTemplate
 */
class CampaignTotemTemplate extends Model
{
    /** @use HasFactory<CampaignTotemTemplateFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'faction' => FactionEnum::class,
            'base' => BaseSizeEnum::class,
            'defense_suit' => SuitEnum::class,
            'willpower_suit' => SuitEnum::class,
            'campaign_is_black_joker_totem' => 'boolean',
            'campaign_is_red_joker_totem' => 'boolean',
            'campaign_totem_special_replace' => 'boolean',
            'campaign_is_mini_master' => 'boolean',
        ];
    }

    protected static function newFactory(): CampaignTotemTemplateFactory
    {
        return CampaignTotemTemplateFactory::new();
    }

    /**
     * Actions linked to this template (pg 52). The `is_signature_action`
     * pivot marks signature (f) actions.
     *
     * @return BelongsToMany<Action, $this>
     */
    public function campaignTotemActions(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'campaign_totem_template_actions')
            ->withPivot('is_signature_action');
    }

    /** @return BelongsToMany<Ability, $this> */
    public function campaignTotemAbilities(): BelongsToMany
    {
        return $this->belongsToMany(Ability::class, 'campaign_totem_template_abilities');
    }
}
