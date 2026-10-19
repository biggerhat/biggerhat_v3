<?php

use App\Enums\GameModeTypeEnum;
use App\Enums\LoreMediaTypeEnum;
use App\Enums\PodSourceEnum;
use App\Enums\PoolSeasonEnum;
use App\Enums\SculptVersionEnum;
use App\Models\Action;
use App\Models\Blueprint;
use App\Models\Channel;
use App\Models\Character;
use App\Models\Lore;
use App\Models\LoreMedia;
use App\Models\Miniature;
use App\Models\Package;
use App\Models\PodLink;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\TOS\Unit;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/*
 * Permission-gate + happy-path + validation matrix for the remaining Malifaux
 * catalog controllers that were converted to FormRequests (Upgrade and Crew
 * have dedicated suites).
 *
 * Each row: route prefix, permission suffix, model, a factory-less creator,
 * the column holding the name, and a valid payload.
 */

dataset('remaining catalogs', function () {
    return [
        'schemes' => ['schemes', 'scheme', Scheme::class, fn () => Scheme::factory()->create(['name' => 'Old Name']), 'name', ['name' => 'New Thing', 'season' => PoolSeasonEnum::defaultSeason()->value]],
        'strategies' => ['strategies', 'strategy', Strategy::class, fn () => Strategy::factory()->create(['name' => 'Old Name']), 'name', ['name' => 'New Thing', 'season' => PoolSeasonEnum::defaultSeason()->value]],
        'actions' => ['actions', 'action', Action::class, fn () => Action::factory()->create(['name' => 'Old Name']), 'name', [
            'name' => 'New Thing', 'game_mode_type' => GameModeTypeEnum::Standard->value, 'type' => 'attack', 'is_signature' => false, 'stone_cost' => 0,
        ]],
        'blueprints' => ['blueprints', 'blueprint', Blueprint::class, fn () => Blueprint::create(['name' => 'Old Name', 'sculpt_version' => SculptVersionEnum::cases()[0]->value]), 'name', [
            'name' => 'New Thing', 'sculpt_version' => SculptVersionEnum::cases()[0]->value,
        ]],
        'pod_links' => ['pod_links', 'pod_link', PodLink::class, fn () => PodLink::create(['name' => 'Old Name', 'source' => PodSourceEnum::cases()[0]->value, 'url' => 'https://example.com/a']), 'name', [
            'name' => 'New Thing', 'source' => PodSourceEnum::cases()[0]->value, 'url' => 'https://example.com/b',
        ]],
        'lore_media' => ['lore_media', 'lore', LoreMedia::class, fn () => LoreMedia::factory()->create(['name' => 'Old Name']), 'name', [
            'name' => 'New Thing', 'type' => LoreMediaTypeEnum::cases()[0]->value,
        ]],
        'lores' => ['lores', 'lore', Lore::class, fn () => Lore::factory()->create(['name' => 'Old Name']), 'name', ['name' => 'New Thing']],
        'channels' => ['channels', 'channel', Channel::class, fn () => Channel::create(['name' => 'Old Name']), 'name', ['name' => 'New Thing']],
        'packages' => ['packages', 'package', Package::class, fn () => Package::factory()->create(['name' => 'Old Name']), 'name', ['name' => 'New Thing']],
    ];
});

/** Lore routes bind by `id` explicitly; the rest use the model's route key. */
function catalogKey(string $prefix, $record): mixed
{
    return in_array($prefix, ['lore_media', 'lores'], true) ? $record->id : $record->getRouteKey();
}

function remainingUser(string $suffix, array $verbs): User
{
    $user = User::factory()->create();
    foreach ($verbs as $verb) {
        Permission::firstOrCreate(['name' => "{$verb}_{$suffix}", 'guard_name' => 'web']);
        $user->givePermissionTo("{$verb}_{$suffix}");
    }

    return $user;
}

