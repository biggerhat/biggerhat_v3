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

class GameSetupStepCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    use Concerns\LogsBroadcast;

    public function __construct(
        public Game $game,
        public GamePlayer $player,
        public string $step,
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
        return 'GameSetupStepCompleted';
    }

    public function broadcastWith(): array
    {
        return [
            'player_id' => $this->player->id,
            'step' => $this->step,
            'game_status' => $this->game->fresh()->status->value,
        ];
    }
}
