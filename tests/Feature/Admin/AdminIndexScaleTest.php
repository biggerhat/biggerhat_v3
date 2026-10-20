<?php

use App\Enums\ContentTypeEnum;
use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Enums\TransmissionTypeEnum;
use App\Models\Channel;
use App\Models\Transmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
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
});

it('paginates and filters the users index server-side', function () {
    User::factory()->count(30)->create();
    User::factory()->create(['name' => 'Zed Findable', 'email' => 'zed.findable@example.test']);

    $this->actingAs($this->superAdmin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Index')
            ->where('users.per_page', 25)
            ->has('users.data', 25)
            ->where('users.total', 32)
        );

    $this->actingAs($this->superAdmin)
        ->get(route('admin.users.index', ['q' => 'findable']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Zed Findable')
        );

    $this->actingAs($this->superAdmin)
        ->get(route('admin.users.index', ['role' => RoleEnum::SuperAdmin->value]))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1));
});

it('does not ship sensitive user columns in the users index', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('users.data.0.password')
            ->missing('users.data.0.remember_token')
        );
});

it('hides guest sessions by default and caps the session list', function () {
    DB::table('sessions')->insert([
        ['id' => 'guest-1', 'user_id' => null, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()],
        ['id' => 'user-1', 'user_id' => $this->superAdmin->id, 'ip_address' => '2.2.2.2', 'user_agent' => 'y', 'payload' => '', 'last_activity' => time()],
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('admin.sessions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('sessions', 1)
            ->where('total', 1)
            ->where('show_guests', false)
        );

    $this->actingAs($this->superAdmin)
        ->get(route('admin.sessions.index', ['guests' => 1]))
        ->assertInertia(fn (Assert $page) => $page->has('sessions', 2)->where('show_guests', true));
});

it('trims failed job rows to what the page needs', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => 'abc-123',
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\Example', 'data' => str_repeat('x', 5000)]),
        'exception' => 'RuntimeException: boom'."\n".str_repeat('#0 trace line'."\n", 800),
        'failed_at' => now(),
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('admin.failed_jobs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('jobs.data.0.job_name', 'App\\Jobs\\Example')
            ->missing('jobs.data.0.payload')
            ->where('jobs.data.0.exception', fn ($e) => strlen($e) < 4100)
        );
});

it('paginates and searches transmissions server-side', function () {
    $channel = Channel::create(['name' => 'Test Channel', 'slug' => 'test-channel']);
    $make = fn (string $title, int $i) => Transmission::create([
        'channel_id' => $channel->id,
        'title' => $title,
        'slug' => 'tx-'.$i,
        'url' => 'https://example.test/'.$i,
        'transmission_type' => TransmissionTypeEnum::cases()[0]->value,
        'content_type' => ContentTypeEnum::cases()[0]->value,
    ]);
    foreach (range(1, 30) as $i) {
        $make('Episode '.$i, $i);
    }
    $make('Needle In Haystack', 99);

    $this->actingAs($this->superAdmin)
        ->get(route('admin.transmissions.index'))
        ->assertInertia(fn (Assert $page) => $page->has('transmissions.data', 25)->where('transmissions.total', 31));

    $this->actingAs($this->superAdmin)
        ->get(route('admin.transmissions.index', ['q' => 'needle']))
        ->assertInertia(fn (Assert $page) => $page->has('transmissions.data', 1));
});

it('shows dashboard counts from cache and a failed jobs tile to super admins', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => 'dash-1', 'connection' => 'database', 'queue' => 'default',
        'payload' => '{}', 'exception' => 'x', 'failed_at' => now(),
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.failed_jobs', 1));
});

it('sends the full route table to admins but trims admin routes for guests', function () {
    $guest = $this->get(route('index'));
    $guest->assertOk();
    $guestRoutes = array_keys($guest->viewData('page')['props']['ziggy']['routes']);
    expect(collect($guestRoutes)->contains(fn ($n) => str_starts_with($n, 'admin.')))->toBeFalse();
    expect($guestRoutes)->toContain('index');

    $admin = $this->actingAs($this->superAdmin)->get(route('index'));
    $adminRoutes = array_keys($admin->viewData('page')['props']['ziggy']['routes']);
    expect(collect($adminRoutes)->contains(fn ($n) => str_starts_with($n, 'admin.')))->toBeTrue();
});
