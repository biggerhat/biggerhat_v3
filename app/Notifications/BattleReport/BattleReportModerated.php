<?php

namespace App\Notifications\BattleReport;

use App\Models\BattleReport;
use Illuminate\Notifications\Notification;

/** Sent to a report's writer when a moderator approves it, turns it down, or takes a public one down. */
class BattleReportModerated extends Notification
{
    /** @param 'approved'|'rejected' $outcome */
    public function __construct(public BattleReport $report, public string $outcome) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $message = $this->outcome === 'approved'
            ? "Your battle report \"{$this->report->title}\" is now public."
            : "Your battle report \"{$this->report->title}\" was not approved to be public".($this->report->moderation_note ? ': '.$this->report->moderation_note : '.');

        return [
            'type' => 'battle_report_moderated',
            'message' => $message,
            'actor' => null,
            'action_url' => route('battle-reports.show', $this->report),
        ];
    }
}
