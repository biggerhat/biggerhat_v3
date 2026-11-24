<?php

namespace App\Notifications\BattleReport;

use App\Models\BattleReport;
use App\Models\BattleReportComment;
use Illuminate\Notifications\Notification;

/** Sent to a writer when someone comments on their public battle report. Database only. */
class BattleReportCommented extends Notification
{
    public function __construct(public BattleReport $report, public BattleReportComment $comment) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $this->comment->loadMissing('author:id,name');

        return [
            'type' => 'battle_report_commented',
            'message' => "{$this->comment->author->name} commented on your battle report \"{$this->report->title}\".",
            'actor' => ['id' => $this->comment->author->id, 'name' => $this->comment->author->name],
            'action_url' => route('battle-reports.show', $this->report).'#comments',
        ];
    }
}
