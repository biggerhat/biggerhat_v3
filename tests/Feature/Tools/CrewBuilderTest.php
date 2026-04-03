<?php

use App\Enums\CharacterStationEnum;
use App\Models\Character;
use App\Models\CrewBuild;
use App\Models\User;

it('displays the crew builder page', function () {
    Character::factory()->count(3)->create([
        'station' => CharacterStationEnum::Master,
        'is_hidden' => false,
    ]);

    $response = $this->get(route('tools.crew_builder.editor'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tools/CrewBuilder/Index')
            ->has('factions')
            ->has('keywords')
            ->has('characters')
            ->has('savedBuilds')
        );
});

it('returns saved builds for authenticated users', function () {
    $user = User::factory()->create();
    $master = Character::factory()->create(['station' => CharacterStationEnum::Master, 'is_hidden' => false]);
    CrewBuild::factory()->count(2)->create(['user_id' => $user->id, 'master_id' => $master->id]);

    $response = $this->actingAs($user)->get(route('tools.crew_builder.editor'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tools/CrewBuilder/Index')
            ->has('savedBuilds', 2)
        );
});

it('returns empty saved builds for guests', function () {
    $response = $this->get(route('tools.crew_builder.editor'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tools/CrewBuilder/Index')
            ->where('savedBuilds', [])
        );
});

it('stores a new crew build for authenticated users', function () {
    $user = User::factory()->create();
    $master = Character::factory()->create(['station' => CharacterStationEnum::Master]);
    $minion = Character::factory()->create(['station' => CharacterStationEnum::Minion, 'cost' => 5]);

    $response = $this->actingAs($user)->postJson(route('tools.crew_builder.store'), [
        'name' => 'My Test Crew',
        'faction' => $master->faction->value,
        'master_id' => $master->id,
        'encounter_size' => 50,
        'crew_data' => [$minion->id],
    ]);

    $response->assertOk()
        ->assertJsonStructure(['id', 'share_code']);

    $this->assertDatabaseHas('crew_builds', [
        'user_id' => $user->id,
        'name' => 'My Test Crew',
        'master_id' => $master->id,
    ]);
});

it('requires authentication to store a build', function () {
    $master = Character::factory()->create(['station' => CharacterStationEnum::Master]);

    $response = $this->postJson(route('tools.crew_builder.store'), [
        'name' => 'My Test Crew',
        'faction' => $master->faction->value,
        'master_id' => $master->id,
        'encounter_size' => 50,
        'crew_data' => [$master->id],
    ]);

    $response->assertUnauthorized();
});

it('validates required fields on store', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('tools.crew_builder.store'), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'faction', 'master_id', 'encounter_size', 'crew_data']);
});

it('updates an existing crew build', function () {
    $user = User::factory()->create();
    $build = CrewBuild::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->putJson(route('tools.crew_builder.update', $build), [
        'name' => 'Updated Crew Name',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['id', 'share_code', 'is_archived']);

    $this->assertDatabaseHas('crew_builds', [
        'id' => $build->id,
        'name' => 'Updated Crew Name',
    ]);
});

it('prevents updating another users build', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $build = CrewBuild::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)->putJson(route('tools.crew_builder.update', $build), [
        'name' => 'Hijacked',
    ]);

    $response->assertForbidden();
});

it('archives and unarchives a build', function () {
    $user = User::factory()->create();
    $build = CrewBuild::factory()->create(['user_id' => $user->id, 'is_archived' => false]);

    $response = $this->actingAs($user)->putJson(route('tools.crew_builder.update', $build), [
        'is_archived' => true,
    ]);

    $response->assertOk();
    expect($build->fresh()->is_archived)->toBeTrue();

    $response = $this->actingAs($user)->putJson(route('tools.crew_builder.update', $build), [
        'is_archived' => false,
    ]);

    $response->assertOk();
    expect($build->fresh()->is_archived)->toBeFalse();
});

it('deletes a crew build', function () {
    $user = User::factory()->create();
    $build = CrewBuild::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->deleteJson(route('tools.crew_builder.destroy', $build));

    $response->assertOk()->assertJson(['success' => true]);
    $this->assertSoftDeleted('crew_builds', ['id' => $build->id]);
});

it('prevents deleting another users build', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $build = CrewBuild::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)->deleteJson(route('tools.crew_builder.destroy', $build));

    $response->assertForbidden();
    $this->assertDatabaseHas('crew_builds', ['id' => $build->id]);
});

