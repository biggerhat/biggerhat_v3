<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** A player changed a shared table setting: which way round the deployment zones sit, or the table's surface. */
class GameBoardSetupChanged implements ShouldBroadcastNow
{
    use Concerns\LogsBroadcast;
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public int $deploymentVariant,
        public ?string $tableImage = null,
    ) {}

    public function broadcastOn(): array
    {
        // people watching an observable game hear the same board changes on its public channel
        return [
            new PresenceChannel('game.'.$this->game->uuid),
            ...($this->game->is_observable ? [new Channel('game-observe.'.$this->game->uuid)] : []),
        ];
    }

    public function broadcastAs(): string
    {
        return 'GameBoardSetupChanged';
    }

    public function broadcastWith(): array
    {
        return ['deployment_variant' => $this->deploymentVariant, 'table_image' => $this->tableImage];
    }
}
