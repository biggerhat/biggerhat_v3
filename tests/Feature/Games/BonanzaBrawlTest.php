<?php

use App\Enums\GameFormatEnum;
use App\Enums\GameStatusEnum;
use App\Enums\PoolSeasonEnum;
use App\Models\Character;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\Miniature;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\Token;
use App\Models\User;

beforeEach(function () {
    Strategy::factory()->count(3)->create(['season' => PoolSeasonEnum::GainingGrounds0]);
    Scheme::factory()->count(5)->create(['season' => PoolSeasonEnum::GainingGrounds0]);
});

it('creates a Bonanza game with no scenario and 11ss locked encounter size', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('games.store'), [
            'season' => PoolSeasonEnum::GainingGrounds0->value,
            'encounter_size' => 50, // server should override
            'format' => GameFormatEnum::BonanzaBrawl->value,
            'is_solo' => true,
        ])->assertRedirect();

    $game = Game::latest('id')->first();
    expect($game->format)->toBe(GameFormatEnum::BonanzaBrawl);
    expect($game->encounter_size)->toBe(11);
    expect($game->strategy_id)->toBeNull();
    expect($game->deployment)->toBeNull();
    expect($game->scheme_pool)->toBeNull();
});

it('creates a standard game with scenario triple by default', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('games.store'), [
            'season' => PoolSeasonEnum::GainingGrounds0->value,
            'encounter_size' => 50,
            'is_solo' => true,
        ])->assertRedirect();

    $game = Game::latest('id')->first();
    expect($game->format)->toBe(GameFormatEnum::Standard);
    expect($game->strategy_id)->not->toBeNull();
    expect($game->scheme_pool)->toBeArray();
});

it('jumps a Bonanza game straight to InProgress when the user submits their master', function () {
    // Bonanza is force-solo personal-tracking — the user only ever submits
    // their own master, slot 2 stays inert, and submission immediately advances
    // to InProgress (skipping Crew + Scheme select entirely).
    $creator = User::factory()->create();

    $game = Game::factory()->bonanza()->create([
        'creator_id' => $creator->id,
        'is_solo' => true,
        'status' => GameStatusEnum::MasterSelect,
    ]);

    $arc = Character::factory()->create([
        'station' => 'master',
        'faction' => 'arcanists',
        'name' => 'Rasputina',
        'display_name' => 'Rasputina',
    ]);
    Miniature::factory()->for($arc, 'character')->create();

    GamePlayer::factory()->for($game, 'game')->create([
        'user_id' => $creator->id,
        'slot' => 1,
        'faction' => 'arcanists',
    ]);
    // Slot 2 exists but stays empty — no opponent in personal-tracking mode.
    GamePlayer::factory()->for($game, 'game')->create([
        'user_id' => null,
        'slot' => 2,
        'opponent_name' => 'Opponent',
    ]);

    $this->actingAs($creator)->postJson(route('games.setup.master', $game->uuid), [
        'master_name' => 'Rasputina',
    ])->assertOk();

    $game->refresh();
    expect($game->status)->toBe(GameStatusEnum::InProgress);
    expect($game->current_turn)->toBe(1);

    // Only the user's lone model is created — slot 2 has no crew row.
    $members = GameCrewMember::where('game_id', $game->id)->get();
    expect($members)->toHaveCount(1);
    expect($members->first()->hiring_category)->toBe('lone');
});

it('copies the master\'s characteristics onto the Bonanza lone GameCrewMember', function () {
    $creator = User::factory()->create();

    $game = Game::factory()->bonanza()->create([
        'creator_id' => $creator->id,
        'is_solo' => true,
        'status' => GameStatusEnum::MasterSelect,
    ]);

    $arc = Character::factory()->create([
        'station' => 'master',
        'faction' => 'arcanists',
        'name' => 'Rasputina',
        'display_name' => 'Rasputina',
    ]);
    Miniature::factory()->for($arc, 'character')->create();
    $versatile = \App\Models\Characteristic::factory()->create(['name' => 'Versatile']);
    $arc->characteristics()->attach($versatile);

    GamePlayer::factory()->for($game, 'game')->create(['user_id' => $creator->id, 'slot' => 1, 'faction' => 'arcanists']);
    GamePlayer::factory()->for($game, 'game')->create(['user_id' => null, 'slot' => 2, 'opponent_name' => 'Opponent']);

    $this->actingAs($creator)->postJson(route('games.setup.master', $game->uuid), [
        'master_name' => 'Rasputina',
    ])->assertOk();

    $member = GameCrewMember::where('game_id', $game->id)->firstOrFail();
    expect($member->characteristics)->toBe(['Versatile']);
});

