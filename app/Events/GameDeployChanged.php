<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** The deployment moved on (the crew was divided, a group was chosen, someone finished placing): the new state, and where the models stand. */
class GameDeployChanged implements ShouldBroadcastNow
{
    use Concerns\LogsBroadcast;
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $deploy  the deployment summary (nothing hidden: both players and watchers see all of it)
     * @param  array<string, array{x: float, y: float}>  $positions
     */
    public function __construct(
        public Game $game,
        public array $deploy,
        public array $positions = [],
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('game.'.$this->game->uuid),
            ...($this->game->is_observable ? [new Channel('game-observe.'.$this->game->uuid)] : []),
        ];
    }

    public function broadcastAs(): string
    {
        return 'GameDeployChanged';
    }

    public function broadcastWith(): array
    {
        return ['deploy' => $this->deploy, 'positions' => $this->positions];
    }
}
