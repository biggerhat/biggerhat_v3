<?php

use App\Services\BonanzaDeckPdfGenerator;
use Illuminate\Support\Facades\Storage;

it('serves the cached print PDF without re-rendering', function () {
    Storage::fake('public');
    // Pre-seed the cache so the controller streams it instead of invoking Chrome.
    Storage::disk('public')->put(BonanzaDeckPdfGenerator::PATH, '%PDF-1.4 fake');

    $resp = $this->get(route('tools.bonanza_loot_deck.print'));

    $resp->assertOk();
    expect($resp->headers->get('content-type'))->toContain('application/pdf');
    expect($resp->getContent())->toStartWith('%PDF');
});
