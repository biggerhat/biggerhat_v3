<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts Bonanza deck PDF generation progress to the admin page over Reverb.
 * status: 'generating' | 'ready' | 'failed'.
 */
class BonanzaDeckPdfStatus implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $status,
        public ?string $url = null,
        public ?int $generatedAt = null,
        public ?string $message = null,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('bonanza-deck');
    }

    public function broadcastAs(): string
    {
        return 'pdf.status';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'status' => $this->status,
            'url' => $this->url,
            'generated_at' => $this->generatedAt,
            'message' => $this->message,
        ];
    }
}
