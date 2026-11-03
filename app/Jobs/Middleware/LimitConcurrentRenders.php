<?php

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caps how many headless-Chrome card renders run at once across all queue workers (config
 * services.browsershot.max_concurrent_renders). A job that finds every slot busy goes back on the queue a few
 * seconds later instead of piling another Chrome onto the server.
 */
class LimitConcurrentRenders
{
    public function handle(object $job, Closure $next): void
    {
        $slots = max(1, (int) config('services.browsershot.max_concurrent_renders', 2));

        for ($i = 0; $i < $slots; $i++) {
            $lock = Cache::lock("card-render-slot:{$i}", 180);

            if ($lock->get()) {
                try {
                    $next($job);
                } finally {
                    $lock->release();
                }

                return;
            }
        }

        $job->release(5);
    }
}