it('forces a Bonanza game to solo mode regardless of the form input', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('games.store'), [
            'season' => PoolSeasonEnum::GainingGrounds0->value,
            'encounter_size' => 50,
            'format' => GameFormatEnum::BonanzaBrawl->value,
            'is_solo' => false,
        ])->assertRedirect();

    $game = Game::latest('id')->first();
    expect($game->format)->toBe(GameFormatEnum::BonanzaBrawl);
    expect($game->is_solo)->toBeTrue();
    // Both player rows exist (creator + inert opponent), matching the standard
    // solo shape so the rest of the codebase doesn't have to special-case it.
    expect($game->players()->count())->toBe(2);
});

it('adjusts Bonanza VP via the manual delta endpoint', function () {
    $user = User::factory()->create();
    $game = Game::factory()->bonanza()->inProgress()->create(['creator_id' => $user->id]);
    $player = GamePlayer::factory()->for($game, 'game')->create([
        'user_id' => $user->id,
        'slot' => 1,
        'total_points' => 0,
    ]);

    // +3 (kill)
    $this->actingAs($user)->patchJson(route('games.play.bonanza_vp', $game->uuid), ['delta' => 3])
        ->assertOk()
        ->assertJsonPath('total_points', 3);

    // +1 (damage)
    $this->actingAs($user)->patchJson(route('games.play.bonanza_vp', $game->uuid), ['delta' => 1])
        ->assertOk()
        ->assertJsonPath('total_points', 4);

    // -3 (death) — should subtract.
    $this->actingAs($user)->patchJson(route('games.play.bonanza_vp', $game->uuid), ['delta' => -3])
        ->assertOk()
        ->assertJsonPath('total_points', 1);

    // -10 (overshoot) — clamps to 0 per rulebook.
    $this->actingAs($user)->patchJson(route('games.play.bonanza_vp', $game->uuid), ['delta' => -10])
        ->assertOk()
        ->assertJsonPath('total_points', 0);

    expect($player->fresh()->total_points)->toBe(0);
});

it('lets a Bonanza player pick a non-master character (any station) of their faction', function () {
    $creator = User::factory()->create();
    $game = Game::factory()->bonanza()->create([
        'creator_id' => $creator->id,
        'status' => GameStatusEnum::MasterSelect,
        'is_solo' => false,
    ]);

    // A non-master (minion) the player wants to hire as their lone Bonanza model.
    $enforcer = Character::factory()->create([
        'station' => 'minion',
        'faction' => 'arcanists',
        'name' => 'Mechanical Rider',
        'display_name' => 'Mechanical Rider',
        'cost' => 10,
        'health' => 10,
    ]);
    Miniature::factory()->for($enforcer, 'character')->create();

    $opponent = User::factory()->create();
    GamePlayer::factory()->for($game, 'game')->create([
        'user_id' => $creator->id,
        'slot' => 1,
        'faction' => 'arcanists',
    ]);
    GamePlayer::factory()->for($game, 'game')->create([
        'user_id' => $opponent->id,
        'slot' => 2,
        'faction' => 'arcanists',
    ]);

    // The relaxed query (no station=master gate) should resolve the minion pick
    // and populate master_id. We don't need to advance the game past
    // master_select for this assertion — the lookup is the regression target.
    $this->actingAs($creator)->postJson(route('games.setup.master', $game->uuid), [
        'master_name' => 'Mechanical Rider',
    ])->assertOk();

    expect(GamePlayer::where('game_id', $game->id)->where('slot', 1)->first()->master_id)->toBe($enforcer->id);
});

it('excludes Masters from the Bonanza model-select prop', function () {
    $creator = User::factory()->create();
    $game = Game::factory()->bonanza()->create([
        'creator_id' => $creator->id,
        'is_solo' => true,
        'status' => GameStatusEnum::MasterSelect,
    ]);
    GamePlayer::factory()->for($game, 'game')->create(['user_id' => $creator->id, 'slot' => 1, 'faction' => 'arcanists']);

    // A Master (cost null → bonanzaCost caps at 10, so it WOULD clear the cost
    // filter) plus a minion. Only the non-Leader minion should be offered.
    $master = Character::factory()->create(['station' => 'master', 'faction' => 'arcanists', 'name' => 'Rasputina', 'display_name' => 'Rasputina', 'cost' => null, 'health' => 8]);
    Miniature::factory()->for($master, 'character')->create();
    $minion = Character::factory()->create(['station' => 'minion', 'faction' => 'arcanists', 'name' => 'Ice Gamin', 'display_name' => 'Ice Gamin', 'cost' => 4]);
    Miniature::factory()->for($minion, 'character')->create();
    // A NULL-station model (totem/peon): must still be offered — a bare
    // `station != 'master'` would wrongly drop it (NULL != 'master' isn't true).
    $totem = Character::factory()->create(['station' => null, 'faction' => 'arcanists', 'name' => 'Wendigo', 'display_name' => 'Wendigo', 'cost' => 4]);
    Miniature::factory()->for($totem, 'character')->create();

    $this->actingAs($creator)->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('masters', function ($masters) {
            $names = collect($masters)->pluck('name');

            return ! $names->contains('Rasputina') && $names->contains('Ice Gamin') && $names->contains('Wendigo');
        }));
});

