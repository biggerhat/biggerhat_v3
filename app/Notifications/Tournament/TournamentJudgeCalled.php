<?php

namespace App\Notifications\Tournament;

use App\Models\Tournament;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the organizers and scorekeepers that a player wants someone at their table (a rules question or a dispute).
 */
class TournamentJudgeCalled extends Notification
{
    public function __construct(public Tournament $tournament, public string $playerName, public ?int $table, public int $round) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $where = $this->table ? "table {$this->table}" : 'their table';

        return [
            'type' => 'tournament_judge_called',
            'message' => "{$this->playerName} is asking for a judge at {$where} (round {$this->round}) in \"{$this->tournament->name}\".",
            'action_url' => route('tournaments.score', $this->tournament),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastAs(): string
    {
        return 'tournament.judge.called';
    }
}
