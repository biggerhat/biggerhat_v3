<?php

namespace App\Models\Campaign;

use App\Models\Character;
use Database\Factories\Campaign\CampaignCrewCardAdvancementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Tier-4 Crew Card advancement (pg 32, 54) — an extra effect borrowed from
 * a crew card associated with a master sharing one of this crew's keywords,
 * added to the crew's own crew card. Unlike the starter effect
 * (`CampaignCrew::crew_card_effect_id`, a single FK), these accumulate: a
 * crew can hold its starter effect plus any number of Tier-4 borrows.
 *
 * @property int $id
 * @property int $campaign_crew_id
 * @property int $crew_card_effect_id
 * @property int|null $source_master_id
 * @property int|null $acquired_aftermath_id
 * @property-read CampaignCrew $crew
 * @property-read CampaignCrewCard $crewCardEffect
 * @property-read Character|null $sourceMaster
 * @property-read CampaignAftermath|null $sourceAftermath
 *
 * @mixin IdeHelperCampaignCrewCardAdvancement
 */
class CampaignCrewCardAdvancement extends Model
{
    /** @use HasFactory<CampaignCrewCardAdvancementFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected static function newFactory(): CampaignCrewCardAdvancementFactory
    {
        return CampaignCrewCardAdvancementFactory::new();
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(CampaignCrew::class, 'campaign_crew_id');
    }

    public function crewCardEffect(): BelongsTo
    {
        return $this->belongsTo(CampaignCrewCard::class, 'crew_card_effect_id');
    }

    public function sourceMaster(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'source_master_id');
    }

    public function sourceAftermath(): BelongsTo
    {
        return $this->belongsTo(CampaignAftermath::class, 'acquired_aftermath_id');
    }
}
