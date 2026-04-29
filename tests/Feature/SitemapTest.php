<?php

use App\Models\TOS\Allegiance;
use App\Models\TOS\Asset;
use App\Models\TOS\Stratagem;
use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;

it('returns sitemap.xml as XML', function () {
    $resp = $this->get('/sitemap.xml');

    $resp->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $body = $resp->streamedContent();
    expect($body)->toContain('<urlset')->and($body)->toContain('<loc>');
});

it('includes TOS landing routes', function () {
    $body = $this->get('/sitemap.xml')->streamedContent();

    foreach ([
        '/tos',
        '/tos/allegiances',
        '/tos/units',
        '/tos/stratagems',
        '/tos/assets',
        '/tos/special-rules',
        '/tos/allegiance-cards',
        '/tos/abilities',
        '/tos/actions',
        '/tos/triggers',
    ] as $path) {
        expect($body)->toContain($path);
    }
});

it('includes per-entity TOS rows when records exist', function () {
    $allegiance = Allegiance::factory()->create();
    $asset = Asset::factory()->create();
    $stratagem = Stratagem::factory()->create();
    $unit = Unit::factory()->create();
    $sculpt = UnitSculpt::factory()->for($unit, 'unit')->create();

    $body = $this->get('/sitemap.xml')->streamedContent();

    expect($body)->toContain($allegiance->slug);
    expect($body)->toContain($asset->slug);
    expect($body)->toContain($stratagem->slug);
    expect($body)->toContain($sculpt->slug);
});
