<?php

namespace App\Events;

use App\Models\Tournament;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TournamentUpdated implements ShouldBroadcastNow
{
    use Concerns\LogsBroadcast;
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Tournament $tournament,
        public string $action = 'updated',
        public ?int $roundId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('tournament.'.$this->tournament->uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TournamentUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'status' => $this->tournament->status->value,
            // Which round changed, when a single round did (lets a client skip work for the others).
            'round_id' => $this->roundId,
        ];
    }
}
