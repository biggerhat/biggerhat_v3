<?php

use App\Enums\FeedbackStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Models\Announcement;
use App\Models\Feedback;
use App\Models\Tournament;
use App\Models\TournamentRound;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }
    foreach (RoleEnum::cases() as $role) {
        Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
    }
    Role::findByName(RoleEnum::SuperAdmin->value)->syncPermissions(Permission::all());

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole(RoleEnum::SuperAdmin->value);

    // Gets past `admin.any` but is not a super admin.
    $this->staff = User::factory()->create();
    $this->staff->givePermissionTo(PermissionEnum::ViewUser->value);
});

dataset('super_admin_pages', [
    'activity' => ['admin.activity.index'],
    'games' => ['admin.games.index'],
    'failed jobs' => ['admin.failed_jobs.index'],
    'features' => ['admin.features.index'],
    'api tokens' => ['admin.api_tokens.index'],
    'sessions' => ['admin.sessions.index'],
    'custom cards' => ['admin.custom_cards.index'],
    'image health' => ['admin.image_health.index'],
    'tournament override' => ['admin.tournaments.index'],
    'trash' => ['admin.trash.index'],
    'maintenance' => ['admin.maintenance.index'],
    'schedule' => ['admin.schedule.index'],
    'cache' => ['admin.cache.index'],
    'announcements' => ['admin.announcements.index'],
]);

it('gates super-admin tooling pages by role', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
    $this->actingAs($this->staff)->get(route($routeName))->assertForbidden();
    $this->actingAs($this->superAdmin)->get(route($routeName))->assertOk();
})->with('super_admin_pages');

it('shows the dashboard to a super admin and redirects single-section staff', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Dashboard/Index')->has('groups'));

    // staff only has view_user → exactly one visible section → straight to it.
    $this->actingAs($this->staff)->get(route('admin.dashboard'))->assertRedirect();
});

it('only lets super admins refresh dashboard analytics', function () {
    $this->actingAs($this->staff)->post(route('admin.refresh_analytics'))->assertForbidden();
    $this->actingAs($this->superAdmin)->post(route('admin.refresh_analytics'))->assertRedirect(route('admin.dashboard'));
});

// ─── API tokens ──────────────────────────────────────────────────────────

it('creates an API token, shows the plaintext once, and audits it', function () {
    $target = User::factory()->create();

    $this->actingAs($this->superAdmin)
        ->post(route('admin.api_tokens.store'), ['user_id' => $target->id, 'name' => 'CI token', 'abilities' => ['read']])
        ->assertRedirect(route('admin.api_tokens.index'))
        ->assertSessionHas('new_api_token');

    expect($target->tokens()->where('name', 'CI token')->exists())->toBeTrue();
    expect(Activity::where('log_name', 'admin')->where('description', 'like', 'Created API token%')->exists())->toBeTrue();
});

it('rejects API token creation with a missing user or name', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('admin.api_tokens.store'), ['name' => ''])
        ->assertSessionHasErrors(['user_id', 'name']);
});

it('revokes an API token and audits it', function () {
    $target = User::factory()->create();
    $token = $target->createToken('to-revoke')->accessToken;

    $this->actingAs($this->superAdmin)->post(route('admin.api_tokens.delete', $token->id))->assertRedirect();

    expect($target->tokens()->count())->toBe(0);
    expect(Activity::where('log_name', 'admin')->where('description', 'like', 'Revoked API token%')->exists())->toBeTrue();
});

// ─── Sessions ────────────────────────────────────────────────────────────

it('revokes a single session and all sessions for a user', function () {
    DB::table('sessions')->insert([
        ['id' => 's1', 'user_id' => $this->staff->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'a', 'payload' => '', 'last_activity' => time()],
        ['id' => 's2', 'user_id' => $this->staff->id, 'ip_address' => '1.1.1.2', 'user_agent' => 'b', 'payload' => '', 'last_activity' => time()],
        ['id' => 's3', 'user_id' => $this->staff->id, 'ip_address' => '1.1.1.3', 'user_agent' => 'c', 'payload' => '', 'last_activity' => time()],
    ]);

    $this->actingAs($this->superAdmin)->post(route('admin.sessions.delete', 's1'))->assertRedirect();
    expect(DB::table('sessions')->where('id', 's1')->exists())->toBeFalse();

    $this->actingAs($this->superAdmin)->post(route('admin.sessions.delete_all_for_user', $this->staff->id))->assertRedirect();
    expect(DB::table('sessions')->where('user_id', $this->staff->id)->count())->toBe(0);
});

// ─── Cache ───────────────────────────────────────────────────────────────

