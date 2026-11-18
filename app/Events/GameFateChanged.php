<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Something about the Fate deck changed (a draw, a flip, a discard). It carries no cards at all: it only tells the other people at the
 * table to look again, and each of them asks for the state they are allowed to see. A hand can never be in this payload, because the
 * game's channel reaches the opponent (and the observers' channel reaches anyone).
 */
class GameFateChanged implements ShouldBroadcastNow
{
    use Concerns\LogsBroadcast;
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public string $action,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('game.'.$this->game->uuid),
            new Channel('game-observe.'.$this->game->uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'GameFateChanged';
    }

    public function broadcastWith(): array
    {
        return ['action' => $this->action];
    }
}
