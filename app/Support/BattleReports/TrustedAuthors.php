<?php

namespace App\Support\BattleReports;

use App\Models\BattleReport;
use App\Models\BattleReportDecision;
use App\Models\User;

/**
 * A writer whose battle reports moderators have approved several times, and never turned down (unless the turn-down was reversed on
 * appeal), skips the queue: their reports go public when they ask. Moderators can still take any report down, and a take-down ends the
 * trust. The record is the decisions log, which outlives the reports it is about.
 */
class TrustedAuthors
{
    public const APPROVED_REPORTS_NEEDED = 3;

    public static function trusts(User $author): bool
    {
        $decisions = BattleReportDecision::where('user_id', $author->id);

        if ((clone $decisions)->where('decision', 'rejected')->whereNull('overturned_at')->exists()) {
            return false;
        }

        // Reports a person approved (an automatic approval earns nothing), each counted once however often it was re-read.
        return (clone $decisions)->where('decision', 'approved')->whereNotNull('moderator_id')->whereNotNull('battle_report_id')
            ->distinct()->count('battle_report_id') >= self::APPROVED_REPORTS_NEEDED;
    }

    /**
     * Write a decision down. An approval after an appeal reverses the turn-down it answers.
     */
    public static function record(BattleReport $report, string $decision, ?User $moderator, ?string $note = null): void
    {
        if ($decision === 'approved' && $moderator !== null && $report->appeal_note !== null) {
            BattleReportDecision::where('battle_report_id', $report->id)->where('decision', 'rejected')->whereNull('overturned_at')->update(['overturned_at' => now()]);
        }
        BattleReportDecision::create([
            'user_id' => $report->user_id,
            'battle_report_id' => $report->id,
            'title' => $report->title,
            'decision' => $decision,
            'moderator_id' => $moderator?->id,
            'note' => $note,
        ]);
    }
}