it('clears a known cache target and rejects an unknown one', function () {
    $this->actingAs($this->superAdmin)->post(route('admin.cache.clear', 'view'))->assertRedirect();
    expect(Activity::where('log_name', 'admin')->where('description', 'like', 'Cleared cache%')->exists())->toBeTrue();

    $this->actingAs($this->superAdmin)->post(route('admin.cache.clear', 'bogus'))->assertRedirect();
    expect(Activity::where('log_name', 'admin')->where('description', 'like', '%bogus%')->exists())->toBeFalse();
});

// ─── Failed jobs ─────────────────────────────────────────────────────────

it('deletes a failed job and flushes the rest', function () {
    foreach (['f1', 'f2'] as $uuid) {
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid, 'connection' => 'database', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'x', 'failed_at' => now(),
        ]);
    }

    $this->actingAs($this->superAdmin)->post(route('admin.failed_jobs.delete', 'f1'))->assertRedirect();
    expect(DB::table('failed_jobs')->count())->toBe(1);

    $this->actingAs($this->superAdmin)->post(route('admin.failed_jobs.flush'))->assertRedirect();
    expect(DB::table('failed_jobs')->count())->toBe(0);
});

it('reports a missing failed job on retry', function () {
    $this->actingAs($this->superAdmin)->post(route('admin.failed_jobs.retry', 'nope'))->assertRedirect();
    expect(Activity::where('log_name', 'admin')->where('description', 'Retried a failed job')->exists())->toBeFalse();
});

// ─── Feature flags ───────────────────────────────────────────────────────

it('rejects toggling an unknown feature flag', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('admin.features.update', 'not-a-flag'), ['action' => 'activate'])
        ->assertRedirect();

    expect(Activity::where('log_name', 'admin')->where('description', 'like', 'Feature flag%')->exists())->toBeFalse();
});

// ─── Trash ───────────────────────────────────────────────────────────────

it('restores and permanently deletes trashed tournaments', function () {
    $restorable = Tournament::factory()->create();
    $restorable->delete();
    $doomed = Tournament::factory()->create();
    $doomed->delete();

    $this->actingAs($this->superAdmin)->post(route('admin.trash.restore', ['kind' => 'tournament', 'id' => $restorable->id]))->assertRedirect();
    expect(Tournament::find($restorable->id))->not->toBeNull();

    $this->actingAs($this->superAdmin)->post(route('admin.trash.force_delete', ['kind' => 'tournament', 'id' => $doomed->id]))->assertRedirect();
    expect(Tournament::withTrashed()->find($doomed->id))->toBeNull();

    expect(Activity::where('log_name', 'admin')->where('description', 'like', 'Restored tournament%')->exists())->toBeTrue();
    expect(Activity::where('log_name', 'admin')->where('description', 'like', 'Permanently deleted tournament%')->exists())->toBeTrue();
});

it('handles restoring an unknown trash record gracefully', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('admin.trash.restore', ['kind' => 'tournament', 'id' => 99999]))
        ->assertRedirect();
});

// ─── Announcements ───────────────────────────────────────────────────────

it('creates, updates and deletes an announcement', function () {
    $payload = ['message' => 'Maintenance tonight', 'level' => 'warning', 'audience' => 'all', 'is_dismissable' => true];

    $this->actingAs($this->superAdmin)->post(route('admin.announcements.store'), $payload)->assertRedirect();
    $announcement = Announcement::where('message', 'Maintenance tonight')->firstOrFail();

    $this->actingAs($this->superAdmin)
        ->post(route('admin.announcements.update', $announcement), [...$payload, 'message' => 'Moved to Friday'])
        ->assertRedirect();
    expect($announcement->fresh()->message)->toBe('Moved to Friday');

    $this->actingAs($this->superAdmin)->delete(route('admin.announcements.delete', $announcement))->assertRedirect();
    expect(Announcement::find($announcement->id))->toBeNull();
});

it('rejects invalid announcements', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('admin.announcements.store'), [
            'message' => '',
            'level' => 'catastrophic',
            'audience' => 'everyone',
            'link_url' => 'javascript:alert(1)',
        ])
        ->assertSessionHasErrors(['message', 'level', 'audience', 'link_url']);

    $this->actingAs($this->superAdmin)
        ->post(route('admin.announcements.store'), [
            'message' => 'x', 'level' => 'info', 'audience' => 'all',
            'starts_at' => '2026-10-10', 'ends_at' => '2026-10-01',
        ])
        ->assertSessionHasErrors('ends_at');
});

// ─── Feedback ────────────────────────────────────────────────────────────

