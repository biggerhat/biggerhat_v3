<?php

use App\Enums\GameStatusEnum;
use App\Enums\PoolSeasonEnum;
use App\Models\Character;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\LootCard;
use App\Models\Miniature;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\User;
use App\Services\LootDeckService;

beforeEach(function () {
    Strategy::factory()->count(3)->create(['season' => PoolSeasonEnum::GainingGrounds0]);
    Scheme::factory()->count(5)->create(['season' => PoolSeasonEnum::GainingGrounds0]);
});

// Light helper — the seeder wires up Action/Ability/Trigger relations we don't
// need here. Build the bare minimum so deck-pop logic has something to chew on.
function makeLootCardSet(int $count = 6): \Illuminate\Support\Collection
{
    return collect(range(1, $count))->map(fn ($i) => LootCard::create([
        'slug' => "card-{$i}",
        'suit' => 'crow',
        'value' => $i,
        'value_label' => (string) $i,
        'name' => "Card {$i}",
        'title_a' => "A Title {$i}",
        'effect_a' => "Effect A {$i}",
        'title_b' => "B Title {$i}",
        'effect_b' => "Effect B {$i}",
        'sort_order' => $i,
    ]));
}

// ── LootDeckService unit tests ─────────────────────────────────────────────

it('initialState shuffles every catalog card id into the deck', function () {
    makeLootCardSet(6);
    $service = new LootDeckService;

    $state = $service->initialState();

    expect($state['deck'])->toHaveCount(6);
    expect($state['discard'])->toBe([]);
    expect($state['dropped_markers'])->toBe([]);
    expect(collect($state['deck'])->sort()->values()->all())->toBe(LootCard::pluck('id')->all());
});

it('draw pops the top of the deck and persists state', function () {
    makeLootCardSet(3);
    $service = new LootDeckService;
    $game = Game::factory()->bonanza()->inProgress()->create([
        'loot_state' => $service->initialState(),
    ]);

    $beforeSize = count($game->loot_state['deck']);
    $card = $service->draw($game->fresh());

    expect($card)->not->toBeNull();
    expect($game->fresh()->loot_state['deck'])->toHaveCount($beforeSize - 1);
});

it('draw reshuffles discard back into deck when deck runs dry', function () {
    makeLootCardSet(3);
    $service = new LootDeckService;
    $game = Game::factory()->bonanza()->inProgress()->create([
        'loot_state' => [
            'deck' => [],
            'discard' => [1, 2, 3],
            'dropped_markers' => [],
        ],
    ]);

    $card = $service->draw($game->fresh());

    expect($card)->not->toBeNull();
    expect($game->fresh()->loot_state['discard'])->toBe([]);
    expect($game->fresh()->loot_state['deck'])->toHaveCount(2);
});

it('draw returns null when deck and discard are both empty', function () {
    makeLootCardSet(3);
    $service = new LootDeckService;
    $game = Game::factory()->bonanza()->inProgress()->create([
        'loot_state' => [
            'deck' => [],
            'discard' => [],
            'dropped_markers' => [],
        ],
    ]);

    expect($service->draw($game->fresh()))->toBeNull();
});

it('attachToMember adds an entry tagged with loot_card_id and chosen side', function () {
    $cards = makeLootCardSet(2);
    $service = new LootDeckService;
    $member = GameCrewMember::factory()->create();

    $service->attachToMember($member, $cards->first(), 'a');

    $member->refresh();
    expect($member->attached_upgrades)->toHaveCount(1);
    expect($member->attached_upgrades[0]['loot_card_id'])->toBe($cards->first()->id);
    expect($member->attached_upgrades[0]['loot_side'])->toBe('a');
});

