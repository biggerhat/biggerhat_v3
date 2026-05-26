<?php

namespace App\Models\Campaign;

use Database\Factories\Campaign\EquipmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog entry for an upgrade-shaped equipment piece (pg 22–28, plus the
 * 8 Those Who Thirst entries on pg 29–30 and Omen's Mark). Rows are referenced
 * by `campaign_equipment` (per-crew instances) and surfaced during the Barter
 * phase of the Aftermath wizard.
 */
class Equipment extends Model
{
    /** @use HasFactory<EquipmentFactory> */
    use HasFactory;

    protected $table = 'equipment_catalog';

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'is_always_available' => 'boolean',
            'is_red_joker_entry' => 'boolean',
            'ttw_only' => 'boolean',
            'is_omens_mark' => 'boolean',
            'is_unique' => 'boolean',
            'leader_only' => 'boolean',
            'non_unique_only' => 'boolean',
            'annihilate_after_game' => 'boolean',
            'granted_ability' => 'array',
            'granted_action' => 'array',
        ];
    }

    protected static function newFactory(): EquipmentFactory
    {
        return EquipmentFactory::new();
    }

    /**
     * Barter eligibility — for a flip of value $flip in a Barter phase, an
     * equipment row is eligible iff its `br` is ≤ the flip OR it's always
     * available. Suit-pool filtering by crew keywords is applied separately.
     */
    public function scopeBarterableAt(Builder $query, int $flip): Builder
    {
        return $query->where(function (Builder $q) use ($flip) {
            $q->where('is_always_available', true)
                ->orWhere('br', '<=', $flip);
        })->where('is_red_joker_entry', false)
            ->where('ttw_only', false);
    }
}
