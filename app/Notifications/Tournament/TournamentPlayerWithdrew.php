<?php

namespace App\Notifications\Tournament;

use App\Models\Tournament;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the organizers that a player withdrew themselves (so a pairing may need fixing or a waitlisted RSVP a seat).
 */
class TournamentPlayerWithdrew extends Notification
{
    public function __construct(public Tournament $tournament, public string $playerName, public bool $stillPaired) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $note = $this->stillPaired ? ' They are in the round in progress — record a forfeit if they have left.' : '';

        return [
            'type' => 'tournament_player_withdrew',
            'message' => "{$this->playerName} withdrew from \"{$this->tournament->name}\".{$note}",
            'action_url' => route('tournaments.manage', $this->tournament),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastAs(): string
    {
        return 'tournament.player.withdrew';
    }
}
