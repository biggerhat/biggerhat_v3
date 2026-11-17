<?php

namespace App\Events;

use App\Models\Game;
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
     * @param  array<string, array{x: float|int, y: float|int, under?: bool}>  $positions  Crew member id => inches from the table corner.
     */
    public function __construct(
        public Game $game,
        public array $positions,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('game.'.$this->game->uuid)];
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