it('derives References (with tokens) for the Bonanza model that has no crew build', function () {
    $creator = User::factory()->create();
    $game = Game::factory()->bonanza()->create([
        'creator_id' => $creator->id,
        'is_solo' => true,
        'status' => GameStatusEnum::MasterSelect,
    ]);

    $model = Character::factory()->create(['station' => 'minion', 'faction' => 'arcanists', 'name' => 'Ice Golem', 'display_name' => 'Ice Golem', 'cost' => 8]);
    Miniature::factory()->for($model, 'character')->create();
    $token = Token::factory()->create(['name' => 'Frozen Heart']);
    $model->tokens()->attach($token->id);

    GamePlayer::factory()->for($game, 'game')->create(['user_id' => $creator->id, 'slot' => 1, 'faction' => 'arcanists']);
    GamePlayer::factory()->for($game, 'game')->create(['user_id' => null, 'slot' => 2, 'opponent_name' => 'Opp']);

    $this->actingAs($creator)->postJson(route('games.setup.master', $game->uuid), ['master_name' => 'Ice Golem'])->assertOk();

    $this->actingAs($creator)->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('game.players', function ($players) {
            $slotOne = collect($players)->firstWhere('slot', 1);
            $tokens = collect($slotOne['references']['tokens'] ?? []);

            return $tokens->contains('name', 'Frozen Heart');
        }));
});

it('derives effective Bonanza cost for dash-cost models', function () {
    $totem = Character::factory()->create(['cost' => null, 'health' => 5]);
    expect($totem->bonanzaCost())->toBe(4); // health - 1

    $beefy = Character::factory()->create(['cost' => null, 'health' => 14]);
    expect($beefy->bonanzaCost())->toBe(10); // capped per rulebook

    $printed = Character::factory()->create(['cost' => 7, 'health' => 5]);
    expect($printed->bonanzaCost())->toBe(7); // printed cost wins
});

it('advances the turn counter on Bonanza next-turn', function () {
    $user = User::factory()->create();
    $game = Game::factory()->bonanza()->inProgress()->create([
        'creator_id' => $user->id,
        'is_solo' => true,
        'current_turn' => 1,
        'max_turns' => 5,
    ]);
    GamePlayer::factory()->for($game, 'game')->create(['user_id' => $user->id, 'slot' => 1]);
    GamePlayer::factory()->for($game, 'game')->create(['user_id' => null, 'slot' => 2]);

    $this->actingAs($user)->postJson(route('games.play.bonanza_next_turn', $game->uuid))
        ->assertOk()
        ->assertJsonPath('current_turn', 2)
        ->assertJsonPath('game_complete', false);

    expect($game->fresh()->current_turn)->toBe(2);
    expect($game->fresh()->status)->toBe(GameStatusEnum::InProgress);
});

it('finalizes a Bonanza game when the last turn ends, with no winner declared', function () {
    $user = User::factory()->create();
    $game = Game::factory()->bonanza()->inProgress()->create([
        'creator_id' => $user->id,
        'is_solo' => true,
        'current_turn' => 5,
        'max_turns' => 5,
    ]);
    GamePlayer::factory()->for($game, 'game')->create(['user_id' => $user->id, 'slot' => 1, 'total_points' => 7]);
    GamePlayer::factory()->for($game, 'game')->create(['user_id' => null, 'slot' => 2]);

    $this->actingAs($user)->postJson(route('games.play.bonanza_next_turn', $game->uuid))
        ->assertOk()
        ->assertJsonPath('game_complete', true);

    $game->refresh();
    expect($game->status)->toBe(GameStatusEnum::Completed);
    // Personal-tracker mode → no opponent comparison, so we mark it as a tie
    // rather than crowning the user by default.
    expect($game->is_tie)->toBeTrue();
    expect($game->winner_id)->toBeNull();
    expect($game->winner_slot)->toBeNull();
});

it('rejects the Bonanza next-turn endpoint on a standard format game', function () {
    $user = User::factory()->create();
    $game = Game::factory()->inProgress()->create(['creator_id' => $user->id]);
    GamePlayer::factory()->for($game, 'game')->create(['user_id' => $user->id, 'slot' => 1]);

    $this->actingAs($user)->postJson(route('games.play.bonanza_next_turn', $game->uuid))->assertStatus(422);
});

it('rejects the Bonanza VP endpoint on a standard format game', function () {
    $user = User::factory()->create();
    $game = Game::factory()->inProgress()->create(['creator_id' => $user->id]);
    GamePlayer::factory()->for($game, 'game')->create([
        'user_id' => $user->id,
        'slot' => 1,
    ]);

    $this->actingAs($user)->patchJson(route('games.play.bonanza_vp', $game->uuid), ['delta' => 1])
        ->assertStatus(422);
});
