<?php

namespace App\Notifications\Tournament;

use App\Models\Tournament;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a waitlisted RSVP when a seat frees up (a withdrawal, a removed player or a raised limit) and they move
 * under the player limit.
 */
class TournamentWaitlistPromoted extends Notification
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
            'type' => 'tournament_waitlist_promoted',
            'message' => "A spot opened up in \"{$this->tournament->name}\" — you're off the waitlist.",
            'actor' => null,
            'action_url' => route('tournaments.view', $this->tournament),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastAs(): string
    {
        return 'tournament.waitlist.promoted';
    }
}
