<?php

use App\Enums\BaseSizeEnum;
use App\Enums\GameModeTypeEnum;
use App\Models\Ability;
use App\Models\Characteristic;
use App\Models\Keyword;
use App\Models\Marker;
use App\Models\Token;
use App\Models\Trigger;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/*
 * Permission-gate + happy-path + validation matrix for the uniform Malifaux
 * catalog CRUD controllers (view_*, edit_*, delete_* per resource).
 */

dataset('catalogs', function () {
    return [
        'keywords' => ['keywords', 'keyword', Keyword::class, ['name' => 'New Thing', 'game_mode_type' => GameModeTypeEnum::Standard->value]],
        'characteristics' => ['characteristics', 'characteristic', Characteristic::class, ['name' => 'New Thing']],
        'markers' => ['markers', 'marker', Marker::class, ['name' => 'New Thing', 'base' => BaseSizeEnum::ThirtyMM->value]],
        'tokens' => ['tokens', 'token', Token::class, ['name' => 'New Thing', 'is_general' => false]],
        'abilities' => ['abilities', 'ability', Ability::class, ['name' => 'New Thing', 'game_mode_type' => GameModeTypeEnum::Standard->value, 'costs_stone' => false]],
        'triggers' => ['triggers', 'trigger', Trigger::class, ['name' => 'New Thing', 'stone_cost' => 0]],
    ];
});

function catalogUser(string $permissionSuffix, array $verbs): User
{
    $user = User::factory()->create();
    foreach ($verbs as $verb) {
        Permission::firstOrCreate(['name' => "{$verb}_{$permissionSuffix}", 'guard_name' => 'web']);
        $user->givePermissionTo("{$verb}_{$permissionSuffix}");
    }

    return $user;
}

it('denies every write action without permission', function (string $prefix, string $perm, string $model, array $payload) {
    $record = $model::factory()->create();
    $nobody = User::factory()->create();

    $this->actingAs($nobody)->get(route("admin.{$prefix}.index"))->assertForbidden();
    $this->actingAs($nobody)->get(route("admin.{$prefix}.create"))->assertForbidden();
    $this->actingAs($nobody)->post(route("admin.{$prefix}.store"), $payload)->assertForbidden();
    $this->actingAs($nobody)->post(route("admin.{$prefix}.update", $record->getRouteKey()), $payload)->assertForbidden();
    $this->actingAs($nobody)->post(route("admin.{$prefix}.delete", $record->getRouteKey()))->assertForbidden();
})->with('catalogs');

it('lets a view-only user browse but not write', function (string $prefix, string $perm, string $model, array $payload) {
    $record = $model::factory()->create();
    $viewer = catalogUser($perm, ['view']);

    $this->actingAs($viewer)->get(route("admin.{$prefix}.index"))->assertOk();
    $this->actingAs($viewer)->post(route("admin.{$prefix}.store"), $payload)->assertForbidden();
    $this->actingAs($viewer)->post(route("admin.{$prefix}.update", $record->getRouteKey()), $payload)->assertForbidden();
    $this->actingAs($viewer)->post(route("admin.{$prefix}.delete", $record->getRouteKey()))->assertForbidden();
})->with('catalogs');

it('creates a record with edit permission', function (string $prefix, string $perm, string $model, array $payload) {
    $editor = catalogUser($perm, ['view', 'edit']);

    $this->actingAs($editor)
        ->post(route("admin.{$prefix}.store"), $payload)
        ->assertRedirect(route("admin.{$prefix}.index"));

    expect($model::where('name', 'New Thing')->exists())->toBeTrue();
})->with('catalogs');

it('rejects a create with no name', function (string $prefix, string $perm, string $model, array $payload) {
    $editor = catalogUser($perm, ['view', 'edit']);
    unset($payload['name']);

    $this->actingAs($editor)
        ->post(route("admin.{$prefix}.store"), $payload)
        ->assertSessionHasErrors('name');

    expect($model::count())->toBe(0);
})->with('catalogs');

it('updates a record with edit permission', function (string $prefix, string $perm, string $model, array $payload) {
    $editor = catalogUser($perm, ['view', 'edit']);
    $record = $model::factory()->create(['name' => 'Old Name']);

    $this->actingAs($editor)
        ->post(route("admin.{$prefix}.update", $record->getRouteKey()), $payload)
        ->assertRedirect(route("admin.{$prefix}.index"));

    expect($record->fresh()->name)->toBe('New Thing');
})->with('catalogs');

it('rejects an update with no name', function (string $prefix, string $perm, string $model, array $payload) {
    $editor = catalogUser($perm, ['view', 'edit']);
    $record = $model::factory()->create(['name' => 'Keep Me']);
    unset($payload['name']);

    $this->actingAs($editor)
        ->post(route("admin.{$prefix}.update", $record->getRouteKey()), $payload)
        ->assertSessionHasErrors('name');

    expect($record->fresh()->name)->toBe('Keep Me');
})->with('catalogs');

it('deletes only with delete permission', function (string $prefix, string $perm, string $model, array $payload) {
    $record = $model::factory()->create();
    $editor = catalogUser($perm, ['view', 'edit']);

    $this->actingAs($editor)->post(route("admin.{$prefix}.delete", $record->getRouteKey()))->assertForbidden();
    expect($model::find($record->id))->not->toBeNull();

    $deleter = catalogUser($perm, ['view', 'delete']);
    $this->actingAs($deleter)
        ->post(route("admin.{$prefix}.delete", $record->getRouteKey()))
        ->assertRedirect(route("admin.{$prefix}.index"));

    expect($model::find($record->id))->toBeNull();
})->with('catalogs');
