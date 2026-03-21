<?php

namespace App\Events;

use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GamePlayerJoined implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    use Concerns\LogsBroadcast;

    public function __construct(
        public Game $game,
        public GamePlayer $player,
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
        return 'GamePlayerJoined';
    }

    public function broadcastWith(): array
    {
        return [
            'player' => [
                'id' => $this->player->id,
                'slot' => $this->player->slot,
                'role' => $this->player->role,
                'user' => [
                    'id' => $this->player->user_id,
                    'name' => $this->player->user->name,
                ],
            ],
            'game_status' => $this->game->fresh()->status->value,
        ];
    }
}
