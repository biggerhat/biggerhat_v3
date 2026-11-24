<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reader's comment on a public battle report: plain text. The report's writer (and moderators) can remove any comment.
 *
 * @mixin IdeHelperBattleReportComment
 */
class BattleReportComment extends Model
{
    protected $guarded = ['id'];

    /** @return BelongsTo<BattleReport, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(BattleReport::class, 'battle_report_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
