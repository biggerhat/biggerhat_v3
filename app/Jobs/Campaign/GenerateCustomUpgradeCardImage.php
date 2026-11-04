<?php

namespace App\Jobs\Campaign;

use App\Jobs\Middleware\LimitConcurrentRenders;
use App\Models\CustomUpgrade;
use App\Services\Campaign\CustomUpgradeCardImageGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Renders a custom upgrade / crew card's saved images. Same shape as GenerateLeaderCardImage: a coalescing marker is
 * released as the job starts, a "rendering" marker covers the Chrome run, and overlapping dispatches are released
 * rather than dropped so the latest edit always gets rendered.
 */
class GenerateCustomUpgradeCardImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(public int $customUpgradeId) {}

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(15);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("custom-upgrade-card-{$this->customUpgradeId}"))->releaseAfter(2)->expireAfter(150), new LimitConcurrentRenders(priority: false)];
    }

    public function handle(CustomUpgradeCardImageGenerator $generator): void
    {
        Cache::forget(CustomUpgrade::renderPendingKey($this->customUpgradeId));

        $upgrade = CustomUpgrade::find($this->customUpgradeId);
        if (! $upgrade) {
            return;
        }

        Cache::put(CustomUpgrade::renderingKey($upgrade->id), true, $this->timeout + 30);

        try {
            $generator->generate($upgrade);
        } catch (\Throwable $e) {
            report($e);
        } finally {
            Cache::forget(CustomUpgrade::renderingKey($upgrade->id));
        }
    }
}
