<?php

namespace App\Notifications\BattleReport;

use App\Models\BattleReport;
use Illuminate\Notifications\Notification;

/** Sent to the moderators when a reader reports a public battle report. Database only. */
class BattleReportFlagged extends Notification
{
    public function __construct(public BattleReport $report) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'battle_report_flagged',
            'message' => "A reader reported the public battle report \"{$this->report->title}\".",
            'action_url' => route('admin.battle_reports.index', ['status' => 'reported']),
        ];
    }
}
