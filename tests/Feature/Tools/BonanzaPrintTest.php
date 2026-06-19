<?php

use App\Models\LootCard;
use Illuminate\Support\Facades\Storage;

it('renders the printer-friendly Bonanza loot deck PDF as an image cut-grid', function () {
    Storage::fake('public');
    // A minimal valid 1x1 PNG stands in for a captured card image.
    Storage::disk('public')->put(
        'loot/test.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==')
    );

    LootCard::create([
        'slug' => 'test-loot',
        'name' => 'Test Loot',
        'suit' => 'crow',
        'value' => 7,
        'value_label' => '7',
        'sort_order' => 1,
        'image' => 'loot/test.png',
    ]);

    $resp = $this->get(route('tools.bonanza_loot_deck.print'));

    $resp->assertOk();
    expect($resp->headers->get('content-type'))->toContain('application/pdf');
    expect(strlen($resp->getContent()))->toBeGreaterThan(1000);
});

it('skips cards without an image and still renders', function () {
    LootCard::create([
        'slug' => 'no-image',
        'name' => 'No Image',
        'suit' => 'ram',
        'value' => 3,
        'value_label' => '3',
        'sort_order' => 1,
        'image' => null,
    ]);

    $this->get(route('tools.bonanza_loot_deck.print'))->assertOk();
});
