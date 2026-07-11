<?php

namespace App\Models\Campaign;

use App\Models\Ability;
use App\Models\Action;
use App\Models\Character;
use App\Models\CustomCharacter;
use Database\Factories\Campaign\CampaignCrewCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A starting Crew Card option for the Starting Arsenal wizard (pg 15).
 *
 * These replace the interim approach of tagging Ability rows with
 * is_crew_card_effect=true — crew cards can have richer structure than
 * a single ability description allows.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $master_id the master this card is actually printed on (nullable — some rows are generic/unassigned)
 * @property string|null $master_type Character::class or CustomCharacter::class — a Crew Card can be printed on a custom-built Campaign Leader, not just an official master
 * @property bool $requires_token_choice
 * @property bool $requires_marker_choice
 * @property bool $requires_upgrade_type_choice
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Action> $actions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Ability> $abilities
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CampaignCrew> $crews
 * @property-read Character|CustomCharacter|null $master
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCard newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCard newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCard query()
 *
 * @mixin \Eloquent
 * @mixin IdeHelperCampaignCrewCard
 */
class CampaignCrewCard extends Model
{
    /** @use HasFactory<CampaignCrewCardFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'requires_token_choice' => 'boolean',
            'requires_marker_choice' => 'boolean',
            'requires_upgrade_type_choice' => 'boolean',
        ];
    }

    protected static function newFactory(): CampaignCrewCardFactory
    {
        return CampaignCrewCardFactory::new();
    }

    /** @return BelongsToMany<Action, $this> */
    public function actions(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'campaign_crew_card_actions')
            ->withPivot('is_signature_action');
    }

    /** @return BelongsToMany<Ability, $this> */
    public function abilities(): BelongsToMany
    {
        return $this->belongsToMany(Ability::class, 'campaign_crew_card_abilities');
    }

    public function crews(): HasMany
    {
        return $this->hasMany(CampaignCrew::class, 'crew_card_effect_id');
    }

    /**
     * The master this card is actually printed on — either an official
     * Character or a custom-built Campaign Leader (CustomCharacter).
     */
    public function master(): MorphTo
    {
        return $this->morphTo();
    }
}
