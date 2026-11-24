<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reader's report of a public battle report ("this should not be public"). One per reader per report. Moderators resolve them
 * by dismissing them or by taking the battle report down.
 */
/**
 * @mixin IdeHelperBattleReportFlag
 */
class BattleReportFlag extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    /** @return BelongsTo<BattleReport, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(BattleReport::class, 'battle_report_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
