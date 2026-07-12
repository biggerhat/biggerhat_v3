<?php

namespace App\Notifications\Tournament;

use App\Models\Tournament;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a tournament creator grants someone organizer access —
 * TournamentOrganizerController::store() previously granted real
 * co-management permission with zero notice to the grantee.
 */
class TournamentOrganizerAdded extends Notification
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
        $this->tournament->loadMissing('creator:id,name');

        return [
            'type' => 'tournament_organizer_added',
            'message' => "You were made an organizer of \"{$this->tournament->name}\".",
            'actor' => ['id' => $this->tournament->creator_id, 'name' => $this->tournament->creator->name],
            'action_url' => route('tournaments.view', $this->tournament),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastAs(): string
    {
        return 'tournament.organizer.added';
    }
}
