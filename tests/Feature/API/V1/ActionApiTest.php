<?php

use App\Enums\ActionTypeEnum;
use App\Models\Action;

it('lists actions with pagination', function () {
    Action::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/actions');

    $response->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('data'))->toHaveCount(3);
});

it('filters actions by type', function () {
    Action::factory()->create(['type' => ActionTypeEnum::Attack]);
    Action::factory()->create(['type' => ActionTypeEnum::Tactical]);

    $response = $this->getJson('/api/v1/actions?type=attack');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.type'))->toBe('attack');
});

it('searches actions by name', function () {
    Action::factory()->create(['name' => 'Greatsword']);
    Action::factory()->create(['name' => 'Obey']);

    $response = $this->getJson('/api/v1/actions?search=Greatsword');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('shows a single action by slug', function () {
    $action = Action::factory()->create();

    $response = $this->getJson("/api/v1/actions/{$action->slug}");

    $response->assertOk()
        ->assertJsonPath('data.id', $action->id)
        ->assertJsonStructure(['data' => ['triggers']]);
});

it('returns 404 for missing action', function () {
    $this->getJson('/api/v1/actions/nonexistent-slug')
        ->assertNotFound();
});
