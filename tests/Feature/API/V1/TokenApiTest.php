<?php

use App\Models\Token;

it('lists tokens with pagination', function () {
    Token::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/tokens');

    $response->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('data'))->toHaveCount(3);
});

it('searches tokens by name', function () {
    Token::factory()->create(['name' => 'Focus']);
    Token::factory()->create(['name' => 'Poison']);

    $response = $this->getJson('/api/v1/tokens?search=Focus');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('shows a single token by slug', function () {
    $token = Token::factory()->create();

    $response = $this->getJson("/api/v1/tokens/{$token->slug}");

    $response->assertOk()
        ->assertJsonPath('data.id', $token->id);
});

it('returns 404 for missing token', function () {
    $this->getJson('/api/v1/tokens/nonexistent-slug')
        ->assertNotFound();
});
