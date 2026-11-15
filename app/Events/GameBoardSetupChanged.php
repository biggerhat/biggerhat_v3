<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** A player chose which way round the deployment zones sit. */
class GameBoardSetupChanged implements ShouldBroadcastNow
{
    use Concerns\LogsBroadcast;
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public int $deploymentVariant,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('game.'.$this->game->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'GameBoardSetupChanged';
    }

    public function broadcastWith(): array
    {
        return ['deployment_variant' => $this->deploymentVariant];
    }
}
