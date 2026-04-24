<?php

namespace App\Models\TOS;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot for tos_unit_special_rule. Custom because the `parameters` JSON column
 * needs casting on both reads AND writes (`withCasts` on the relation only
 * handles reads, so sync/attach calls would otherwise hit "Array to string
 * conversion" when persisting).
 *
 * @mixin IdeHelperUnitSpecialRulePivot
 */
class UnitSpecialRulePivot extends Pivot
{
    protected $table = 'tos_unit_special_rule';

    public $incrementing = true;

    public function casts(): array
    {
        return [
            'parameters' => 'array',
        ];
    }
}
