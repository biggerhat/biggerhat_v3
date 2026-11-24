<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A reader's "nice report": one per reader per public report.
 *
 * @mixin IdeHelperBattleReportReaction
 */
class BattleReportReaction extends Model
{
    protected $guarded = ['id'];
}
