<?php

namespace App\Jobs\Campaign;

use App\Jobs\Middleware\LimitConcurrentRenders;
use App\Models\CustomCharacter;
use App\Services\Campaign\LeaderCardImageGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

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

    /** Time-based so being released (overlap, no free render slot) never counts against a try count. */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(15);
    }

    public function __construct(public int $customCharacterId) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("leader-card-{$this->customCharacterId}"))->releaseAfter(2)->expireAfter(150), new LimitConcurrentRenders(priority: $this->isCampaignCard())];
    }

    /** Leaders and totems jump the queue for the reserved render slot; homebrew cards wait for the shared ones. */
    private function isCampaignCard(): bool
    {
        return CustomCharacter::query()->whereKey($this->customCharacterId)
            ->where(fn ($q) => $q->where('is_campaign_leader', true)->orWhere('is_campaign_totem', true))
            ->exists();
    }

    public function handle(LeaderCardImageGenerator $generator): void
    {
        // Edits from here on need a fresh render of their own, so release the coalescing marker before reading the card.
        Cache::forget(CustomCharacter::renderPendingKey($this->customCharacterId));

        $character = CustomCharacter::find($this->customCharacterId);
        if (! $character) {
            return;
        }

        Cache::put(CustomCharacter::renderingKey($character->id), true, $this->timeout + 30);

        try {
            $generator->generate($character);
        } catch (\Throwable $e) {
            // Best-effort: a render failure must not bubble up and block
            // whatever request (Aftermath advance, Leader Builder save, ...)
            // triggered this dispatch.
            report($e);
        } finally {
            Cache::forget(CustomCharacter::renderingKey($character->id));
        }
    }
}
