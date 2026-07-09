<?php

namespace App\Models\Campaign;

use App\Enums\Campaign\AdvancementTableEnum;
use App\Models\Action;
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
 * `applied_skl_from` is set only for Skl Boost advancements — the action's
 * actual Skl at the moment the boost was applied, captured because the
 * catalog row's own skl_from is a qualifying range, not necessarily the
 * action's exact prior value, so removing the advancement needs this to
 * restore it correctly.
 * 
 * An Attack/Tactical Mod advancement (pg 38-43) targets exactly one of three
 * things: the Leader (default — `applied_to_custom_character_id` and
 * `from_equipment_id` both null, `applied_to_action_index` indexes the
 * leader's `actions[]`), the crew's current Totem (`applied_to_custom_character_id`
 * set, `applied_to_action_index` indexes the totem's own `actions[]` —
 * identical shape/mechanism to the leader), or a piece of owned Equipment
 * (`from_equipment_id` set, `applied_to_action_id` is the real `actions.id`
 * the equipment grants — equipment has no per-instance actions[] to index
 * into, so nothing is mutated; this record alone is the source of truth,
 * rendered as an overlay wherever that equipment is displayed). Equipment
 * targeting locks that equipment to the crew going forward (pg 31: "if the
 * action is from a piece of equipment, the leader must always take that
 * equipment if possible going forward").
 *
 * @property int $id
 * @property int $custom_character_id
 * @property int|null $source_aftermath_id
 * @property AdvancementTableEnum $source_table
 * @property int|null $advancement_catalog_id
 * @property int|null $catalog_core_id
 * @property int|null $from_equipment_id
 * @property int $applied_to_action_index
 * @property int|null $applied_to_action_id
 * @property int|null $applied_skl_from
 * @property int|null $applied_to_custom_character_id
 * @property int $position_in_xp_track
 * @property array<string, mixed>|null $free_choice
 * @property \Carbon\CarbonImmutable|null $acquired_at
 * @property-read CustomCharacter $leader
 * @property-read CampaignAftermath|null $sourceAftermath
 * @property-read CustomCharacter|null $appliedToCustomCharacter
 * @property-read CampaignEquipment|null $fromEquipment
 * @property-read Action|null $appliedToAction
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

    /**
     * The real Action row an Equipment-targeted advancement modifies. Null
     * unless `from_equipment_id` is set.
     */
    public function appliedToAction(): BelongsTo
    {
        return $this->belongsTo(Action::class, 'applied_to_action_id');
    }
}