it('gates feedback management by permission and updates status', function () {
    $feedback = Feedback::create(['category' => 'general', 'message' => 'Love it', 'status' => FeedbackStatusEnum::New->value]);

    $this->actingAs($this->staff)->get(route('admin.feedback.index'))->assertForbidden();
    $this->actingAs($this->staff)->post(route('admin.feedback.update', $feedback), ['status' => 'read'])->assertForbidden();

    $manager = User::factory()->create();
    $manager->givePermissionTo([PermissionEnum::ViewFeedback->value, PermissionEnum::ManageFeedback->value]);

    $this->actingAs($manager)->get(route('admin.feedback.index'))->assertOk();
    $this->actingAs($manager)->post(route('admin.feedback.update', $feedback), ['status' => 'read', 'admin_notes' => 'Thanks'])->assertRedirect();
    expect($feedback->fresh()->status)->toBe(FeedbackStatusEnum::Read);

    $this->actingAs($manager)->post(route('admin.feedback.update', $feedback), ['status' => 'bogus'])->assertSessionHasErrors('status');

    $this->actingAs($manager)->post(route('admin.feedback.delete', $feedback))->assertRedirect();
    expect(Feedback::find($feedback->id))->toBeNull();
});

// ─── Tournament override ─────────────────────────────────────────────────

it('forces a tournament status with an audited reason and rejects unknown statuses', function () {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::Draft->value]);

    $this->actingAs($this->superAdmin)
        ->post(route('admin.tournaments.force_status', $tournament), ['status' => 'active', 'reason' => 'TO clicked start by mistake'])
        ->assertRedirect();

    expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::Active);
    $entry = Activity::where('log_name', 'admin')->where('description', 'Forced tournament status')->first();
    expect($entry->properties['from'])->toBe('draft');
    expect($entry->properties['to'])->toBe('active');
    expect($entry->properties['reason'])->toBe('TO clicked start by mistake');

    $this->actingAs($this->superAdmin)
        ->post(route('admin.tournaments.force_status', $tournament), ['status' => 'banana'])
        ->assertRedirect();
    expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::Active);
});

it('forces a round status, stamping started_at, and rejects a mismatched round', function () {
    $tournament = Tournament::factory()->create();
    $round = TournamentRound::factory()->create(['tournament_id' => $tournament->id, 'status' => TournamentRoundStatusEnum::Setup->value]);
    $other = TournamentRound::factory()->create();

    $this->actingAs($this->superAdmin)
        ->post(route('admin.tournaments.rounds.force_status', [$tournament, $round]), ['status' => 'in_progress'])
        ->assertRedirect();

    $round->refresh();
    expect($round->status)->toBe(TournamentRoundStatusEnum::InProgress);
    expect($round->started_at)->not->toBeNull();

    $this->actingAs($this->superAdmin)
        ->post(route('admin.tournaments.rounds.force_status', [$tournament, $other]), ['status' => 'completed'])
        ->assertRedirect();
    expect($other->fresh()->status)->toBe(TournamentRoundStatusEnum::Setup);
});

it('soft-deletes a tournament from the override tool', function () {
    $tournament = Tournament::factory()->create();

    $this->actingAs($this->superAdmin)->post(route('admin.tournaments.delete', $tournament))->assertRedirect(route('admin.tournaments.index'));

    expect(Tournament::find($tournament->id))->toBeNull();
    expect(Tournament::withTrashed()->find($tournament->id))->not->toBeNull();
});

it('searches tournaments in the override tool', function () {
    Tournament::factory()->create(['name' => 'Grand Needle Open']);
    Tournament::factory()->create(['name' => 'Other Event']);

    $this->actingAs($this->superAdmin)
        ->get(route('admin.tournaments.index', ['q' => 'needle']))
        ->assertInertia(fn ($page) => $page->has('tournaments.data', 1)->where('tournaments.data.0.name', 'Grand Needle Open'));
});

// ─── Transmissions ───────────────────────────────────────────────────────

it('validates transmission urls and linked slugs', function () {
    $manager = User::factory()->create();
    $manager->givePermissionTo([PermissionEnum::ViewChannel->value, PermissionEnum::EditChannel->value]);

    $this->actingAs($manager)
        ->post(route('admin.transmissions.store'), [
            'title' => 'Bad',
            'url' => 'not a url',
            'channel_id' => 999,
            'transmission_type' => 'nope',
            'content_type' => 'nope',
            'characters' => ['ghost-character'],
            'keywords' => ['ghost-keyword'],
        ])
        ->assertSessionHasErrors(['url', 'channel_id', 'transmission_type', 'content_type', 'characters.0', 'keywords.0']);
});
