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
 * `is_black_joker` / `is_red_joker` both true on a row means "Any Joker" —
 * either color qualifies (Attack Mod's Cruel Lessons / Consult the Bones).
 * Exactly one true means that specific card only (Tactical Mod's Illumination
 * of Illios / Darkness of Delios grant different triggers per color).
 *
 * For skl_boost rows, `skl_from`/`skl_from_max` describe the qualifying range
 * of the target action's *current* Skl (e.g. "select one attack with a Skl of
 * 0 or 1"), not a single required value — `skl_from_max` is null when the
 * row only accepts one exact Skl. `skl_to` is always the single resulting
 * value the action's Skl becomes.
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
 * @property int|null $skl_from_max
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
