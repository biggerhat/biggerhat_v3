<?php

namespace App\Traits\Campaign;

/**
 * Shared shape for the Tier 2 Action / Ability advancement tables (pg
 * 44–51) — grants a brand-new action/ability to the leader. Rows are either
 * bespoke campaign-only entries (stat_block / suits+defensive_ability_type
 * filled directly) or a lookup pointer to a real catalog row that already
 * exists on some model's card. The one "Any Joker" row per chart is a
 * free-choice pick, resolved at apply-time via the Leader Builder's
 * cost-cap search — not a static row.
 *
 * @property int $id
 * @property int|null $flip_value
 * @property bool $is_joker
 * @property bool $is_always_available
 * @property string $talent_name
 * @property string $effect_text
 */
trait HasActionAbilityAdvancementShape
{
    public function casts(): array
    {
        return [
            'is_joker' => 'boolean',
            'is_always_available' => 'boolean',
        ];
    }
}
