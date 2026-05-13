<?php

use App\Models\BlogPost;
use App\Models\Character;
use App\Models\Miniature;
use App\Models\Package;
use App\Models\User;

// These tests assert the server-rendered HTML carries the per-page meta
// tags. The Vue <SeoHead> component replaces them on client-side nav, but
// link unfurlers (Discord, Slack, Twitter) only ever see the Blade output —
// so this is what determines social preview correctness.

it('renders dynamic title and description in initial HTML for a blog post', function () {
    $post = BlogPost::factory()->published()->create([
        'title' => 'Welcome to Malifaux',
        'excerpt' => 'A short introduction to the game and its factions.',
        'featured_image' => 'posts/welcome.jpg',
        'user_id' => User::factory(),
    ]);

    $response = $this->get(route('blog.view', $post->slug));

    $response->assertOk();
    // Title — Blade template uses `<title inertia>` so we match the literal string.
    $response->assertSee('<title inertia>Welcome to Malifaux — BiggerHat</title>', false);
    // og:title carries the same suffixed string
    $response->assertSee('property="og:title" content="Welcome to Malifaux — BiggerHat"', false);
    // The excerpt should land verbatim in both description and og:description
    $response->assertSee('A short introduction to the game and its factions.', false);
    // og:type should be 'article' (overrides the default 'website')
    $response->assertSee('property="og:type" content="article"', false);
    // featured_image is a storage path, so it should become an absolute URL ending with the path
    $response->assertSee('property="og:image" content="', false);
    $response->assertSee('/storage/posts/welcome.jpg"', false);
});

it('falls back to site defaults when a page does not provide page_meta', function () {
    // The homepage doesn't attach page_meta, so it exercises the Blade fallback.
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('<title inertia>BiggerHat</title>', false);
    $response->assertSee('property="og:image" content="', false);
    $response->assertSee('/images/biggerhat-og.png"', false);
});

it('renders character meta with miniature image as og:image', function () {
    // CharacterObserver regenerates display_name from name+title on save, so
    // we set `name` rather than `display_name` to control the rendered title.
    $character = Character::factory()->create(['name' => 'Sonnia Criid', 'title' => null]);
    $miniature = Miniature::factory()->create([
        'character_id' => $character->id,
        'front_image' => 'characters/sonnia.png',
        'slug' => 'sonnia-classic',
    ]);

    $response = $this->get(route('characters.view', [
        'character' => $character->slug,
        'miniature' => $miniature->id,
        'slug' => $miniature->slug,
    ]));

    $response->assertOk();
    $response->assertSee('Sonnia Criid — BiggerHat', false);
    $response->assertSee('/storage/characters/sonnia.png', false);
});

it('renders package meta with description from package fields', function () {
    $package = Package::factory()->create([
        'name' => 'Sonnia Core Box',
        'description' => 'Contains Sonnia Criid and her starting crew.',
        'front_image' => 'packages/sonnia-box.jpg',
    ]);

    $response = $this->get(route('packages.view', $package->slug));

    $response->assertOk();
    $response->assertSee('Sonnia Core Box — BiggerHat', false);
    $response->assertSee('Contains Sonnia Criid and her starting crew.', false);
    $response->assertSee('/storage/packages/sonnia-box.jpg', false);
});

it('serves the default OG image without 404ing', function () {
    // Direct file existence check — Discord scrapers will 404 if missing.
    expect(file_exists(public_path('images/biggerhat-og.png')))->toBeTrue();
});
