<?php

use App\Enums\PermissionEnum;
use App\Models\TabletopLayout;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;

beforeEach(fn () => Feature::for(null)->activate('experiment-tabletop'));

it('is hidden until the tabletop flag is on, and for anyone without the beta permission', function () {
    Feature::for(null)->deactivate('experiment-tabletop');
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('tools.tabletop.tables'))->assertNotFound();

    $user->givePermissionTo(PermissionEnum::UseTabletopBeta->value);
    $this->actingAs($user)->get(route('tools.tabletop.tables'))->assertOk();
});

it('sends a guest to sign in', function () {
    $this->get(route('tools.tabletop.tables'))->assertRedirect();
});

it('lists your own tables and the public ones, and nobody elses private or link-only tables', function () {
    $me = User::factory()->create();
    $other = User::factory()->create(['name' => 'Maker']);
    TabletopLayout::factory()->for($me)->create(['name' => 'Mine private']);
    TabletopLayout::factory()->for($me)->public()->create(['name' => 'Mine public']);
    TabletopLayout::factory()->for($other)->public()->create(['name' => 'Their public']);
    TabletopLayout::factory()->for($other)->shared()->create(['name' => 'Their link only']);
    TabletopLayout::factory()->for($other)->create(['name' => 'Their private']);

    $this->actingAs($me)->get(route('tools.tabletop.tables'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Tools/TabletopTables')
        ->has('mine', 2)
        ->where('mine.0.mine', true)
        ->has('gallery.data', 2)
        ->where('gallery.data', fn ($rows) => collect($rows)->pluck('name')->sort()->values()->all() === ['Mine public', 'Their public'])
        ->where('gallery.data', fn ($rows) => collect($rows)->firstWhere('name', 'Their public')['owner'] === 'Maker')
        ->has('table_images', 6)
        ->has('terrain_images'));
});

it('searches the public gallery by name and pages it twelve at a time', function () {
    $me = User::factory()->create();
    TabletopLayout::factory()->public()->count(13)->create();
    TabletopLayout::factory()->public()->create(['name' => 'Foggy Docks']);

    $this->actingAs($me)->get(route('tools.tabletop.tables'))->assertInertia(fn (Assert $page) => $page->has('gallery.data', 12)->where('gallery.total', 14)->where('gallery.last_page', 2));
    $this->actingAs($me)->get(route('tools.tabletop.tables', ['search' => 'foggy']))->assertInertia(fn (Assert $page) => $page
        ->has('gallery.data', 1)->where('gallery.data.0.name', 'Foggy Docks')->where('active_search', 'foggy'));
    $this->actingAs($me)->get(route('tools.tabletop.tables', ['search' => '100%']))->assertInertia(fn (Assert $page) => $page->has('gallery.data', 0));
});

it('turns a visibility choice into the right flags, and public implies shared', function () {
    $me = User::factory()->create();
    $layout = TabletopLayout::factory()->for($me)->create();

    foreach (['link' => [true, false], 'public' => [true, true], 'private' => [false, false]] as $visibility => [$shared, $public]) {
        $this->actingAs($me)->put(route('tools.tabletop.layouts.update', $layout->uuid), ['visibility' => $visibility])->assertRedirect();
        $layout->refresh();
        expect([$layout->is_shared, $layout->is_public, $layout->visibility()])->toBe([$shared, $public, $visibility]);
    }

    $this->actingAs($me)->put(route('tools.tabletop.layouts.update', $layout->uuid), ['visibility' => 'everyone'])->assertSessionHasErrors('visibility');
    $this->actingAs($me)->post(route('tools.tabletop.layouts.store'), [
        'name' => 'Born public', 'visibility' => 'public',
        'terrain' => [['id' => 'p1', 'name' => 'Wall', 'traits' => [], 'surfaces' => [['id' => 'p1-1', 'top' => 1, 'shape' => ['kind' => 'rect', 'x' => 1, 'y' => 1, 'w' => 4, 'h' => 1]]]]],
    ])->assertRedirect();
    expect(TabletopLayout::where('name', 'Born public')->first()->visibility())->toBe('public');
});

it('lets anyone with access open a public table by its link and save a copy', function () {
    $other = User::factory()->create();
    $public = TabletopLayout::factory()->public()->create(['name' => 'From the gallery']);

    $this->actingAs($other)->get(route('tools.tabletop', ['layout' => $public->uuid]))->assertInertia(fn (Assert $page) => $page->where('incoming_layout.name', 'From the gallery')->where('incoming_layout.visibility', 'public')->where('editing', false));
    $this->actingAs($other)->post(route('tools.tabletop.layouts.copy', $public->uuid))->assertRedirect();
    $copy = TabletopLayout::where('user_id', $other->id)->firstOrFail();
    expect($copy->visibility())->toBe('private')->and($copy->copied_from)->toBe($public->uuid);
});

it('opens your own table for editing only when you ask, and never someone elses', function () {
    $me = User::factory()->create();
    $mine = TabletopLayout::factory()->for($me)->create();
    $theirs = TabletopLayout::factory()->public()->create();

    $this->actingAs($me)->get(route('tools.tabletop', ['layout' => $mine->uuid]))->assertInertia(fn (Assert $page) => $page->where('editing', false));
    $this->actingAs($me)->get(route('tools.tabletop', ['layout' => $mine->uuid, 'edit' => 1]))->assertInertia(fn (Assert $page) => $page->where('editing', true));
    $this->actingAs($me)->get(route('tools.tabletop', ['layout' => $theirs->uuid, 'edit' => 1]))->assertInertia(fn (Assert $page) => $page->where('editing', false)->where('incoming_layout.mine', false));
});

it('tells every page whether the viewer may use the tabletop, for the menu', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('tools.tabletop.tables'))->assertInertia(fn (Assert $page) => $page->where('auth.can_use_tabletop', true));

    Feature::for(null)->deactivate('experiment-tabletop');
    $this->actingAs($user)->get(route('tools.compare'))->assertInertia(fn (Assert $page) => $page->where('auth.can_use_tabletop', false));
    $user->givePermissionTo(PermissionEnum::UseTabletopBeta->value);
    $this->actingAs($user)->get(route('tools.compare'))->assertInertia(fn (Assert $page) => $page->where('auth.can_use_tabletop', true));
});

it('opens a fresh empty table only when asked, and never together with a saved one', function () {
    $me = User::factory()->create();
    $mine = TabletopLayout::factory()->for($me)->create();

    $this->actingAs($me)->get(route('tools.tabletop'))->assertInertia(fn (Assert $page) => $page->where('fresh', false));
    $this->actingAs($me)->get(route('tools.tabletop', ['new' => 1]))->assertInertia(fn (Assert $page) => $page->where('fresh', true)->where('incoming_layout', null)->where('editing', false));
    $this->actingAs($me)->get(route('tools.tabletop', ['new' => 1, 'layout' => $mine->uuid]))->assertInertia(fn (Assert $page) => $page->where('fresh', false)->where('incoming_layout.uuid', $mine->uuid));
});
