<?php

namespace App\Notifications\BattleReport;

use App\Models\BattleReport;
use Illuminate\Notifications\Notification;

/**
 * Sent to the moderators (users with approve_battle_reports, and super admins) when a writer asks for a battle report to be public.
 * Database only: a moderator finds it in the bell and in the Battle Reports queue.
 */
class BattleReportApprovalRequested extends Notification
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
        $this->report->loadMissing('author:id,name');

        return [
            'type' => 'battle_report_approval_requested',
            'message' => "{$this->report->author->name} asked to publish the battle report \"{$this->report->title}\".",
            'actor' => ['id' => $this->report->author->id, 'name' => $this->report->author->name],
            'action_url' => route('admin.battle_reports.index'),
        ];
    }
}
