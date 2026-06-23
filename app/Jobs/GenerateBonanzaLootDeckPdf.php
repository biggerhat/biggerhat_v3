<?php

namespace App\Jobs;

use App\Events\BonanzaDeckPdfStatus;
use App\Services\BonanzaDeckPdfGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Regenerates the cached Bonanza deck PDF in the background and broadcasts
 * progress. ShouldBeUnique collapses the burst of dispatches that a multi-card
 * edit session would otherwise produce into a single render.
 */
class GenerateBonanzaLootDeckPdf implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public string $uniqueId = 'bonanza-deck-pdf';

    public function handle(BonanzaDeckPdfGenerator $generator): void
    {
        BonanzaDeckPdfStatus::dispatch('generating');

        try {
            $generator->generate();
        } catch (\Throwable $e) {
            // Best-effort cache warming: a render failure must not bubble up
            // (it would break the card-save request that dispatched this under
            // a sync queue, and the print route regenerates on demand anyway).
            report($e);
            BonanzaDeckPdfStatus::dispatch('failed', null, null, $e->getMessage());

            return;
        }

        BonanzaDeckPdfStatus::dispatch('ready', $generator->url(), $generator->generatedAt());
    }
}
