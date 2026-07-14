<?php

namespace App\Models\Campaign;

use App\Models\Upgrade;
use Database\Factories\Campaign\CampaignCrewCardAdvancementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A Tier-4 Crew Card advancement (pg 32, 54) — an extra effect borrowed
 * either from the generic pg 15-16 catalog (`CampaignCrewCard`) or from a
 * real, keyword-matched Crew Card Upgrade (`Upgrade::forCrews()`), added to
 * the crew's own crew card. Unlike the starter effect
 * (`CampaignCrew::crew_card_effect_id`, a single FK), these accumulate: a
 * crew can hold its starter effect plus any number of Tier-4 borrows.
 *
 * `crew_card_effect_id`/`crew_card_effect_type` is polymorphic (no DB-level
 * FK) since a single column can't reference both source tables.
 *
 * Deliberately has no "source master" attribution — the real catalog pool is
 * keyword-matched (no single associated master) and the generic pool is
 * always generic, so there's no single truth to attribute a borrow to
 * anymore. Display context (whose crew this belongs to) is derived live from
 * the holding crew's own current Leader instead.
 *
 * @property int $id
 * @property int $campaign_crew_id
 * @property int $crew_card_effect_id
 * @property string $crew_card_effect_type CampaignCrewCard::class or Upgrade::class
 * @property array{type: string, id: int|string, name: string}|null $crew_card_choice
 * @property int|null $acquired_aftermath_id
 * @property-read CampaignCrew $crew
 * @property-read CampaignCrewCard|Upgrade $crewCardEffect
 * @property-read CampaignAftermath|null $sourceAftermath
 *
 * @mixin IdeHelperCampaignCrewCardAdvancement
 */
class CampaignCrewCardAdvancement extends Model
{
    /** @use HasFactory<CampaignCrewCardAdvancementFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'crew_card_choice' => 'array',
        ];
    }

    protected static function newFactory(): CampaignCrewCardAdvancementFactory
    {
        return CampaignCrewCardAdvancementFactory::new();
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(CampaignCrew::class, 'campaign_crew_id');
    }

    public function crewCardEffect(): MorphTo
    {
        return $this->morphTo();
    }

    public function sourceAftermath(): BelongsTo
    {
        return $this->belongsTo(CampaignAftermath::class, 'acquired_aftermath_id');
    }
}
