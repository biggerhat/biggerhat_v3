<?php

use App\Enums\ModelStageEnum;
use App\Models\Character;
use App\Models\CollectionModel;
use App\Models\Miniature;
use App\Models\User;
use App\Support\Collection\CollectionModels;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->character = Character::factory()->create();
    $this->mini = Miniature::factory()->create(['character_id' => $this->character->id]);
    $this->mini2 = Miniature::factory()->create(['character_id' => $this->character->id]);
});

function stagesOf(User $user, Miniature $mini): array
{
    return CollectionModel::where('user_id', $user->id)->where('miniature_id', $mini->id)->orderBy('id')->get()->map(fn ($m) => $m->stage->value)->all();
}

// ─── Existing collections are kept ───

it('turns existing collection rows into models without losing their built and painted state', function () {
    DB::table('user_miniatures')->insert([
        ['user_id' => $this->user->id, 'miniature_id' => $this->mini->id, 'quantity' => 3, 'is_built' => true, 'is_painted' => true, 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $this->user->id, 'miniature_id' => $this->mini2->id, 'quantity' => 2, 'is_built' => true, 'is_painted' => false, 'created_at' => now(), 'updated_at' => now()],
    ]);

    (require base_path('database/migrations/2026_11_24_100001_backfill_collection_models.php'))->up();

    expect(stagesOf($this->user, $this->mini))->toBe(['painted', 'painted', 'painted']);
    expect(stagesOf($this->user, $this->mini2))->toBe(['built', 'built']);
    expect(DB::table('user_miniatures')->where('user_id', $this->user->id)->count())->toBe(2);

    // Running it again changes nothing.
    (require base_path('database/migrations/2026_11_24_100001_backfill_collection_models.php'))->up();
    expect(CollectionModel::where('user_id', $this->user->id)->count())->toBe(5);
});