it('stores a crew build with empty crew data', function () {
    $user = User::factory()->create();
    $master = Character::factory()->create(['station' => CharacterStationEnum::Master]);

    $response = $this->actingAs($user)->postJson(route('tools.crew_builder.store'), [
        'name' => 'Empty Crew',
        'faction' => $master->faction->value,
        'master_id' => $master->id,
        'encounter_size' => 50,
        'crew_data' => [],
    ]);

    $response->assertOk()
        ->assertJsonStructure(['id', 'share_code']);
});

it('displays a public shared build by share code', function () {
    $user = User::factory()->create(['name' => 'TestCreator']);
    $build = CrewBuild::factory()->public()->create(['user_id' => $user->id]);

    $response = $this->get(route('tools.crew_builder.share', $build->share_code));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tools/CrewBuilder/View')
            ->has('build', fn ($b) => $b
                ->where('id', $build->id)
                ->where('faction', $build->faction->value)
                ->where('master_id', $build->master_id)
                ->where('user_name', 'TestCreator')
                ->etc()
            )
            ->has('characters')
            ->has('factions')
        );
});

it('shows private notice for private shared build when not owner', function () {
    $build = CrewBuild::factory()->create(['is_public' => false]);

    $response = $this->get(route('tools.crew_builder.share', $build->share_code));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tools/CrewBuilder/Private')
        );
});

it('allows owner to view their private shared build', function () {
    $user = User::factory()->create();
    $build = CrewBuild::factory()->create(['user_id' => $user->id, 'is_public' => false]);

    $response = $this->actingAs($user)->get(route('tools.crew_builder.share', $build->share_code));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tools/CrewBuilder/View')
            ->has('build')
        );
});

it('toggles public visibility on a build', function () {
    $user = User::factory()->create();
    $build = CrewBuild::factory()->create(['user_id' => $user->id, 'is_public' => false]);

    $response = $this->actingAs($user)->putJson(route('tools.crew_builder.update', $build), [
        'is_public' => true,
    ]);

    $response->assertOk()->assertJson(['is_public' => true]);
    expect($build->fresh()->is_public)->toBeTrue();

    $response = $this->actingAs($user)->putJson(route('tools.crew_builder.update', $build), [
        'is_public' => false,
    ]);

    $response->assertOk()->assertJson(['is_public' => false]);
    expect($build->fresh()->is_public)->toBeFalse();
});

it('returns saved builds with faction as string', function () {
    $user = User::factory()->create();
    $build = CrewBuild::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('tools.crew_builder.editor'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('savedBuilds.0', fn ($saved) => $saved
                ->where('faction', $build->faction->value)
                ->where('id', $build->id)
                ->etc()
            )
        );
});

it('returns 404 for invalid share code', function () {
    $response = $this->get(route('tools.crew_builder.share', 'nonexistent'));

    $response->assertNotFound();
});

it('generates a unique share code on creation', function () {
    $builds = CrewBuild::factory()->count(3)->create();

    $codes = $builds->pluck('share_code')->unique();
    expect($codes)->toHaveCount(3);
    $codes->each(fn ($code) => expect($code)->toBeString()->not->toBeEmpty());
});

it('excludes hidden characters from the hiring pool', function () {
    Character::factory()->create(['station' => CharacterStationEnum::Master, 'is_hidden' => false]);
    Character::factory()->create(['station' => CharacterStationEnum::Henchman, 'is_hidden' => true, 'cost' => 7, 'is_unhirable' => false]);
    Character::factory()->create(['station' => CharacterStationEnum::Henchman, 'is_hidden' => false, 'cost' => 5, 'is_unhirable' => false]);

    $response = $this->get(route('tools.crew_builder.editor'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('characters', 2)
        );
});

it('stores crew data with character ids', function () {
    $user = User::factory()->create();
    $master = Character::factory()->create(['station' => CharacterStationEnum::Master]);
    $minions = Character::factory()->count(3)->create(['station' => CharacterStationEnum::Minion, 'cost' => 4]);

    $crewData = $minions->pluck('id')->toArray();

    $response = $this->actingAs($user)->postJson(route('tools.crew_builder.store'), [
        'name' => 'Full Crew',
        'faction' => $master->faction->value,
        'master_id' => $master->id,
        'encounter_size' => 50,
        'crew_data' => $crewData,
    ]);

    $response->assertOk();

    $build = CrewBuild::find($response->json('id'));
    expect($build->crew_data)->toBe($crewData);
});
