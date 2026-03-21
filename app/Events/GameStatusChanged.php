<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameStatusChanged implements ShouldBroadcastNow
{
    use Concerns\LogsBroadcast;
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
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
        return 'GameStatusChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'status' => $this->game->status->value,
        ];
    }
}
