<?php

namespace App\Jobs\Campaign;

use App\Models\CustomCharacter;
use App\Services\Campaign\LeaderCardImageGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * Regenerates a Campaign Leader/Totem's card images in the background.
 *
 * Uses WithoutOverlapping (keyed per character) rather than ShouldBeUnique:
 * ShouldBeUnique collapses a burst of saves into one render, but silently
 * *drops* any dispatch that arrives while a render is already in flight —
 * a quick undo-then-redo of an advancement could leave the on-disk image
 * reflecting the undone state forever, since the redo's render never ran.
 * WithoutOverlapping instead releases an overlapping dispatch back onto the
 * queue to retry shortly after, so the latest character state always
 * eventually gets rendered.
 */
class GenerateLeaderCardImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(public int $customCharacterId) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("leader-card-{$this->customCharacterId}"))->releaseAfter(2)->expireAfter(150)];
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
