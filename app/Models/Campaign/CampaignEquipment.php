<?php

namespace App\Models\Campaign;

use App\Models\Upgrade;
use Database\Factories\Campaign\CampaignEquipmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per per-crew equipment instance — multiple copies of the same
 * underlying Equipment catalog row stack as separate rows. Annihilated
 * instances stay around for history; `active()` scope filters them out.
 *
 * Post-Catalog-Consolidation: `equipment_upgrade_id` points at an upgrades
 * row with `game_mode_type=campaign` + `campaign_upgrade_kind=equipment`.
 *
 * @property int $id
 * @property int $campaign_crew_id
 * @property int|null $equipment_upgrade_id
 * @property string $source
 * @property int|null $acquired_aftermath_id
 * @property \Carbon\CarbonImmutable|null $annihilated_at
 * @property-read Upgrade|null $catalog
 * @property-read \App\Models\Campaign\CampaignCrew|null $crew
 *
 * @method static Builder<static>|CampaignEquipment active()
 * @method static \Database\Factories\Campaign\CampaignEquipmentFactory factory($count = null, $state = [])
 * @method static Builder<static>|CampaignEquipment newModelQuery()
 * @method static Builder<static>|CampaignEquipment newQuery()
 * @method static Builder<static>|CampaignEquipment query()
 *
 * @mixin \Eloquent
 */
class CampaignEquipment extends Model
{
    /** @use HasFactory<CampaignEquipmentFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'annihilated_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): CampaignEquipmentFactory
    {
        return CampaignEquipmentFactory::new();
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(CampaignCrew::class, 'campaign_crew_id');
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Upgrade::class, 'equipment_upgrade_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('annihilated_at');
    }
}
