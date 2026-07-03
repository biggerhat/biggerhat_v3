<?php

namespace App\Models\Campaign;

use App\Models\Ability;
use App\Traits\Campaign\HasActionAbilityAdvancementShape;
use Database\Factories\Campaign\AdvancementAbilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tier 2 Ability advancement (pg 50–51). Adds a new ability to the leader.
 *
 * A few "always available" rows are unconditionally selectable. The one
 * "Any Joker" row mirrors the Action table's free-pick mechanic.
 *
 * @property string|null $suits
 * @property string|null $defensive_ability_type
 * @property int|null $ability_id
 * @property-read Ability|null $ability
 *
 * @mixin IdeHelperAdvancementAbility
 */
class AdvancementAbility extends Model
{
    use HasActionAbilityAdvancementShape;

    /** @use HasFactory<AdvancementAbilityFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected static function newFactory(): AdvancementAbilityFactory
    {
        return AdvancementAbilityFactory::new();
    }

    /**
     * The real, already-existing Ability this row grants — set for rows
     * that reuse a named ability from elsewhere in the game. Null for
     * bespoke campaign-only rows (whose text lives in suits/
     * defensive_ability_type) and for the Any Joker row.
     */
    public function ability(): BelongsTo
    {
        return $this->belongsTo(Ability::class);
    }
}
