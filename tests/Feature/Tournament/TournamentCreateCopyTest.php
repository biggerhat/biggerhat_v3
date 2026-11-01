<?php

use App\Models\Tournament;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->organizer = User::factory()->create();
});

it('prefills the create form from a tournament the user organizes', function () {
    $source = Tournament::factory()->create([
        'creator_id' => $this->organizer->id,
        'name' => 'Spring Showdown',
        'planned_rounds' => 4,
        'round_time_limit' => 120,
        'location' => 'Boston',
    ]);

    $this->actingAs($this->organizer)
        ->get(route('tournaments.create', ['copy' => $source->uuid]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('template.name', 'Spring Showdown (copy)')
            ->where('template.planned_rounds', 4)
            ->where('template.round_time_limit', 120)
            ->where('template.location', 'Boston')
            ->has('previous_events', 1));
});

it('ignores a copy request for a tournament the user does not organize', function () {
    $someoneElses = Tournament::factory()->create(['name' => 'Private Event']);

    $this->actingAs($this->organizer)
        ->get(route('tournaments.create', ['copy' => $someoneElses->uuid]))
        ->assertInertia(fn (Assert $page) => $page->where('template', null)->has('previous_events', 0));
});

it('renders a blank form with no copy parameter', function () {
    $this->actingAs($this->organizer)
        ->get(route('tournaments.create'))
        ->assertInertia(fn (Assert $page) => $page->where('template', null));
});