it('denies every action without permission', function (string $prefix, string $perm, string $model, Closure $make, string $col, array $payload) {
    $record = $make();
    $nobody = User::factory()->create();

    $this->actingAs($nobody)->get(route("admin.{$prefix}.index"))->assertForbidden();
    $this->actingAs($nobody)->post(route("admin.{$prefix}.store"), $payload)->assertForbidden();
    $this->actingAs($nobody)->post(route("admin.{$prefix}.update", catalogKey($prefix, $record)), $payload)->assertForbidden();
    $this->actingAs($nobody)->post(route("admin.{$prefix}.delete", catalogKey($prefix, $record)))->assertForbidden();
})->with('remaining catalogs');

it('lets a view-only user browse but not write', function (string $prefix, string $perm, string $model, Closure $make, string $col, array $payload) {
    $record = $make();
    $viewer = remainingUser($perm, ['view']);

    $this->actingAs($viewer)->get(route("admin.{$prefix}.index"))->assertOk();
    $this->actingAs($viewer)->post(route("admin.{$prefix}.store"), $payload)->assertForbidden();
    $this->actingAs($viewer)->post(route("admin.{$prefix}.update", catalogKey($prefix, $record)), $payload)->assertForbidden();
    $this->actingAs($viewer)->post(route("admin.{$prefix}.delete", catalogKey($prefix, $record)))->assertForbidden();
})->with('remaining catalogs');

it('creates a record with edit permission', function (string $prefix, string $perm, string $model, Closure $make, string $col, array $payload) {
    $editor = remainingUser($perm, ['view', 'edit']);

    $this->actingAs($editor)
        ->post(route("admin.{$prefix}.store"), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route("admin.{$prefix}.index"));

    expect($model::where($col, 'New Thing')->exists())->toBeTrue();
})->with('remaining catalogs');

it('rejects a create with no name', function (string $prefix, string $perm, string $model, Closure $make, string $col, array $payload) {
    $editor = remainingUser($perm, ['view', 'edit']);
    unset($payload['name']);

    $this->actingAs($editor)
        ->post(route("admin.{$prefix}.store"), $payload)
        ->assertSessionHasErrors('name');

    expect($model::count())->toBe(0);
})->with('remaining catalogs');

it('updates a record with edit permission', function (string $prefix, string $perm, string $model, Closure $make, string $col, array $payload) {
    $editor = remainingUser($perm, ['view', 'edit']);
    $record = $make();

    $this->actingAs($editor)
        ->post(route("admin.{$prefix}.update", catalogKey($prefix, $record)), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route("admin.{$prefix}.index"));

    expect($record->fresh()->{$col})->toBe('New Thing');
})->with('remaining catalogs');

it('rejects an update with no name', function (string $prefix, string $perm, string $model, Closure $make, string $col, array $payload) {
    $editor = remainingUser($perm, ['view', 'edit']);
    $record = $make();
    unset($payload['name']);

    $this->actingAs($editor)
        ->post(route("admin.{$prefix}.update", catalogKey($prefix, $record)), $payload)
        ->assertSessionHasErrors('name');

    expect($record->fresh()->{$col})->toBe('Old Name');
})->with('remaining catalogs');

it('deletes only with delete permission', function (string $prefix, string $perm, string $model, Closure $make, string $col, array $payload) {
    $record = $make();
    $editor = remainingUser($perm, ['view', 'edit']);

    $this->actingAs($editor)->post(route("admin.{$prefix}.delete", catalogKey($prefix, $record)))->assertForbidden();
    expect($model::find($record->id))->not->toBeNull();

    $deleter = remainingUser($perm, ['view', 'delete']);
    $this->actingAs($deleter)
        ->post(route("admin.{$prefix}.delete", catalogKey($prefix, $record)))
        ->assertRedirect(route("admin.{$prefix}.index"));

    expect($model::find($record->id))->toBeNull();
})->with('remaining catalogs');

