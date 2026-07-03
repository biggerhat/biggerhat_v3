<?php

namespace App\Traits\Campaign;

use App\Models\Trigger;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared shape for the Tier 1 Attack Mod / Tactical Mod advancement tables
 * (pg 38–43) — flip-gated trigger / skl-boost / signature modification of an
 * existing leader action. Both tables have identical columns; only the
 * physical table differs per model.
 *
 * @property int $id
 * @property int|null $flip_value
 * @property bool $is_black_joker
 * @property bool $is_red_joker
 * @property bool $is_always_available
 * @property string $modifier_type
 * @property string $name
 * @property string $effect_text
 * @property string|null $suit
 * @property int|null $skl_from
 * @property int|null $skl_to
 * @property int|null $trigger_id
 * @property-read Trigger|null $trigger
 */
trait HasAttackTacticalAdvancementShape
{
    public function casts(): array
    {
        return [
            'is_black_joker' => 'boolean',
            'is_red_joker' => 'boolean',
            'is_always_available' => 'boolean',
        ];
    }

    /**
     * The real, already-existing Trigger this row grants — set for
     * modifier_type = 'trigger' rows that reuse a named trigger from
     * elsewhere in the game. Null for bespoke campaign-only rows and for
     * skl_boost / signature rows (which mutate an existing action directly,
     * they don't grant a new trigger).
     */
    public function trigger(): BelongsTo
    {
        return $this->belongsTo(Trigger::class);
    }
}
