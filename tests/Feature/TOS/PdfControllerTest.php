<?php

use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('returns 404 when the sculpt has no card art', function () {
    $unit = Unit::factory()->withSides()->create();
    $sculpt = UnitSculpt::factory()->forUnit($unit)->create();

    $this->get(route('tos.units.pdf', $sculpt->slug))->assertNotFound();
});

it('streams a PDF when the sculpt has a combination image', function () {
    $unit = Unit::factory()->withSides()->create(['name' => 'Earl Burns']);
    $sculpt = UnitSculpt::factory()->forUnit($unit)->create();

    // Seed a real-ish JPG so dompdf can decode it.
    $image = UploadedFile::fake()->image('combo.jpg', 800, 600)->mimeType('image/jpeg');
    $path = 'tos/sculpts/'.$sculpt->slug.'.jpg';
    Storage::disk('public')->put($path, $image->getContent());
    $sculpt->update(['combination_image' => $path]);

    $response = $this->get(route('tos.units.pdf', $sculpt->slug));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});
