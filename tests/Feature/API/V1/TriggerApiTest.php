<?php

use App\Models\Trigger;

it('lists triggers with pagination', function () {
    Trigger::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/triggers');

    $response->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('data'))->toHaveCount(3);
});

it('searches triggers by name', function () {
    Trigger::factory()->create(['name' => 'Critical Strike']);
    Trigger::factory()->create(['name' => 'Onslaught']);

    $response = $this->getJson('/api/v1/triggers?search=Critical');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('shows a single trigger by slug', function () {
    $trigger = Trigger::factory()->create();

    $response = $this->getJson("/api/v1/triggers/{$trigger->slug}");

    $response->assertOk()
        ->assertJsonPath('data.id', $trigger->id)
        ->assertJsonStructure(['data' => ['actions']]);
});

it('returns 404 for missing trigger', function () {
    $this->getJson('/api/v1/triggers/nonexistent-slug')
        ->assertNotFound();
});
