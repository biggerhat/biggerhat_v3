<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\User;
use App\Support\AdminNav;
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

function adminWith(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

// ─── Shared nav source ───

it('only offers nav items the user may access', function () {
    $user = adminWith('view_keyword', 'view_character');
    $titles = collect(AdminNav::forUser($user))->flatMap(fn ($g) => collect($g['items'])->pluck('title'))->all();

    expect($titles)->toContain('Keywords', 'Characters')
        ->not->toContain('Users', 'Logs', 'Trash');
});

it('gives super admins every item and drops empty groups for others', function () {
    $all = collect(AdminNav::definition())->sum(fn ($g) => count($g['items']));
    $super = collect(AdminNav::forUser($this->superAdmin))->sum(fn ($g) => count($g['items']));
    expect($super)->toBe($all);

    expect(collect(AdminNav::forUser(adminWith('view_keyword')))->pluck('title')->all())->toBe(['Game Data']);
});

it('treats a pipe-separated permission as any-of', function () {
    expect(AdminNav::allows(adminWith('edit_posts'), 'create_posts|edit_posts'))->toBeTrue()
        ->and(AdminNav::allows(adminWith('view_keyword'), 'create_posts|edit_posts'))->toBeFalse()
        ->and(AdminNav::allows(adminWith('view_keyword'), 'super_admin'))->toBeFalse()
        ->and(AdminNav::allows(null, 'view_keyword'))->toBeFalse();
});

it('uses unique item keys and only permissions that exist', function () {
    $items = collect(AdminNav::definition())->flatMap(fn ($g) => $g['items']);
    $valid = collect(PermissionEnum::cases())->map->value->push('super_admin');

    expect($items->pluck('key')->duplicates()->all())->toBe([]);
    foreach ($items as $item) {
        foreach (explode('|', $item['permission']) as $permission) {
            expect($valid->contains($permission))->toBeTrue("{$item['key']} uses unknown permission {$permission}");
        }
    }
});

it('shares the nav on admin pages only', function () {
    $this->actingAs($this->superAdmin)->get(route('admin.keywords.index'))
        ->assertInertia(fn ($page) => $page->has('adminNav')->where('adminNav.0.title', 'Game Data'));

    $this->actingAs($this->superAdmin)->get(route('index'))
        ->assertInertia(fn ($page) => $page->where('adminNav', null));
});

it('renders dashboard tiles from the shared nav', function () {
    Keyword::factory()->count(2)->create();

    $this->actingAs($this->superAdmin)->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('groups.0.title', 'Game Data')
            ->where('groups.0.items', fn ($items) => collect($items)->contains(fn ($i) => $i['label'] === 'Keywords' && $i['count'] === 2)));
});

// ─── Record search ───

it('requires a signed-in admin to search', function () {
    $this->getJson(route('admin.search', ['q' => 'ab']))->assertUnauthorized();
    $this->actingAs(User::factory()->create())->getJson(route('admin.search', ['q' => 'ab']))->assertForbidden();
});

it('ignores queries shorter than two characters', function () {
    Character::factory()->create(['name' => 'Zed', 'title' => null]);

    $this->actingAs($this->superAdmin)->getJson(route('admin.search', ['q' => 'z']))
        ->assertOk()->assertExactJson(['results' => []]);
});

it('finds records with working edit links', function () {
    $character = Character::factory()->create(['name' => 'Zebulon Quux', 'title' => null]);
    Keyword::factory()->create(['name' => 'Zebra Stripe']);

    $response = $this->actingAs($this->superAdmin)->getJson(route('admin.search', ['q' => 'zeb']))->assertOk();
    $results = collect($response->json('results'));

    expect($results->pluck('type')->all())->toContain('Character', 'Keyword');

    $hit = $results->firstWhere('type', 'Character');
    expect($hit['label'])->toBe($character->fresh()->display_name);
    $this->actingAs($this->superAdmin)->get($hit['url'])->assertOk();
    expect($hit['url'])->toBe(route('admin.characters.edit', $character));
});

it('only returns record types the user may open', function () {
    Character::factory()->create(['name' => 'Zebulon Quux', 'title' => null]);
    Keyword::factory()->create(['name' => 'Zebra Stripe']);

    $types = collect($this->actingAs(adminWith('view_keyword'))->getJson(route('admin.search', ['q' => 'zeb']))->json('results'))
        ->pluck('type')->all();

    expect($types)->toBe(['Keyword']);
});

it('matches LIKE wildcards literally', function () {
    Keyword::factory()->create(['name' => '100% Real']);
    Keyword::factory()->create(['name' => 'Fully Fake']);

    $labels = collect($this->actingAs($this->superAdmin)->getJson(route('admin.search', ['q' => '0%']))->json('results'))
        ->pluck('label')->all();

    expect($labels)->toBe(['100% Real']);
});

it('searches every source without a query error', function () {
    // An empty database still compiles each source's query, catching bad columns/scopes/routes.
    $this->actingAs($this->superAdmin)->getJson(route('admin.search', ['q' => 'zz']))
        ->assertOk()->assertJsonStructure(['results']);
});
