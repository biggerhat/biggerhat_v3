<?php

namespace App\Notifications\Tournament;

use App\Models\TournamentPlayer;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an organizer registers an existing user into a tournament
 * (user_id set on the new TournamentPlayer) — mirrors
 * CampaignInvitationReceived's "someone added you to something" shape.
 */
class TournamentPlayerRegistered extends Notification
{
    public function __construct(public TournamentPlayer $player) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $this->player->loadMissing('tournament:id,uuid,name,creator_id', 'tournament.creator:id,name');

        return [
            'type' => 'tournament_player_registered',
            'message' => "You were registered for the tournament \"{$this->player->tournament->name}\".",
            'actor' => ['id' => $this->player->tournament->creator_id, 'name' => $this->player->tournament->creator->name],
            'action_url' => route('tournaments.view', $this->player->tournament),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastAs(): string
    {
        return 'tournament.player.registered';
    }
}
