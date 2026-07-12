<?php

namespace App\Notifications\Game;

use App\Models\Game;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a game's creator when someone joins via the games.join link —
 * GameController::join() only broadcasts GamePlayerJoined to open tabs
 * (->toOthers()), so a creator who isn't watching the page learns nothing.
 */
class GameOpponentJoined extends Notification
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
            'type' => 'game_opponent_joined',
            'message' => "{$this->opponentName} joined your game.",
            'actor' => null,
            'action_url' => route('games.show', $this->game->uuid),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastAs(): string
    {
        return 'game.opponent.joined';
    }
}
