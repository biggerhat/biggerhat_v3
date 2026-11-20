<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** A player changed the terrain layout. Carries the whole layout: it is small, and replacing beats merging edits. */
class GameBoardTerrainChanged implements ShouldBroadcastNow
{
    use Concerns\LogsBroadcast;
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, array<string, mixed>>  $terrain
     */
    public function __construct(
        public Game $game,
        public array $terrain,
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
        return 'GameBoardTerrainChanged';
    }

    public function broadcastWith(): array
    {
        return ['terrain' => $this->terrain];
    }
}
