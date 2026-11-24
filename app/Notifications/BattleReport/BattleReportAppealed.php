<?php

namespace App\Notifications\BattleReport;

use App\Models\BattleReport;
use Illuminate\Notifications\Notification;

/** Sent to the moderators when a writer asks them to look again at a report that was turned down. Database only. */
class BattleReportAppealed extends Notification
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
            'type' => 'battle_report_appealed',
            'message' => "{$this->report->author->name} asked for a second look at the battle report \"{$this->report->title}\".",
            'actor' => ['id' => $this->report->author->id, 'name' => $this->report->author->name],
            'action_url' => route('admin.battle_reports.index'),
        ];
    }
}
