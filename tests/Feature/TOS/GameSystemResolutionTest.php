<?php

use App\Models\TOS\Allegiance;

it('returns tos for /tos URLs', function () {
    Allegiance::factory()->create();

    $this->get(route('tos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('currentGameSystem.slug', 'tos'));
});

it('returns malifaux for the home/index URL', function () {
    $this->get(route('index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('currentGameSystem.slug', 'malifaux'));
});

it('returns tos for /tos/allegiances URLs', function () {
    Allegiance::factory()->create();

    $this->get(route('tos.allegiances.index'))
        ->assertInertia(fn ($page) => $page->where('currentGameSystem.slug', 'tos'));
});

it('cookie fallback applies on game-agnostic routes only', function () {
    // Settings/profile routes are game-agnostic — cookie should win.
    // Use withUnencryptedCookie because preferred_game_system is exempted from
    // the encrypt-cookies middleware (so JS can read it on the client).
    $this->withUnencryptedCookie('preferred_game_system', 'tos')
        ->actingAs(\App\Models\User::factory()->create())
        ->get('/settings/appearance')
        ->assertInertia(fn ($page) => $page->where('currentGameSystem.slug', 'tos'));
});

it('cookie does NOT override URL on Malifaux pages', function () {
    // Even with the cookie set to TOS, visiting /keywords (Malifaux scaffolding)
    // must report Malifaux because URL is the source of truth.
    $this->withUnencryptedCookie('preferred_game_system', 'tos')
        ->get(route('keywords.index'))
        ->assertInertia(fn ($page) => $page->where('currentGameSystem.slug', 'malifaux'));
});

it('exposes a switch_to target with the opposite system', function () {
    Allegiance::factory()->create();

    $this->get(route('tos.index'))
        ->assertInertia(fn ($page) => $page
            ->where('currentGameSystem.slug', 'tos')
            ->where('currentGameSystem.switch_to.slug', 'malifaux')
        );

    $this->get(route('index'))
        ->assertInertia(fn ($page) => $page
            ->where('currentGameSystem.slug', 'malifaux')
            ->where('currentGameSystem.switch_to.slug', 'tos')
        );
});
