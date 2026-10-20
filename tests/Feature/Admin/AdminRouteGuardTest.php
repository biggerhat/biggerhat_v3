<?php

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

/*
 * Invariant: every admin.* route is protected by more than the blanket
 * `admin.any` entry middleware — a permission:/role: gate, or (for the few
 * deliberate exceptions) an in-controller authorization check.
 */
it('has no admin route guarded only by the admin.any entry middleware', function () {
    $allowlist = [
        // Landing page: visibility of each section is filtered per-permission inside the controller.
        'admin.dashboard',
        // Palette search: each result type is permission-filtered inside the controller.
        'admin.search',
    ];

    $unguarded = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $r) => str_starts_with((string) $r->getName(), 'admin.'))
        ->reject(fn (LaravelRoute $r) => in_array($r->getName(), $allowlist, true))
        ->filter(function (LaravelRoute $r) {
            return collect($r->gatherMiddleware())
                ->filter(fn ($m) => is_string($m) && (str_starts_with($m, 'permission:') || str_starts_with($m, 'role:') || str_starts_with($m, 'role_or_permission:')))
                ->isEmpty();
        })
        ->map(fn (LaravelRoute $r) => $r->getName().' ['.implode('|', $r->methods()).'] '.$r->uri())
        ->values()
        ->all();

    expect($unguarded)->toBe([]);
});

it('uses only defined permission names in admin route middleware', function () {
    $defined = collect(\App\Enums\PermissionEnum::cases())->map->value->all();

    $unknown = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $r) => str_starts_with((string) $r->getName(), 'admin.'))
        ->flatMap(fn (LaravelRoute $r) => collect($r->gatherMiddleware())
            ->filter(fn ($m) => is_string($m) && str_starts_with($m, 'permission:'))
            ->flatMap(fn ($m) => explode('|', substr($m, strlen('permission:'))))
            ->reject(fn ($p) => in_array($p, $defined, true))
        )
        ->unique()
        ->values()
        ->all();

    expect($unknown)->toBe([]);
});

it('keeps every state-changing admin route off GET', function () {
    $writes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $r) => str_starts_with((string) $r->getName(), 'admin.'))
        ->filter(fn (LaravelRoute $r) => in_array('GET', $r->methods(), true))
        ->filter(fn (LaravelRoute $r) => preg_match('#/(store|update|delete|destroy|restore|force-delete|clear|retry|flush|scan|down|up)(/|$)#', $r->uri()) === 1)
        ->map(fn (LaravelRoute $r) => $r->getName().' '.$r->uri())
        ->values()
        ->all();

    expect($writes)->toBe([]);
});

it('rolls back everything a failed admin write already saved', function () {
    $admin = App\Models\User::factory()->create();
    $admin->givePermissionTo(Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'edit_keyword', 'guard_name' => 'web']));

    // Fail after the keyword row is created, as a later relation sync would.
    App\Models\Keyword::created(fn () => throw new RuntimeException('boom'));

    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($admin)->post(route('admin.keywords.store'), [
        'name' => 'Half Saved', 'game_mode_type' => 'standard',
    ]))->toThrow(RuntimeException::class);

    expect(App\Models\Keyword::where('name', 'Half Saved')->exists())->toBeFalse();
});
