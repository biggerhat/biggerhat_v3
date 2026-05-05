<?php

namespace App\Models\TOS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per unit instance in a Garrison's pool. Mirrors `CompanyUnit` minus
 * the per-unit Asset pivot — Assets in a Garrison live at the Garrison level
 * (a pool with quantity), not attached to a specific unit row.
 *
 * @mixin IdeHelperGarrisonUnit
 */
class GarrisonUnit extends Model
{
    protected $table = 'tos_garrison_units';

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'is_commander' => 'boolean',
        ];
    }

    public function garrison(): BelongsTo
    {
        return $this->belongsTo(Garrison::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function sculpt(): BelongsTo
    {
        return $this->belongsTo(UnitSculpt::class, 'sculpt_id');
    }
}
