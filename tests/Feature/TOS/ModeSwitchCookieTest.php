<?php

use App\Models\TOS\Allegiance;

it('switching to tos sets cookie and redirects to TOS home', function () {
    Allegiance::factory()->create();

    $response = $this->post(route('system.switch'), ['system' => 'tos']);

    $response->assertRedirect(route('tos.index'));
    $cookies = $response->headers->getCookies();
    $names = array_map(fn ($c) => $c->getName(), $cookies);
    expect($names)->toContain('preferred_game_system');

    $cookie = collect($cookies)->first(fn ($c) => $c->getName() === 'preferred_game_system');
    expect($cookie?->getValue())->toBe('tos');
});

it('switching to malifaux sets cookie and redirects to home', function () {
    $response = $this->post(route('system.switch'), ['system' => 'malifaux']);

    $response->assertRedirect(route('index'));

    $cookies = $response->headers->getCookies();
    $cookie = collect($cookies)->first(fn ($c) => $c->getName() === 'preferred_game_system');
    expect($cookie?->getValue())->toBe('malifaux');
});

it('rejects an unknown system', function () {
    $this->postJson(route('system.switch'), ['system' => 'something-else'])
        ->assertStatus(422);
});

it('rejects a missing system', function () {
    $this->postJson(route('system.switch'), [])
        ->assertStatus(422);
});