it('repairs models for a collection row written some other way', function () {
    $this->user->collectionMiniatures()->attach($this->mini->id, ['quantity' => 2, 'is_built' => true]);

    $this->actingAs($this->user)->get(route('collection.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('collection.0.models', 2)->where('collection.0.models.0.stage', 'built'));
});

// ─── One model at a time ───

it('shows each model of a miniature with its own stage', function () {
    CollectionModels::setQuantity($this->user, $this->mini->id, 3);
    $models = CollectionModel::where('user_id', $this->user->id)->orderBy('id')->get();

    $this->actingAs($this->user)->putJson(route('collection.models.update', $models[0]), ['stage' => 'based', 'note' => ' Battle-worn '])->assertRedirect();
    $this->actingAs($this->user)->putJson(route('collection.models.update', $models[1]), ['stage' => 'built'])->assertRedirect();

    expect(stagesOf($this->user, $this->mini))->toBe(['based', 'built', 'unbuilt']);
    expect($models[0]->fresh()->note)->toBe('Battle-worn');

    $this->actingAs($this->user)->get(route('collection.index'))->assertInertia(fn ($page) => $page
        ->where('collection.0.quantity', 3)
        ->where('collection.0.built_count', 2)
        ->where('collection.0.painted_count', 1)
        ->where('collection.0.is_built', false)
        ->where('collection.0.is_painted', false)
        ->where('collection.0.models.0.note', 'Battle-worn')
        ->where('totals.owned_miniatures', 3)
        ->where('totals.built', 2)
        ->where('totals.painted', 1));
});

it('keeps the miniature row in step: quantity and the built and painted flags', function () {
    CollectionModels::setQuantity($this->user, $this->mini->id, 2);
    $models = CollectionModel::where('user_id', $this->user->id)->get();
    foreach ($models as $m) {
        $this->actingAs($this->user)->putJson(route('collection.models.update', $m), ['stage' => 'painted'])->assertRedirect();
    }

    $pivot = $this->user->collectionMiniatures()->first()->pivot;
    expect((int) $pivot->quantity)->toBe(2)->and((bool) $pivot->is_built)->toBeTrue()->and((bool) $pivot->is_painted)->toBeTrue();

    $this->actingAs($this->user)->postJson(route('collection.models.store'), ['miniature_id' => $this->mini->id])->assertRedirect();
    $pivot = $this->user->collectionMiniatures()->first()->pivot;
    expect((int) $pivot->quantity)->toBe(3)->and((bool) $pivot->is_built)->toBeFalse();
});

it('adds a model of a miniature that is not in the collection yet', function () {
    $this->actingAs($this->user)->postJson(route('collection.models.store'), ['miniature_id' => $this->mini->id, 'stage' => 'primed', 'note' => 'Spare'])->assertRedirect();

    expect(stagesOf($this->user, $this->mini))->toBe(['primed']);
    expect($this->user->collectionMiniatures()->count())->toBe(1);
});

it('takes one model out, and the last one takes the miniature out of the collection', function () {
    CollectionModels::setQuantity($this->user, $this->mini->id, 2);
    $models = CollectionModel::where('user_id', $this->user->id)->orderBy('id')->get();

    $this->actingAs($this->user)->deleteJson(route('collection.models.destroy', $models[0]))->assertRedirect();
    expect((int) $this->user->collectionMiniatures()->first()->pivot->quantity)->toBe(1);

    $this->actingAs($this->user)->deleteJson(route('collection.models.destroy', $models[1]))->assertRedirect();
    expect($this->user->collectionMiniatures()->count())->toBe(0);
    expect(CollectionModel::where('user_id', $this->user->id)->count())->toBe(0);
});

it('drops the least progressed models first when the quantity goes down', function () {
    CollectionModels::setQuantity($this->user, $this->mini->id, 3);
    $models = CollectionModel::where('user_id', $this->user->id)->orderBy('id')->get();
    $models[0]->update(['stage' => 'painted']);
    $models[1]->update(['stage' => 'built']);

    $this->actingAs($this->user)->postJson(route('collection.toggle'), ['miniature_id' => $this->mini->id, 'quantity' => 2])->assertRedirect();

    expect(stagesOf($this->user, $this->mini))->toBe(['painted', 'built']);
});

// ─── Group actions ───

it('marks every copy built or painted, and back', function () {
    CollectionModels::setQuantity($this->user, $this->mini->id, 2);
    CollectionModel::where('user_id', $this->user->id)->first()->update(['stage' => 'primed']);

    $this->actingAs($this->user)->postJson(route('collection.update_status'), ['miniature_id' => $this->mini->id, 'is_painted' => true])->assertRedirect();
    expect(stagesOf($this->user, $this->mini))->toBe(['painted', 'painted']);

    $this->actingAs($this->user)->postJson(route('collection.update_status'), ['miniature_id' => $this->mini->id, 'is_painted' => false])->assertRedirect();
    expect(stagesOf($this->user, $this->mini))->toBe(['built', 'built']);

    $this->actingAs($this->user)->postJson(route('collection.update_status'), ['miniature_id' => $this->mini->id, 'is_built' => false])->assertRedirect();
    expect(stagesOf($this->user, $this->mini))->toBe(['unbuilt', 'unbuilt']);
});

it('sets a stage for many miniatures at once', function () {
    CollectionModels::setQuantity($this->user, $this->mini->id, 2);
    CollectionModels::setQuantity($this->user, $this->mini2->id, 1);

    $this->actingAs($this->user)->postJson(route('collection.set_stage_bulk'), ['miniature_ids' => [$this->mini->id, $this->mini2->id], 'stage' => 'primed'])->assertRedirect();

    expect(stagesOf($this->user, $this->mini))->toBe(['primed', 'primed']);
    expect(stagesOf($this->user, $this->mini2))->toBe(['primed']);
});

it('removes the models when a miniature is removed', function () {
    CollectionModels::setQuantity($this->user, $this->mini->id, 2);

    $this->actingAs($this->user)->postJson(route('collection.remove'), ['miniature_id' => $this->mini->id])->assertRedirect();

    expect(CollectionModel::where('user_id', $this->user->id)->count())->toBe(0);
});

// ─── Rules ───

it('rejects a stage that does not exist, an over-long note and too many copies', function () {
    CollectionModels::setQuantity($this->user, $this->mini->id, 1);
    $model = CollectionModel::where('user_id', $this->user->id)->first();

    $this->actingAs($this->user)->putJson(route('collection.models.update', $model), ['stage' => 'shiny'])->assertStatus(422);
    $this->actingAs($this->user)->putJson(route('collection.models.update', $model), ['note' => str_repeat('a', 256)])->assertStatus(422);
    $this->actingAs($this->user)->postJson(route('collection.toggle'), ['miniature_id' => $this->mini->id, 'quantity' => 201])->assertStatus(422);
    $this->actingAs($this->user)->postJson(route('collection.set_stage_bulk'), ['miniature_ids' => [], 'stage' => 'built'])->assertStatus(422);
});

it('stops at the most copies one miniature can have', function () {
    CollectionModels::setQuantity($this->user, $this->mini->id, CollectionModels::MAX_PER_MINIATURE);

    expect(CollectionModels::addModel($this->user, $this->mini->id))->toBeNull();
    expect(CollectionModel::where('user_id', $this->user->id)->count())->toBe(CollectionModels::MAX_PER_MINIATURE);
});

it("does not let someone change or delete another person's model", function () {
    $other = User::factory()->create();
    CollectionModels::setQuantity($other, $this->mini->id, 1);
    $theirs = CollectionModel::where('user_id', $other->id)->first();

    $this->actingAs($this->user)->putJson(route('collection.models.update', $theirs), ['stage' => 'based'])->assertForbidden();
    $this->actingAs($this->user)->deleteJson(route('collection.models.destroy', $theirs))->assertForbidden();
    expect($theirs->fresh()->stage)->toBe(ModelStageEnum::Unbuilt);
});

it('needs a login for every model action', function () {
    $this->postJson(route('collection.models.store'), ['miniature_id' => $this->mini->id])->assertUnauthorized();
    $this->postJson(route('collection.set_stage_bulk'), ['miniature_ids' => [$this->mini->id], 'stage' => 'built'])->assertUnauthorized();
});

// ─── Sharing ───

it('shows a shared collection with stages but not the notes', function () {
    CollectionModels::setQuantity($this->user, $this->mini->id, 1);
    CollectionModel::where('user_id', $this->user->id)->first()->update(['stage' => 'painted', 'note' => 'Private joke']);
    $this->user->update(['collection_is_public' => true, 'collection_share_code' => 'abc123abc123']);
    $viewer = User::factory()->create();

    $this->actingAs($viewer)->get(route('collection.share', 'abc123abc123'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('collection.0.models.0.stage', 'painted')->where('collection.0.models.0.note', null)->where('is_owner', false));
    $this->actingAs($this->user)->get(route('collection.share', 'abc123abc123'))
        ->assertInertia(fn ($page) => $page->where('collection.0.models.0.note', 'Private joke'));
});

it('counts models, not miniatures, on the profile stats', function () {
    CollectionModels::setQuantity($this->user, $this->mini->id, 3);
    CollectionModels::mark($this->user, [$this->mini->id], true, true);

    expect(CollectionModels::totals($this->user))->toMatchArray(['models' => 3, 'built' => 3, 'painted' => 3, 'miniatures' => 1]);
});

// ─── Packages and factions count the models ───

it('shows what a package holds and how much of it the player has, built and painted', function () {
    $other = Character::factory()->create();
    $otherMini = Miniature::factory()->create(['character_id' => $other->id]);
    $package = \App\Models\Package::factory()->create(['factions' => ['guild'], 'game_system' => \App\Enums\GameSystemEnum::Malifaux]);
    $package->characters()->attach([$this->character->id => ['quantity' => 2], $other->id => ['quantity' => 1]]);
    $this->user->collectionPackages()->attach($package->id);

    // Three copies of the first character (the package holds two), none of the second.
    CollectionModels::setQuantity($this->user, $this->mini->id, 3);
    $models = CollectionModel::where('user_id', $this->user->id)->orderBy('id')->get();
    $models[0]->update(['stage' => 'painted']);
    $models[1]->update(['stage' => 'built']);

    $manifest = public_path('build/manifest.json');
    $props = $this->actingAs($this->user)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? hash_file('xxh128', $manifest) : '',
        'X-Inertia-Partial-Component' => 'Collection/Index',
        'X-Inertia-Partial-Data' => 'owned_packages',
    ])->get(route('collection.index'))->assertOk()->json('props.owned_packages');

    expect($props)->toHaveCount(1);
    expect($props[0])->toMatchArray(['models' => 3, 'owned' => 2, 'built' => 2, 'painted' => 1]);
    expect($props[0]['contents'])->toHaveCount(2);
});

it('counts the models, built and painted in each faction', function () {
    $faction = $this->character->faction->value;
    CollectionModels::setQuantity($this->user, $this->mini->id, 2);
    CollectionModels::mark($this->user, [$this->mini->id], true, null);

    $this->actingAs($this->user)->get(route('collection.index'))->assertInertia(fn ($page) => $page
        ->where('faction_stats', fn ($stats) => collect($stats)->firstWhere('faction', $faction) === null
            ? false
            : collect($stats)->firstWhere('faction', $faction)['models'] === 2 && collect($stats)->firstWhere('faction', $faction)['built'] === 2 && collect($stats)->firstWhere('faction', $faction)['painted'] === 0));
});
