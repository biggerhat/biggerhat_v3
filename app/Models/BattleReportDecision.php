<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One moderator decision on a battle report (approved, or turned down / taken down). Outlives the report, so deleting a
 * turned-down report does not wipe the record.
 *
 * @mixin IdeHelperBattleReportDecision
 */
class BattleReportDecision extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['overturned_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}