// ─── Per-resource validation ───

dataset('invalid payloads', function () {
    $season = PoolSeasonEnum::defaultSeason()->value;

    return [
        'scheme bad season' => ['schemes', 'scheme', ['name' => 'X', 'season' => 'nope'], 'season'],
        'scheme unknown next scheme' => ['schemes', 'scheme', ['name' => 'X', 'season' => $season, 'next_scheme_one_id' => 999999], 'next_scheme_one_id'],
        'scheme bad requirement type' => ['schemes', 'scheme', ['name' => 'X', 'season' => $season, 'requirements' => [['type' => 'bogus']]], 'requirements.0.type'],
        'strategy bad season' => ['strategies', 'strategy', ['name' => 'X', 'season' => 'nope'], 'season'],
        'strategy unknown token' => ['strategies', 'strategy', ['name' => 'X', 'season' => $season, 'token_ids' => [999999]], 'token_ids.0'],
        'action bad type' => ['actions', 'action', ['name' => 'X', 'game_mode_type' => 'standard', 'type' => 'bogus', 'is_signature' => false, 'stone_cost' => 0], 'type'],
        'action stone cost too high' => ['actions', 'action', ['name' => 'X', 'game_mode_type' => 'standard', 'type' => 'attack', 'is_signature' => false, 'stone_cost' => 11], 'stone_cost'],
        'action unknown trigger' => ['actions', 'action', ['name' => 'X', 'game_mode_type' => 'standard', 'type' => 'attack', 'is_signature' => false, 'stone_cost' => 0, 'triggers' => ['Nope']], 'triggers.0'],
        'blueprint bad sculpt' => ['blueprints', 'blueprint', ['name' => 'X', 'sculpt_version' => 'nope'], 'sculpt_version'],
        'blueprint unknown character' => ['blueprints', 'blueprint', ['name' => 'X', 'sculpt_version' => SculptVersionEnum::cases()[0]->value, 'characters' => ['Nobody']], 'characters.0'],
        'pod link not a url' => ['pod_links', 'pod_link', ['name' => 'X', 'source' => PodSourceEnum::cases()[0]->value, 'url' => 'nope'], 'url'],
        'pod link unknown miniature' => ['pod_links', 'pod_link', ['name' => 'X', 'source' => PodSourceEnum::cases()[0]->value, 'url' => 'https://example.com', 'miniatures' => [999999]], 'miniatures.0'],
        'lore media bad type' => ['lore_media', 'lore', ['name' => 'X', 'type' => 'nope'], 'type'],
        'lore unknown character' => ['lores', 'lore', ['name' => 'X', 'characters' => ['Nobody']], 'characters.0'],
        'lore unknown tos unit' => ['lores', 'lore', ['name' => 'X', 'tos_units' => ['nope']], 'tos_units.0'],
        'lore new media needs type' => ['lores', 'lore', ['name' => 'X', 'new_media' => [['name' => 'M']]], 'new_media.0.type'],
        'channel unknown user' => ['channels', 'channel', ['name' => 'X', 'user_ids' => [999999]], 'user_ids.0'],
        'package bad game system' => ['packages', 'package', ['name' => 'X', 'game_system' => 'nope'], 'game_system'],
        'package unknown tos unit' => ['packages', 'package', ['name' => 'X', 'tos_units' => ['nope']], 'tos_units.0'],
        'package unknown keyword' => ['packages', 'package', ['name' => 'X', 'keywords' => ['Nope']], 'keywords.0'],
    ];
});

it('rejects invalid payloads', function (string $prefix, string $perm, array $payload, string $errorKey) {
    $editor = remainingUser($perm, ['view', 'edit']);

    $this->actingAs($editor)
        ->post(route("admin.{$prefix}.store"), $payload)
        ->assertSessionHasErrors($errorKey);
})->with('invalid payloads');

// ─── Relation syncing + regressions ───

