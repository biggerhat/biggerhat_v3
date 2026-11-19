<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Markers were placed, moved or picked up. Carries the whole list, which is small, so a missed event heals on the next one. */
class GameBoardMarkersChanged implements ShouldBroadcastNow
{
    use Concerns\LogsBroadcast;
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<array{id: string, marker_id: int, x: float|int, y: float|int}>  $markers
     */
    public function __construct(
        public Game $game,
        public array $markers,
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
        return 'GameBoardMarkersChanged';
    }

    public function broadcastWith(): array
    {
        return ['markers' => $this->markers];
    }
}
