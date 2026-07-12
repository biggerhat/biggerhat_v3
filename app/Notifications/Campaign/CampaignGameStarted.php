<?php

namespace App\Notifications\Campaign;

use App\Models\Game;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the opponent when a Campaign duel game is created against their
 * crew — CampaignGameController::store() puts the Game straight into
 * MasterSelect (skipping Setup/join), so without this the opponent has no
 * signal a live game is waiting on them.
 */
class CampaignGameStarted extends Notification
{
    public function __construct(public Game $game, public string $opponentName) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'campaign_game_started',
            'message' => "{$this->opponentName} started a campaign game against your crew.",
            'actor' => ['id' => $this->game->creator_id, 'name' => $this->opponentName],
            'action_url' => route('games.show', $this->game->uuid),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastAs(): string
    {
        return 'campaign.game.started';
    }
}