it('dropMarkersOnDeath converts loot upgrades to markers and strips the member', function () {
    $cards = makeLootCardSet(2);
    $service = new LootDeckService;
    $game = Game::factory()->bonanza()->inProgress()->create([
        'loot_state' => $service->initialState(),
    ]);
    $member = GameCrewMember::factory()->create([
        'game_id' => $game->id,
        'attached_upgrades' => [
            // a regular upgrade — should not be touched
            ['id' => 42, 'name' => 'Regular Upgrade'],
            // two loot entries — should drop as markers
            ['id' => 1_000_000_001, 'name' => 'Loot 1', 'loot_card_id' => $cards[0]->id, 'loot_side' => 'a'],
            ['id' => 1_000_000_002, 'name' => 'Loot 2', 'loot_card_id' => $cards[1]->id, 'loot_side' => 'b'],
        ],
    ]);

    $created = $service->dropMarkersOnDeath($game->fresh(), $member);

    expect($created)->toHaveCount(2);
    expect($game->fresh()->loot_state['dropped_markers'])->toHaveCount(2);
    expect($member->fresh()->attached_upgrades)->toHaveCount(1);
    expect($member->fresh()->attached_upgrades[0]['id'])->toBe(42);
});

it('yoinkMarker removes the marker and reattaches with a new side choice', function () {
    $cards = makeLootCardSet(2);
    $service = new LootDeckService;
    $marker = ['id' => 'abc-123', 'card_id' => $cards->first()->id, 'side' => 'a', 'dropped_by_player_id' => null];
    $game = Game::factory()->bonanza()->inProgress()->create([
        'loot_state' => [
            'deck' => [],
            'discard' => [],
            'dropped_markers' => [$marker],
        ],
    ]);
    $member = GameCrewMember::factory()->create(['game_id' => $game->id]);

    $card = $service->yoinkMarker($game->fresh(), $member, 'abc-123', 'b');

    expect($card)->not->toBeNull();
    expect($game->fresh()->loot_state['dropped_markers'])->toBe([]);
    expect($member->fresh()->attached_upgrades)->toHaveCount(1);
    expect($member->fresh()->attached_upgrades[0]['loot_side'])->toBe('b');
});

it('yoinkMarker returns null when the marker id does not exist', function () {
    makeLootCardSet(2);
    $service = new LootDeckService;
    $game = Game::factory()->bonanza()->inProgress()->create([
        'loot_state' => ['deck' => [], 'discard' => [], 'dropped_markers' => []],
    ]);
    $member = GameCrewMember::factory()->create(['game_id' => $game->id]);

    expect($service->yoinkMarker($game->fresh(), $member, 'no-such-id', 'a'))->toBeNull();
});

// ── HTTP endpoint tests ────────────────────────────────────────────────────

it('auto-initializes loot_state when a Bonanza game enters InProgress', function () {
    makeLootCardSet(6);

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
    GamePlayer::factory()->for($game, 'game')->create([
        'user_id' => null,
        'slot' => 2,
        'opponent_name' => 'Opponent',
    ]);

    // Single submission — Bonanza force-solo means slot 1 alone advances to InProgress.
    $this->actingAs($creator)->postJson(route('games.setup.master', $game->uuid), ['master_name' => 'Rasputina'])->assertOk();

    $game->refresh();
    expect($game->status)->toBe(GameStatusEnum::InProgress);
    expect($game->loot_state)->toBeArray();
    expect($game->loot_state['deck'])->toHaveCount(6);
});

it('lets the creator draw a loot card via the endpoint', function () {
    makeLootCardSet(3);
    $service = new LootDeckService;

    $user = User::factory()->create();
    $game = Game::factory()->bonanza()->inProgress()->create([
        'creator_id' => $user->id,
        'is_solo' => true,
        'loot_state' => $service->initialState(),
    ]);
    GamePlayer::factory()->for($game, 'game')->create(['user_id' => $user->id, 'slot' => 1]);

    $this->actingAs($user)->postJson(route('games.play.loot.draw', $game->uuid))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['card' => ['id', 'name', 'side_a_actions', 'side_b_actions'], 'deck_size', 'discard_size']);

    expect($game->fresh()->loot_state['deck'])->toHaveCount(2);
});

it('rejects loot endpoints on a standard format game', function () {
    $user = User::factory()->create();
    $game = Game::factory()->inProgress()->create(['creator_id' => $user->id]);
    GamePlayer::factory()->for($game, 'game')->create(['user_id' => $user->id, 'slot' => 1]);

    $this->actingAs($user)->postJson(route('games.play.loot.draw', $game->uuid))->assertStatus(422);
});

