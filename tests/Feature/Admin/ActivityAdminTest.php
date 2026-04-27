<?php

use App\Models\Announcement;
use App\Models\Character;
use App\Models\Game;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->admin = User::factory()->create()->assignRole('super_admin');
});

function makeAnnouncement(): Announcement
{
    return Announcement::create([
        'message' => 'Test '.uniqid(),
        'level' => 'info',
        'audience' => 'all',
        'is_dismissable' => true,
    ]);
}

it('records a created event when a tracked model is saved', function () {
    $this->actingAs($this->admin);

    $announcement = makeAnnouncement();

    $row = Activity::query()
        ->where('subject_type', Announcement::class)
        ->where('subject_id', $announcement->id)
        ->first();
    expect($row)->not->toBeNull();
    expect($row->event)->toBe('created');
    expect($row->log_name)->toBe('announcement');
});

it('records an updated event with a dirty diff for LogsAdminActivity models', function () {
    $this->actingAs($this->admin);

    $character = Character::factory()->create();
    $originalName = $character->fresh()->display_name;
    $character->update(['display_name' => 'New Name']);

    $row = Activity::query()
        ->where('subject_type', Character::class)
        ->where('subject_id', $character->id)
        ->where('event', 'updated')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->properties->get('attributes')['display_name'])->toBe('New Name');
    expect($row->properties->get('old')['display_name'])->toBe($originalName);
});

it('does NOT record updates for LogsCreationActivity models', function () {
    $this->actingAs($this->admin);

    $game = Game::factory()->create();
    $game->update(['encounter_size' => $game->encounter_size + 5]);

    $rows = Activity::query()
        ->where('subject_type', Game::class)
        ->where('subject_id', $game->id)
        ->get();

    expect($rows)->toHaveCount(1);
    expect($rows->first()->event)->toBe('created');
});

it('renders the admin activity page with rows visible to a super_admin', function () {
    $this->actingAs($this->admin);
    makeAnnouncement();

    // Filter to announcement so we don't accidentally count the User row
    // logged when beforeEach() created the admin.
    $response = $this->get(route('admin.activity.index', ['log' => 'announcement']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Activity/Index')
        ->has('activities.data', 1)
        ->where('activities.data.0.event', 'created')
        ->where('activities.data.0.log_name', 'announcement')
        ->has('log_names')
        ->where('storage_ready', true)
    );
});

it('filters by log_name', function () {
    $this->actingAs($this->admin);
    makeAnnouncement();
    Character::factory()->create();

    $response = $this->get(route('admin.activity.index', ['log' => 'character']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('filters.log', 'character')
        ->has('activities.data', 1)
        ->where('activities.data.0.log_name', 'character')
    );
});

it('filters by event', function () {
    $this->actingAs($this->admin);
    $character = Character::factory()->create(['display_name' => 'Before']);
    $character->update(['display_name' => 'After']);

    $updatedRows = Activity::query()->where('event', 'updated')->count();
    expect($updatedRows)->toBe(1);

    $response = $this->get(route('admin.activity.index', ['event' => 'updated']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('activities.data', 1)
        ->where('activities.data.0.event', 'updated')
    );
});

it('blocks non-super_admin users from the activity page', function () {
    $regular = User::factory()->create();
    $this->actingAs($regular);

    $this->get(route('admin.activity.index'))->assertForbidden();
});

it('discovers all models using LogsAdminActivity / LogsCreationActivity in log_names', function () {
    $this->actingAs($this->admin);

    \Illuminate\Support\Facades\Cache::forget('admin:activity:log_names:v2');

    $response = $this->get(route('admin.activity.index'));

    $response->assertOk();
    $response->assertInertia(function ($page) {
        $names = collect($page->toArray()['props']['log_names'] ?? [])->all();
        expect($names)->toContain('character', 'announcement', 'upgrade', 'blogpost', 'user', 'game', 'tournament', 'tournamentplayer', 'tournamentround');

        return $page;
    });
});
