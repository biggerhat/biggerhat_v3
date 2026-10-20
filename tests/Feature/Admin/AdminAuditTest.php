<?php

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Lab404\Impersonate\Events\LeaveImpersonation;
use Lab404\Impersonate\Events\TakeImpersonation;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (RoleEnum::cases() as $role) {
        Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
    }

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole(RoleEnum::SuperAdmin->value);
});

it('logs impersonation start and stop', function () {
    $target = User::factory()->create(['name' => 'Target Person']);

    event(new TakeImpersonation($this->superAdmin, $target));
    event(new LeaveImpersonation($this->superAdmin, $target));

    $entries = Activity::where('log_name', 'admin')->where('event', 'impersonation')->orderBy('id')->get();
    expect($entries)->toHaveCount(2);
    expect($entries[0]->description)->toBe('Started impersonating Target Person');
    expect($entries[1]->description)->toBe('Stopped impersonating Target Person');
    expect($entries[0]->causer_id)->toBe($this->superAdmin->id);
});

it('audits cache clears', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('admin.cache.clear', 'view'))
        ->assertRedirect();

    expect(Activity::where('log_name', 'admin')->where('description', 'like', 'Cleared cache:%')->exists())->toBeTrue();
});

it('validates and audits feature flag changes', function () {
    $flag = array_key_first(App\Providers\FeatureFlagsServiceProvider::FLAGS);

    $this->actingAs($this->superAdmin)
        ->post(route('admin.features.update', $flag), ['action' => 'bogus'])
        ->assertSessionHasErrors('action');

    $this->actingAs($this->superAdmin)
        ->post(route('admin.features.update', $flag), ['action' => 'activate'])
        ->assertRedirect();

    expect(Activity::where('log_name', 'admin')->where('description', "Feature flag {$flag}: activate")->exists())->toBeTrue();
});

it('audits session revocation and failed-job flushes', function () {
    $this->actingAs($this->superAdmin)->post(route('admin.sessions.delete_all_for_user', 999))->assertRedirect();
    $this->actingAs($this->superAdmin)->post(route('admin.failed_jobs.flush'))->assertRedirect();

    expect(Activity::where('log_name', 'admin')->where('description', 'Revoked all sessions for a user')->exists())->toBeTrue();
    expect(Activity::where('log_name', 'admin')->where('description', 'Flushed all failed jobs')->exists())->toBeTrue();
});

it('does not hold a failed analytics fetch for the full ttl', function () {
    config(['analytics.property_id' => null]);

    $this->actingAs($this->superAdmin)->get(route('admin.dashboard'))->assertOk();

    $cached = Cache::get('admin:dashboard:analytics:v1');
    expect($cached['error'])->not->toBeNull();
});
