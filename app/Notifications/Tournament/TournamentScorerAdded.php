<?php

namespace App\Notifications\Tournament;

use App\Models\Tournament;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an organizer makes someone a scorekeeper; the link opens the scoring desk.
 */
class TournamentScorerAdded extends Notification
{
    public function __construct(public Tournament $tournament) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'tournament_scorer_added',
            'message' => "You can enter results for \"{$this->tournament->name}\" from its scoring desk.",
            'actor' => null,
            'action_url' => route('tournaments.score', $this->tournament),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastAs(): string
    {
        return 'tournament.scorer.added';
    }
}
