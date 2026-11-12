<?php

namespace App\Notifications\Tournament;

use App\Models\Tournament;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a player (or each member of a team) when an organizer gives them a side award.
 */
class TournamentAwardGiven extends Notification
{
    public function __construct(public Tournament $tournament, public string $awardName) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'tournament_award_given',
            'message' => "You won \"{$this->awardName}\" at \"{$this->tournament->name}\".",
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
        return 'tournament.award.given';
    }
}
