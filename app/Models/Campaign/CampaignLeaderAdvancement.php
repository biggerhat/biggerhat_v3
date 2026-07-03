<?php

namespace App\Models\Campaign;

use App\Enums\Campaign\AdvancementTableEnum;
use App\Models\CustomCharacter;
use Database\Factories\Campaign\CampaignLeaderAdvancementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One advancement gained by a Leader (or routed to its Totem). The renderer
 * walks these to compose the final action/ability list and any trigger / Skl
 * modifications applied to the underlying CustomCharacter actions.
 *
 * @property int $id
 * @property int $custom_character_id
 * @property int|null $source_aftermath_id
 * @property AdvancementTableEnum $source_table
 * @property int|null $advancement_catalog_id
 * @property int|null $catalog_core_id
 * @property int|null $from_equipment_id
 * @property int $applied_to_action_index
 * @property int|null $applied_to_custom_character_id
 * @property int $position_in_xp_track
 * @property array<string, mixed>|null $free_choice
 * @property \Carbon\CarbonImmutable|null $acquired_at
 * @property-read CustomCharacter $leader
 * @property-read CampaignAftermath|null $sourceAftermath
 * @property-read CustomCharacter|null $appliedToCustomCharacter
 * @property-read CampaignEquipment|null $fromEquipment
 *
 * @mixin IdeHelperCampaignLeaderAdvancement
 */
class CampaignLeaderAdvancement extends Model
{
    /** @use HasFactory<CampaignLeaderAdvancementFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'source_table' => AdvancementTableEnum::class,
            'free_choice' => 'array',
            'acquired_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): CampaignLeaderAdvancementFactory
    {
        return CampaignLeaderAdvancementFactory::new();
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(CustomCharacter::class, 'custom_character_id');
    }

    public function sourceAftermath(): BelongsTo
    {
        return $this->belongsTo(CampaignAftermath::class, 'source_aftermath_id');
    }

    /**
     * The CustomCharacter this advancement is routed to (defaults to the
     * leader). When set, the advancement was rerouted to a totem unlocked
     * via a Tier-3 Totem advancement (pg 52).
     */
    public function appliedToCustomCharacter(): BelongsTo
    {
        return $this->belongsTo(CustomCharacter::class, 'applied_to_custom_character_id');
    }

    /**
     * The piece of Equipment that supplied the action this advancement
     * modifies (pg 31): "if the action is from a piece of equipment, the
     * leader must always take that equipment if possible going forward".
     * Null for advancements unrelated to equipment.
     */
    public function fromEquipment(): BelongsTo
    {
        return $this->belongsTo(CampaignEquipment::class, 'from_equipment_id');
    }
}
