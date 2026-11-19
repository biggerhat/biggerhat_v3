<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Models were dropped on the tabletop. Participants only: the board is not shown to observers. */
class GameBoardMoved implements ShouldBroadcastNow
{
    use Concerns\LogsBroadcast;
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, array{x: float|int, y: float|int, under?: bool, ignores?: list<string>}>  $positions  Crew member id => inches from the table corner.
     */
    public function __construct(
        public Game $game,
        public array $positions,
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
        return 'GameBoardMoved';
    }

    public function broadcastWith(): array
    {
        return ['positions' => $this->positions];
    }
}
