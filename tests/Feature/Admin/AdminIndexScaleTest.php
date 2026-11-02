<?php

use App\Enums\ContentTypeEnum;
use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Enums\TransmissionTypeEnum;
use App\Enums\UpgradeDomainTypeEnum;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Ability;
use App\Models\Action;
use App\Models\BotHeartbeat;
use App\Models\Channel;
use App\Models\Character;
use App\Models\Miniature;
use App\Models\Transmission;
use App\Models\Trigger;
use App\Models\Upgrade;
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

it('lists failed jobs and an offline bot under needs attention', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => 'dash-2', 'connection' => 'database', 'queue' => 'default',
        'payload' => '{}', 'exception' => 'x', 'failed_at' => now(),
    ]);
    BotHeartbeat::factory()->create(['reported_at' => now()->subHour()]);

    $this->actingAs($this->superAdmin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('attention', 2)
            ->where('attention.0.key', 'bot_offline')
            ->where('attention.1.key', 'failed_jobs'));
});

it('leaves the route table out of Inertia navigations but keeps it on the first load', function () {
    $first = $this->actingAs($this->superAdmin)->get(route('admin.dashboard'));
    expect($first->viewData('page')['props']['ziggy'])->toHaveKeys(['routes', 'location']);

    $version = app(HandleInertiaRequests::class)->version(request());
    $visit = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version])
        ->get(route('admin.dashboard'));

    $visit->assertOk();
    expect($visit->json('props.ziggy'))->toHaveKey('location')->not->toHaveKey('routes');
});

it('does not serialize the permission catalogue into the shared auth user', function () {
    $response = $this->actingAs($this->superAdmin)->get(route('admin.dashboard'));

    $user = $response->viewData('page')['props']['auth']['user'];
    expect($user)->not->toHaveKeys(['roles', 'permissions']);
    // The names the client actually reads still ship once, under auth.permissions.
    expect($response->viewData('page')['props']['auth']['permissions'])->not->toBeEmpty();
});

it('ships only the columns the admin index tables render', function () {
    $character = Character::factory()->create();
    Miniature::factory()->for($character)->create();
    Action::factory()->create();

    $this->actingAs($this->superAdmin)
        ->get(route('admin.actions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('actions.0', fn (Assert $row) => $row
                ->hasAll(['id', 'name', 'slug', 'type', 'is_signature', 'game_mode_type', 'internal_notes'])
                ->etc())
            ->missing('actions.0.description'));

    $this->actingAs($this->superAdmin)
        ->get(route('admin.miniatures.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('miniatures.0', fn (Assert $row) => $row
                ->hasAll(['id', 'display_name', 'slug', 'character_name', 'front_image', 'back_image', 'version'])
                ->missing('character'))
        );
});

it('renders the crew upgrades index with its trimmed character relation', function () {
    $upgrade = Upgrade::factory()->create(['domain' => UpgradeDomainTypeEnum::Crew]);
    $upgrade->characters()->attach(Character::factory()->create());

    $this->actingAs($this->superAdmin)
        ->get(route('admin.crews.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('upgrades.0.characters.0', fn (Assert $character) => $character->hasAll(['id', 'display_name'])->etc()));
});

it('still renders every trimmed catalog index', function (string $route, string $prop, string $model) {
    $model::factory()->create();

    $this->actingAs($this->superAdmin)
        ->get(route($route))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has($prop, 1));
})->with([
    'abilities' => ['admin.abilities.index', 'abilities', Ability::class],
    'triggers' => ['admin.triggers.index', 'triggers', Trigger::class],
    'characters' => ['admin.characters.index', 'characters', Character::class],
]);
