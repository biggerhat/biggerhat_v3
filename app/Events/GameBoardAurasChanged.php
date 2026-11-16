<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** A player shared, changed or withdrew an aura. Carries the whole list, which is small, so a missed event heals on the next one. */
class GameBoardAurasChanged implements ShouldBroadcastNow
{
    use Concerns\LogsBroadcast;
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<array{id: string, owner_id: string, range: float|int, locked: bool, x?: float|int, y?: float|int}>  $auras
     */
    public function __construct(
        public Game $game,
        public array $auras,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('game.'.$this->game->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'GameBoardAurasChanged';
    }

    public function broadcastWith(): array
    {
        return ['auras' => $this->auras];
    }
}
