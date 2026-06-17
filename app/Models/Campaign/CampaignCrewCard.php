<?php

namespace App\Models\Campaign;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A starting Crew Card option for the Starting Arsenal wizard (pg 15).
 * These replace the interim approach of tagging Ability rows with
 * is_crew_card_effect=true — crew cards can have richer structure than
 * a single ability description allows.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $requires_token_choice
 * @property bool $requires_marker_choice
 * @property bool $requires_upgrade_type_choice
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CampaignCrew> $crews
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCard newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCard newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CampaignCrewCard query()
 *
 * @mixin \Eloquent
 */
class CampaignCrewCard extends Model
{
    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'requires_token_choice' => 'boolean',
            'requires_marker_choice' => 'boolean',
            'requires_upgrade_type_choice' => 'boolean',
        ];
    }

    public function crews(): HasMany
    {
        return $this->hasMany(CampaignCrew::class, 'crew_card_effect_id');
    }
}