it('attaches a drawn loot card to a crew member with a chosen side', function () {
    $cards = makeLootCardSet(2);

    $user = User::factory()->create();
    $game = Game::factory()->bonanza()->inProgress()->create([
        'creator_id' => $user->id,
        'is_solo' => true,
    ]);
    $player = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $user->id, 'slot' => 1]);
    $member = GameCrewMember::factory()->create([
        'game_id' => $game->id,
        'game_player_id' => $player->id,
    ]);

    $this->actingAs($user)->postJson(route('games.play.loot.attach', $game->uuid), [
        'game_crew_member_id' => $member->id,
        'loot_card_id' => $cards->first()->id,
        'side' => 'a',
    ])->assertOk();

    $member->refresh();
    expect($member->attached_upgrades)->toHaveCount(1);
    expect($member->attached_upgrades[0]['loot_card_id'])->toBe($cards->first()->id);
});

it('drops loot markers when a crew member with loot is killed', function () {
    $cards = makeLootCardSet(2);

    $user = User::factory()->create();
    $game = Game::factory()->bonanza()->inProgress()->create([
        'creator_id' => $user->id,
        'is_solo' => true,
        'loot_state' => ['deck' => [], 'discard' => [], 'dropped_markers' => []],
    ]);
    $player = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $user->id, 'slot' => 1]);
    $member = GameCrewMember::factory()->create([
        'game_id' => $game->id,
        'game_player_id' => $player->id,
        'attached_upgrades' => [
            ['id' => 1_000_000_001, 'name' => 'Loot', 'loot_card_id' => $cards->first()->id, 'loot_side' => 'a'],
        ],
    ]);

    $this->actingAs($user)->postJson(route('games.play.crew.kill', ['game' => $game->uuid, 'gameCrewMember' => $member->id]))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($game->fresh()->loot_state['dropped_markers'])->toHaveCount(1);
    expect($member->fresh()->is_killed)->toBeTrue();
    expect(collect($member->fresh()->attached_upgrades)->whereNotNull('loot_card_id')->count())->toBe(0);
});

it('yoinks a dropped marker onto a different crew member', function () {
    $cards = makeLootCardSet(2);
    $marker = ['id' => 'm-1', 'card_id' => $cards->first()->id, 'side' => 'a', 'dropped_by_player_id' => null];

    $user = User::factory()->create();
    $game = Game::factory()->bonanza()->inProgress()->create([
        'creator_id' => $user->id,
        'is_solo' => true,
        'loot_state' => ['deck' => [], 'discard' => [], 'dropped_markers' => [$marker]],
    ]);
    $player = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $user->id, 'slot' => 1]);
    $member = GameCrewMember::factory()->create([
        'game_id' => $game->id,
        'game_player_id' => $player->id,
    ]);

    $this->actingAs($user)->postJson(route('games.play.loot.yoink', $game->uuid), [
        'game_crew_member_id' => $member->id,
        'marker_id' => 'm-1',
        'side' => 'b',
    ])->assertOk();

    expect($game->fresh()->loot_state['dropped_markers'])->toBe([]);
    expect($member->fresh()->attached_upgrades)->toHaveCount(1);
    expect($member->fresh()->attached_upgrades[0]['loot_side'])->toBe('b');
});

it('blocks attaching loot to another player\'s crew member', function () {
    $cards = makeLootCardSet(2);

    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $game = Game::factory()->bonanza()->inProgress()->create(['creator_id' => $owner->id, 'is_solo' => false]);
    $ownerPlayer = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $owner->id, 'slot' => 1]);
    $strangerPlayer = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $stranger->id, 'slot' => 2]);
    $ownerMember = GameCrewMember::factory()->create([
        'game_id' => $game->id,
        'game_player_id' => $ownerPlayer->id,
    ]);

    $this->actingAs($stranger)->postJson(route('games.play.loot.attach', $game->uuid), [
        'game_crew_member_id' => $ownerMember->id,
        'loot_card_id' => $cards->first()->id,
        'side' => 'a',
    ])->assertForbidden();
});
