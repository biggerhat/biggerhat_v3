<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A saved card render finished, sent to the owner's private channel (App.Models.User.{id}, already authorized in
 * routes/channels.php) so the page that is waiting on it can reload instead of polling.
 * kind: 'custom_character' (id = custom character) | 'crew_card' (id = campaign crew).
 */
class CardImageGenerated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public const KIND_CUSTOM_CHARACTER = 'custom_character';

    public const KIND_CREW_CARD = 'crew_card';

    public function __construct(
        public int $userId,
        public string $kind,
        public int $id,
        public ?string $generatedAt,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'card.image.generated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['kind' => $this->kind, 'id' => $this->id, 'generated_at' => $this->generatedAt];
    }
}
