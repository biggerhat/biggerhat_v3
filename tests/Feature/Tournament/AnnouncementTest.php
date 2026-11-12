<?php

use App\Events\TournamentUpdated;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->tournament = Tournament::factory()->active()->create(['creator_id' => $this->owner->id]);
    $this->url = fn () => route('tournaments.announcement', $this->tournament);
});

it('lets the organizer post an announcement that the public page and display mode show', function () {
    Event::fake([TournamentUpdated::class]);

    $this->actingAs($this->owner)->putJson(($this->url)(), ['announcement' => '  Lunch until 1:15  '])
        ->assertOk()->assertJsonPath('announcement', 'Lunch until 1:15');

    $fresh = $this->tournament->fresh();
    expect($fresh->announcement)->toBe('Lunch until 1:15')->and($fresh->announced_at)->not->toBeNull();
    Event::assertDispatched(TournamentUpdated::class, fn ($e) => $e->action === 'announcement');

    auth()->logout();
    foreach (['tournaments.view', 'tournaments.display'] as $route) {
        $this->get(route($route, $this->tournament->uuid))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('tournament.announcement', 'Lunch until 1:15')->has('tournament.announced_at'));
    }
});

it('clears the announcement when the text is empty', function () {
    $this->tournament->update(['announcement' => 'Old news', 'announced_at' => now()]);

    $this->actingAs($this->owner)->putJson(($this->url)(), ['announcement' => ''])->assertOk();

    expect($this->tournament->fresh())->announcement->toBeNull()->announced_at->toBeNull();
});

it('rejects an over-long announcement', function () {
    $this->actingAs($this->owner)->putJson(($this->url)(), ['announcement' => str_repeat('a', 501)])
        ->assertStatus(422)->assertJsonValidationErrors(['announcement']);
});

it('is organizer-only', function () {
    $this->actingAs(User::factory()->create())->putJson(($this->url)(), ['announcement' => 'Hi'])->assertForbidden();
    expect($this->tournament->fresh()->announcement)->toBeNull();
});

it('says which round a score change belongs to in the broadcast', function () {
    $event = new TournamentUpdated($this->tournament, 'score_updated', 7);

    expect($event->broadcastWith())->toMatchArray(['action' => 'score_updated', 'round_id' => 7]);
    expect((new TournamentUpdated($this->tournament))->broadcastWith()['round_id'])->toBeNull();
});
