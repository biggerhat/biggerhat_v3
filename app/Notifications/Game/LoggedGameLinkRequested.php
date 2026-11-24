<?php

namespace App\Notifications\Game;

use App\Models\Game;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/** Sent to a player named as the opponent of a game someone logged by hand: they can confirm it was them (so it counts in their stats) or decline. */
class LoggedGameLinkRequested extends Notification
{
    public function __construct(public Game $game) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $this->game->loadMissing('creator:id,name');

        return [
            'type' => 'logged_game_link_requested',
            'message' => "{$this->game->creator->name} logged a game against you. Confirm it to add it to your stats.",
            'actor' => ['id' => $this->game->creator->id, 'name' => $this->game->creator->name],
            'action_url' => route('games.logged.show', $this->game),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastAs(): string
    {
        return 'logged.game.link.requested';
    }
}