it('stores an action with omitted trigger and character lists', function () {
    $editor = remainingUser('action', ['view', 'edit']);

    $this->actingAs($editor)->post(route('admin.actions.store'), [
        'name' => 'Bare Action', 'game_mode_type' => 'standard', 'type' => 'attack', 'is_signature' => false, 'stone_cost' => 1,
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.actions.index'));

    expect(Action::where('name', 'Bare Action')->exists())->toBeTrue();
});

it('creates a miniature from a character and rejects an unknown character', function () {
    $editor = remainingUser('miniature', ['view', 'edit']);
    $character = Character::factory()->create();
    $payload = ['character_id' => $character->id, 'version' => SculptVersionEnum::cases()[0]->value];

    $this->actingAs($editor)->post(route('admin.miniatures.store'), $payload)
        ->assertSessionHasNoErrors()->assertRedirect(route('admin.miniatures.index'));
    expect(Miniature::where('character_id', $character->id)->count())->toBe(1);

    $this->actingAs($editor)->post(route('admin.miniatures.store'), [...$payload, 'character_id' => 999999])
        ->assertSessionHasErrors('character_id');
    $this->actingAs($editor)->post(route('admin.miniatures.store'), ['version' => $payload['version']])
        ->assertSessionHasErrors('character_id');
});

it('denies miniature writes without permission', function () {
    $character = Character::factory()->create();
    $miniature = Miniature::factory()->create(['character_id' => $character->id]);
    $nobody = User::factory()->create();
    $payload = ['character_id' => $character->id, 'version' => SculptVersionEnum::cases()[0]->value];

    $this->actingAs($nobody)->get(route('admin.miniatures.index'))->assertForbidden();
    $this->actingAs($nobody)->post(route('admin.miniatures.store'), $payload)->assertForbidden();
    $this->actingAs($nobody)->post(route('admin.miniatures.update', $miniature->getRouteKey()), $payload)->assertForbidden();
    $this->actingAs($nobody)->post(route('admin.miniatures.delete', $miniature->getRouteKey()))->assertForbidden();
});

it('links TOS units to a package by slug, not by shared name', function () {
    $editor = remainingUser('package', ['view', 'edit']);
    $first = Unit::factory()->withSides()->create(['name' => 'Twin', 'title' => 'One']);
    Unit::factory()->withSides()->create(['name' => 'Twin', 'title' => 'Two']);

    $this->actingAs($editor)->post(route('admin.packages.store'), [
        'name' => 'Twin Box', 'game_system' => 'tos', 'tos_units' => [$first->slug],
    ])->assertSessionHasNoErrors();

    $package = Package::where('name', 'Twin Box')->firstOrFail();
    expect($package->tosUnits->pluck('id')->all())->toBe([$first->id]);
});

it('syncs a pod link\'s miniatures', function () {
    $editor = remainingUser('pod_link', ['view', 'edit']);
    $miniature = Miniature::factory()->create(['character_id' => Character::factory()->create()->id]);

    $this->actingAs($editor)->post(route('admin.pod_links.store'), [
        'name' => 'Linked', 'source' => PodSourceEnum::cases()[0]->value, 'url' => 'https://example.com/x', 'miniatures' => [$miniature->id],
    ])->assertSessionHasNoErrors();

    expect(PodLink::where('name', 'Linked')->firstOrFail()->miniatures->pluck('id')->all())->toBe([$miniature->id]);
});

it('creates a lore entry with inline new media', function () {
    $editor = remainingUser('lore', ['view', 'edit']);

    $this->actingAs($editor)->post(route('admin.lores.store'), [
        'name' => 'Tale', 'new_media' => [['name' => 'Novel', 'type' => LoreMediaTypeEnum::cases()[0]->value]],
    ])->assertSessionHasNoErrors();

    expect(Lore::where('name', 'Tale')->firstOrFail()->media->pluck('name')->all())->toBe(['Novel']);
});
