<?php

namespace App\Jobs\Campaign;

use App\Models\CustomCharacter;
use App\Services\Campaign\LeaderCardImageGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Regenerates a Campaign Leader/Totem's card images in the background.
 * ShouldBeUnique collapses a burst of saves (e.g. picking several Tier-4
 * advancements in one Aftermath submit) into a single render.
 */
class GenerateLeaderCardImage implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(public int $customCharacterId) {}

    public function uniqueId(): string
    {
        return "leader-card-{$this->customCharacterId}";
    }

    public function handle(LeaderCardImageGenerator $generator): void
    {
        $character = CustomCharacter::find($this->customCharacterId);
        if (! $character) {
            return;
        }

        try {
            $generator->generate($character);
        } catch (\Throwable $e) {
            // Best-effort: a render failure must not bubble up and block
            // whatever request (Aftermath advance, Leader Builder save, ...)
            // triggered this dispatch.
            report($e);
        }
    }
}
