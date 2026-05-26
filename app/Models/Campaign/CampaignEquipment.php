<?php

namespace App\Models\Campaign;

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
 * @property int $id
 * @property int $campaign_crew_id
 * @property int $equipment_catalog_id
 * @property string $source
 * @property int|null $acquired_aftermath_id
 * @property \Carbon\CarbonImmutable|null $annihilated_at
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
        return $this->belongsTo(Equipment::class, 'equipment_catalog_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('annihilated_at');
    }
}
