<?php

use App\Enums\FateZoneEnum;
use App\Models\Game;
use App\Models\GameFateCard;
use App\Models\GameLog;
use App\Models\GamePlayer;
use App\Models\User;
use App\Services\Fate\FateDeckService;
use App\Support\Fate\FateCards;
use Laravel\Pennant\Feature;

beforeEach(function () {
    Feature::for(null)->activate('experiment-tabletop');
});

/** A two-player game in progress: slot 1 is $a, slot 2 is $b. */
function fateGame(bool $solo = false, bool $observable = false): array
{
    $a = User::factory()->create();
    $b = User::factory()->create();
    $game = Game::factory()->inProgress()->create(['creator_id' => $a->id, 'is_solo' => $solo, 'is_observable' => $observable]);
    $p1 = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $a->id, 'slot' => 1]);
    $p2 = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $solo ? null : $b->id, 'slot' => 2]);

    return [$game, $p1, $p2, $a, $b];
}

/** Put these cards on top of a player's deck, in this order (a stand-in for a lucky shuffle). */
function stackDeck(GamePlayer $player, array $top): void
{
    app(FateDeckService::class)->ensureDecks($player->game);
    $rest = GameFateCard::query()->where('game_player_id', $player->id)->where('zone', 'deck')->whereNotIn('card', $top)->orderBy('position')->get();
    $i = 0;
    foreach ($top as $card) {
        GameFateCard::query()->where('game_player_id', $player->id)->where('card', $card)->update(['zone' => 'deck', 'position' => $i++]);
    }
    foreach ($rest as $row) {
        $row->update(['position' => $i++]);
    }
}

function fateUrl(Game $game, string $name): string
{
    return route('games.fate.'.$name, $game->uuid);
}

function inZone(GamePlayer $player, string $zone): array
{
    return GameFateCard::query()->where('game_player_id', $player->id)->where('zone', $zone)->orderBy('position')->pluck('card')->all();
}

it('knows the cards: values, suits and the jokers', function () {
    expect(FateCards::value(0))->toBe(1)
        ->and(FateCards::value(12))->toBe(13)
        ->and(FateCards::suit(0))->toBe('rams')
        ->and(FateCards::suit(13))->toBe('masks')
        ->and(FateCards::suit(26))->toBe('tomes')
        ->and(FateCards::suit(51))->toBe('crows')
        ->and(FateCards::value(FateCards::RED_JOKER))->toBe(14)
        ->and(FateCards::value(FateCards::BLACK_JOKER))->toBe(0)
        ->and(FateCards::suit(FateCards::RED_JOKER))->toBeNull()
        ->and(FateCards::all())->toHaveCount(54);
});

it('is hidden until the flag is on', function () {
    Feature::for(null)->deactivate('experiment-tabletop');
    [$game, , , $a] = fateGame();

    $this->actingAs($a)->getJson(fateUrl($game, 'show'))->assertNotFound();
    $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 1])->assertNotFound();
});

it('creates each player a shuffled 54-card deck the first time it is used', function () {
    [$game, $p1, $p2, $a] = fateGame();

    $this->actingAs($a)->getJson(fateUrl($game, 'show'))->assertOk()->assertJsonPath('state.started', false);

    $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 5])->assertOk();

    foreach ([$p1, $p2] as $p) {
        expect(GameFateCard::query()->where('game_player_id', $p->id)->count())->toBe(54)
            ->and(GameFateCard::query()->where('game_player_id', $p->id)->pluck('card')->sort()->values()->all())->toBe(FateCards::all());
    }
    // the shuffle is not the card order
    expect(inZone($p2, 'deck'))->not->toBe(FateCards::all());
});

it('draws into the hand and tells the owner, and only the owner, which cards', function () {
    [$game, $p1, $p2, $a, $b] = fateGame();
    stackDeck($p1, [4, 17, 30, 52, 53]);

    $drawn = $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 3])->assertOk()
        ->assertJsonPath('drawn', [4, 17, 30])
        ->assertJsonPath('state.players.0.hand', [4, 17, 30])
        ->assertJsonPath('state.players.0.hand_count', 3)
        ->assertJsonPath('state.players.0.deck_count', 51);
    expect($drawn->json('state.players.1'))->not->toHaveKey('hand');

    // the opponent sees a size and nothing else of that hand
    $opponent = $this->actingAs($b)->getJson(fateUrl($game, 'show'))->assertOk();
    $opponent->assertJsonPath('state.players.0.hand_count', 3)->assertJsonPath('state.players.0.deck_count', 51);
    expect($opponent->json('state.players.0'))->not->toHaveKey('hand')
        ->and($opponent->json('state'))->not->toHaveKey('deck');
    expect(json_encode($opponent->json()))->not->toContain('"drawn"');
});

it('shows an observer the public cards and neither hand', function () {
    [$game, $p1, $p2, $a] = fateGame(observable: true);
    stackDeck($p1, [4, 17]);
    $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 2])->assertOk();
    $this->actingAs($a)->postJson(fateUrl($game, 'flip'), ['modifier' => 0])->assertOk();

    $observer = User::factory()->create();
    $response = $this->actingAs($observer)->getJson(fateUrl($game, 'show'))->assertOk();

    foreach ($response->json('state.players') as $p) {
        expect($p)->not->toHaveKey('hand');
    }
    expect($response->json('state.players.0.hand_count'))->toBe(2)
        ->and($response->json('state.conflict'))->toHaveCount(1);
    // an observer cannot act
    $this->actingAs($observer)->postJson(fateUrl($game, 'draw'), ['n' => 1])->assertForbidden();
});

it('keeps a game that is not observable from people outside it', function () {
    [$game] = fateGame();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->getJson(fateUrl($game, 'show'))->assertForbidden();
    $this->actingAs($stranger)->postJson(fateUrl($game, 'draw'), ['n' => 1])->assertForbidden();
});

it('needs a game in progress', function () {
    [$game, , , $a] = fateGame();
    $game->update(['status' => 'setup']);

    $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 1])->assertUnprocessable();
});

it('never writes a hidden card to the game log, but names the cards that go face up', function () {
    [$game, $p1, , $a] = fateGame();
    stackDeck($p1, [4, 17, 30]);
    $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 3])->assertOk();
    $this->actingAs($a)->postJson(fateUrl($game, 'discard'), ['cards' => [17]])->assertOk();
    $this->actingAs($a)->postJson(fateUrl($game, 'peek'), ['n' => 2])->assertOk();
    $this->actingAs($a)->postJson(fateUrl($game, 'search'))->assertOk();

    $draw = GameLog::query()->where('action', 'fate.draw')->first();
    expect($draw->payload)->toBe(['count' => 3]);
    expect(GameLog::query()->where('action', 'fate.discard')->first()->payload)->toBe(['cards' => [17]]);
    expect(GameLog::query()->where('action', 'fate.peek')->first()->payload)->toBe(['count' => 2]);
    expect(GameLog::query()->where('action', 'fate.search')->first()->payload)->toHaveKey('looked_through')->not->toHaveKey('cards');
});

it('discards from the hand face up, in order, and refuses a card that is not in the hand', function () {
    [$game, $p1, , $a] = fateGame();
    stackDeck($p1, [4, 17, 30]);
    $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 3])->assertOk();

    $this->actingAs($a)->postJson(fateUrl($game, 'discard'), ['cards' => [30, 4]])->assertOk()
        ->assertJsonPath('state.players.0.discard', [4, 30])
        ->assertJsonPath('state.players.0.hand', [17]);

    // a stale or forged request: that card is not in the hand any more
    $this->actingAs($a)->postJson(fateUrl($game, 'discard'), ['cards' => [4]])->assertUnprocessable();
    $this->actingAs($a)->postJson(fateUrl($game, 'discard'), ['cards' => [51]])->assertUnprocessable();
});

it('refills the hand to seven after the start phase discard', function () {
    [$game, $p1, , $a] = fateGame();
    $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 7])->assertOk();
    $hand = inZone($p1, 'hand');

    $response = $this->actingAs($a)->postJson(fateUrl($game, 'refill'), ['discard' => [$hand[0], $hand[1]]])->assertOk();
    expect($response->json('drawn'))->toHaveCount(2)
        ->and($response->json('state.players.0.hand_count'))->toBe(7)
        ->and($response->json('state.players.0.discard'))->toBe([$hand[0], $hand[1]]);

    // a full hand draws nothing
    $this->actingAs($a)->postJson(fateUrl($game, 'refill'), ['discard' => []])->assertOk()->assertJsonPath('drawn', []);
});

it('lets an effect draw past the hand maximum and reports how many must go', function () {
    [$game, , , $a] = fateGame();
    $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 7])->assertOk();
    $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 2])->assertOk()
        ->assertJsonPath('state.players.0.hand_count', 9)
        ->assertJsonPath('state.players.0.over_max', 2);
});

it('shuffles the discard pile in when the deck runs out mid-draw', function () {
    [$game, $p1, , $a] = fateGame();
    $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 20])->assertOk();
    $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 20])->assertOk();
    $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 14])->assertOk();
    expect(inZone($p1, 'deck'))->toBe([]);
    $this->actingAs($a)->postJson(fateUrl($game, 'discard'), ['cards' => array_slice(inZone($p1, 'hand'), 0, 6)])->assertOk();

    $response = $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 4])->assertOk();

    expect($response->json('drawn'))->toHaveCount(4)
        // the six discards were shuffled into the empty deck and four drawn
        ->and($response->json('state.players.0.discard'))->toBe([])
        ->and($response->json('state.players.0.deck_count'))->toBe(2);
});

it('flips one card to the conflict, and reveals one more for each plus up to four', function () {
    [$game, $p1, , $a] = fateGame();
    stackDeck($p1, [9, 3, 20, 40, 41, 42]);

    $one = $this->actingAs($a)->postJson(fateUrl($game, 'flip'), ['modifier' => 0, 'label' => 'Attack'])->assertOk();
    expect($one->json('state.conflict'))->toHaveCount(1)->and($one->json('state.conflict.0.card'))->toBe(9);
    $flip = $one->json('flip');
    $this->actingAs($a)->postJson(fateUrl($game, 'resolve'), ['flip' => $flip])->assertOk();

    $plus = $this->actingAs($a)->postJson(fateUrl($game, 'flip'), ['modifier' => 2])->assertOk();
    expect(collect($plus->json('state.conflict'))->pluck('card')->all())->toBe([3, 20, 40]);
    $this->actingAs($a)->postJson(fateUrl($game, 'resolve'), ['flip' => $plus->json('flip')])->assertOk();

    $max = $this->actingAs($a)->postJson(fateUrl($game, 'flip'), ['modifier' => 3])->assertOk();
    expect($max->json('state.conflict'))->toHaveCount(4);
    // a bigger modifier is refused, four cards is the limit
    $this->actingAs($a)->postJson(fateUrl($game, 'flip'), ['modifier' => 4])->assertUnprocessable();
});

it('lets a plus flip take any revealed card and a minus flip only the lowest', function () {
    [$game, $p1, , $a] = fateGame();
    stackDeck($p1, [9, 3, 20, 2, 16]);

    $plus = $this->actingAs($a)->postJson(fateUrl($game, 'flip'), ['modifier' => 2])->assertOk();
    expect($plus->json('state.flips.0.legal'))->toBe([9, 3, 20]);
    $this->actingAs($a)->postJson(fateUrl($game, 'choose'), ['flip' => $plus->json('flip'), 'card' => 20])->assertOk()
        ->assertJsonPath('state.flips.0.chosen.card', 20);
    $this->actingAs($a)->postJson(fateUrl($game, 'resolve'), ['flip' => $plus->json('flip')])->assertOk();

    $minus = $this->actingAs($a)->postJson(fateUrl($game, 'flip'), ['modifier' => -1])->assertOk();
    expect($minus->json('state.flips.0.legal'))->toBe([2]);
    $this->actingAs($a)->postJson(fateUrl($game, 'choose'), ['flip' => $minus->json('flip'), 'card' => 16])->assertUnprocessable();
    $this->actingAs($a)->postJson(fateUrl($game, 'choose'), ['flip' => $minus->json('flip'), 'card' => 2])->assertOk();
    // a result is chosen once
    $this->actingAs($a)->postJson(fateUrl($game, 'choose'), ['flip' => $minus->json('flip'), 'card' => 2])->assertUnprocessable();
});

it('offers every tied lowest card on a minus flip', function () {
    // 0 (the 1 of Rams) is alone at the bottom; 2 and 15 (the 3 of Rams and the 3 of Masks) tie
    expect(FateDeckService::legalChoices(-1, [2, 15, 5]))->toBe([2, 15])
        ->and(FateDeckService::legalChoices(-2, [26, 39]))->toBe([26, 39]);
});

it('makes a Black Joker the result even with a plus, and lets a Red Joker be taken even with a minus', function () {
    expect(FateDeckService::legalChoices(2, [8, FateCards::BLACK_JOKER, 30]))->toBe([FateCards::BLACK_JOKER])
        ->and(FateDeckService::legalChoices(-1, [8, FateCards::RED_JOKER]))->toBe([8, FateCards::RED_JOKER])
        ->and(FateDeckService::legalChoices(-2, [8, FateCards::RED_JOKER, FateCards::BLACK_JOKER]))->toBe([FateCards::BLACK_JOKER])
        ->and(FateDeckService::legalChoices(2, [8, FateCards::RED_JOKER]))->toBe([8, FateCards::RED_JOKER]);
});

it('gives a chosen Red Joker the suit its owner names, and refuses it without one', function () {
    [$game, $p1, , $a] = fateGame();
    stackDeck($p1, [FateCards::RED_JOKER]);
    $flip = $this->actingAs($a)->postJson(fateUrl($game, 'flip'), ['modifier' => 0])->json('flip');

    $this->actingAs($a)->postJson(fateUrl($game, 'choose'), ['flip' => $flip, 'card' => FateCards::RED_JOKER])->assertUnprocessable();
    $this->actingAs($a)->postJson(fateUrl($game, 'choose'), ['flip' => $flip, 'card' => FateCards::RED_JOKER, 'suit' => 'crows'])->assertOk()
        ->assertJsonPath('state.flips.0.chosen.joker_suit', 'crows');
    $this->actingAs($a)->postJson(fateUrl($game, 'choose'), ['flip' => $flip, 'card' => FateCards::RED_JOKER, 'suit' => 'banana'])->assertUnprocessable();
});

it('cheats fate: the hand card replaces the result and the old one is discarded', function () {
    [$game, $p1, , $a] = fateGame();
    stackDeck($p1, [5, 30, 40]);
    $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 2])->assertOk();   // hand 5, 30
    $flip = $this->actingAs($a)->postJson(fateUrl($game, 'flip'), ['modifier' => 0])->json('flip'); // 40
    $this->actingAs($a)->postJson(fateUrl($game, 'choose'), ['flip' => $flip, 'card' => 40])->assertOk();

    $response = $this->actingAs($a)->postJson(fateUrl($game, 'cheat'), ['flip' => $flip, 'card' => 30])->assertOk();

    expect($response->json('state.flips.0.chosen.card'))->toBe(30)
        ->and($response->json('state.players.0.discard'))->toBe([40])
        ->and($response->json('state.players.0.hand'))->toBe([5])
        ->and(collect($response->json('state.conflict'))->pluck('card')->all())->toBe([30]);
    // one chance per flip
    $this->actingAs($a)->postJson(fateUrl($game, 'cheat'), ['flip' => $flip, 'card' => 5])->assertUnprocessable();
});

it('needs a result before cheating, and a card that is in the hand', function () {
    [$game, $p1, , $a] = fateGame();
    stackDeck($p1, [5, 40]);
    $flip = $this->actingAs($a)->postJson(fateUrl($game, 'flip'), ['modifier' => 0])->json('flip');

    $this->actingAs($a)->postJson(fateUrl($game, 'cheat'), ['flip' => $flip, 'card' => 40])->assertUnprocessable();
    $this->actingAs($a)->postJson(fateUrl($game, 'choose'), ['flip' => $flip, 'card' => 5])->assertOk();
    $this->actingAs($a)->postJson(fateUrl($game, 'cheat'), ['flip' => $flip, 'card' => 40])->assertUnprocessable();
});

it('cannot cheat a flipped Black Joker, and a Red Joker stops the flipper cheating their own flip but not the opponent', function () {
    [$game, $p1, $p2, $a, $b] = fateGame();
    stackDeck($p1, [FateCards::BLACK_JOKER, FateCards::RED_JOKER, 20]);
    stackDeck($p2, [7, 8, 9]);
    $this->actingAs($b)->postJson(fateUrl($game, 'draw'), ['n' => 2])->assertOk(); // b's hand: 7, 8

    $black = $this->actingAs($a)->postJson(fateUrl($game, 'flip'), ['modifier' => 0])->json('flip');
    $this->actingAs($a)->postJson(fateUrl($game, 'choose'), ['flip' => $black, 'card' => FateCards::BLACK_JOKER])->assertOk();
    $this->actingAs($b)->postJson(fateUrl($game, 'cheat'), ['flip' => $black, 'card' => 7])->assertUnprocessable();
    $this->actingAs($a)->postJson(fateUrl($game, 'resolve'), ['flip' => $black])->assertOk();

    $red = $this->actingAs($a)->postJson(fateUrl($game, 'flip'), ['modifier' => 0])->json('flip');
    $this->actingAs($a)->postJson(fateUrl($game, 'choose'), ['flip' => $red, 'card' => FateCards::RED_JOKER, 'suit' => 'rams'])->assertOk();
    // the Red Joker stops a cheating their own flip (a has nothing in hand, but the rule is checked first)
    $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 1])->assertOk();
    $this->actingAs($a)->postJson(fateUrl($game, 'cheat'), ['flip' => $red, 'card' => inZone($p1, 'hand')[0]])->assertUnprocessable()
        ->assertJsonPath('error', 'A Red Joker stops you cheating your own flip until it is discarded.');

    // the opponent cheats from their own hand; the old card goes to the flipper's discard pile
    $response = $this->actingAs($b)->postJson(fateUrl($game, 'cheat'), ['flip' => $red, 'card' => 7])->assertOk();
    expect(inZone($p1, 'discard'))->toContain(FateCards::RED_JOKER)
        ->and(inZone($p2, 'hand'))->toBe([8])
        ->and($response->json('state.flips.0.chosen.owner_slot'))->toBe(2);
});

it('resolves a flip by discarding what is left in the conflict to each owner, and refuses a second resolve', function () {
    [$game, $p1, $p2, $a, $b] = fateGame();
    stackDeck($p1, [9, 3, 20]);
    stackDeck($p2, [7, 8]);
    $this->actingAs($b)->postJson(fateUrl($game, 'draw'), ['n' => 1])->assertOk();
    $flip = $this->actingAs($a)->postJson(fateUrl($game, 'flip'), ['modifier' => 2])->json('flip');
    $this->actingAs($a)->postJson(fateUrl($game, 'choose'), ['flip' => $flip, 'card' => 9])->assertOk();
    $this->actingAs($b)->postJson(fateUrl($game, 'cheat'), ['flip' => $flip, 'card' => 7])->assertOk();

    // only the flipper resolves
    $this->actingAs($b)->postJson(fateUrl($game, 'resolve'), ['flip' => $flip])->assertUnprocessable();
    $response = $this->actingAs($a)->postJson(fateUrl($game, 'resolve'), ['flip' => $flip])->assertOk();

    expect($response->json('state.conflict'))->toBe([])
        ->and($response->json('state.flips'))->toBe([])
        ->and(inZone($p1, 'discard'))->toEqualCanonicalizing([9, 3, 20])
        ->and(inZone($p2, 'discard'))->toBe([7]);
    $this->actingAs($a)->postJson(fateUrl($game, 'resolve'), ['flip' => $flip])->assertUnprocessable();
});

it('lets a player pass on a chance to cheat', function () {
    [$game, $p1, , $a, $b] = fateGame();
    stackDeck($p1, [9]);
    $flip = $this->actingAs($a)->postJson(fateUrl($game, 'flip'), ['modifier' => 0])->json('flip');

    $this->actingAs($b)->postJson(fateUrl($game, 'cheat.decline'), ['flip' => $flip])->assertOk()
        ->assertJsonPath('state.flips.0.cheaters', [2]);
});

it('looks at the top cards for the owner alone, and the log says only that it did', function () {
    [$game, $p1, , $a, $b] = fateGame();
    stackDeck($p1, [11, 22, 33, 44]);

    $this->actingAs($a)->postJson(fateUrl($game, 'peek'), ['n' => 3])->assertOk()->assertJsonPath('peeked', [11, 22, 33]);

    // the opponent's request acts on their own deck, not a's
    $theirs = $this->actingAs($b)->postJson(fateUrl($game, 'peek'), ['n' => 3])->assertOk();
    expect($theirs->json('peeked'))->not->toBe([11, 22, 33]);
    // peeking moves nothing
    expect(inZone($p1, 'deck'))->toHaveCount(54);
});

it('arranges the peeked cards: top in order, bottom, discard and hand, and nothing else about the order is told', function () {
    [$game, $p1, , $a] = fateGame();
    stackDeck($p1, [11, 22, 33, 44, 5]);

    $response = $this->actingAs($a)->postJson(fateUrl($game, 'arrange'), ['decisions' => [
        ['card' => 11, 'to' => 'bottom'],
        ['card' => 22, 'to' => 'top'],
        ['card' => 33, 'to' => 'discard'],
        ['card' => 44, 'to' => 'hand'],
    ]])->assertOk();

    // the card put on top is on top, 5 is next, and 11 is last; 33 is face up in the pile and 44 is in the hand
    expect(array_slice(inZone($p1, 'deck'), 0, 2))->toBe([22, 5])
        ->and(last(inZone($p1, 'deck')))->toBe(11)
        ->and($response->json('state.players.0.discard'))->toBe([33])
        ->and($response->json('state.players.0.hand'))->toBe([44]);
    expect($response->json('state'))->not->toHaveKey('deck');
    expect(GameLog::query()->where('action', 'fate.arrange')->first()->payload)->toBe(['looked_at' => 4, 'discarded' => [33], 'to_hand' => 1, 'top' => 1, 'bottom' => 1]);
});

it('refuses to arrange cards that are not the top of the deck', function () {
    [$game, $p1, , $a] = fateGame();
    stackDeck($p1, [11, 22, 33]);

    $this->actingAs($a)->postJson(fateUrl($game, 'arrange'), ['decisions' => [['card' => 22, 'to' => 'top'], ['card' => 11, 'to' => 'top']]])->assertOk();
    // 33 is now third, so naming 33 with one other card is not the top two
    $this->actingAs($a)->postJson(fateUrl($game, 'arrange'), ['decisions' => [['card' => 33, 'to' => 'top'], ['card' => 11, 'to' => 'top']]])->assertUnprocessable();
    $this->actingAs($a)->postJson(fateUrl($game, 'arrange'), ['decisions' => [['card' => 22, 'to' => 'banana']]])->assertUnprocessable();
    expect(array_slice(inZone($p1, 'deck'), 0, 3))->toBe([22, 11, 33]);
});

it('puts a hand or discard card on the top or bottom of the deck, and draws a named card back from the pile', function () {
    [$game, $p1, , $a] = fateGame();
    stackDeck($p1, [11, 22, 33]);
    $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 3])->assertOk();
    $this->actingAs($a)->postJson(fateUrl($game, 'discard'), ['cards' => [22]])->assertOk();

    $this->actingAs($a)->postJson(fateUrl($game, 'put'), ['card' => 11, 'where' => 'top'])->assertOk();
    $this->actingAs($a)->postJson(fateUrl($game, 'put'), ['card' => 22, 'where' => 'bottom'])->assertOk();
    expect(inZone($p1, 'deck')[0])->toBe(11)->and(last(inZone($p1, 'deck')))->toBe(22);

    // the pile is public: a card leaving it is named in the log; one leaving the hand is not
    $this->actingAs($a)->postJson(fateUrl($game, 'discard'), ['cards' => [33]])->assertOk();
    $this->actingAs($a)->postJson(fateUrl($game, 'draw_from_discard'), ['card' => 33])->assertOk()->assertJsonPath('state.players.0.hand', [33]);
    expect(GameLog::query()->where('action', 'fate.draw_from_discard')->first()->payload)->toBe(['card' => 33]);
    $this->actingAs($a)->postJson(fateUrl($game, 'draw_from_discard'), ['card' => 33])->assertUnprocessable(); // no longer in the pile
});

it('searches the deck in card order and shuffles after taking a card', function () {
    [$game, $p1, , $a] = fateGame();
    stackDeck($p1, [30, 20, 10]);

    $view = $this->actingAs($a)->postJson(fateUrl($game, 'search'))->assertOk();
    // card order, never deck order
    expect($view->json('deck'))->toBe(FateCards::all());

    $this->actingAs($a)->postJson(fateUrl($game, 'search.take'), ['card' => 20])->assertOk()->assertJsonPath('state.players.0.hand', [20]);
    expect(inZone($p1, 'deck'))->toHaveCount(53)->and(inZone($p1, 'deck'))->not->toContain(20);
    $this->actingAs($a)->postJson(fateUrl($game, 'search.take'), ['card' => 20])->assertUnprocessable(); // already taken
    expect(GameLog::query()->where('action', 'fate.search_take')->first()->payload)->toBeNull();
});

it('shuffles the discard pile into the deck but leaves cards removed from the game out', function () {
    [$game, $p1, , $a] = fateGame();
    stackDeck($p1, [1, 2, 3, 4]);
    $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 4])->assertOk();
    $this->actingAs($a)->postJson(fateUrl($game, 'discard'), ['cards' => [1, 2, 3]])->assertOk();
    $this->actingAs($a)->postJson(fateUrl($game, 'remove'), ['cards' => [3]])->assertOk()->assertJsonPath('state.players.0.removed', [3]);

    $response = $this->actingAs($a)->postJson(fateUrl($game, 'shuffle'))->assertOk();

    expect($response->json('state.players.0.discard'))->toBe([])
        ->and($response->json('state.players.0.deck_count'))->toBe(52)
        ->and(inZone($p1, 'deck'))->toContain(1)->toContain(2)->not->toContain(3)->not->toContain(4)
        ->and(inZone($p1, 'removed'))->toBe([3])
        ->and(inZone($p1, 'hand'))->toBe([4]);
});

it('shuffles every discard pile back at the end phase and leaves the hands alone', function () {
    [$game, $p1, $p2, $a] = fateGame();
    $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 4])->assertOk();
    $hand = inZone($p1, 'hand');
    $this->actingAs($a)->postJson(fateUrl($game, 'discard'), ['cards' => [$hand[0]]])->assertOk();

    $this->actingAs($a)->postJson(fateUrl($game, 'end_phase'))->assertOk();

    expect(inZone($p1, 'discard'))->toBe([])->and(inZone($p1, 'deck'))->toHaveCount(51)->and(inZone($p1, 'hand'))->toHaveCount(3);
});

it('lets the creator of a solo game act as either slot and see both hands, and nobody else', function () {
    [$game, $p1, $p2, $a, $b] = fateGame(solo: true);
    stackDeck($p2, [6, 7]);

    $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 1, 'slot' => 1])->assertOk();
    $response = $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 2, 'slot' => 2])->assertOk()->assertJsonPath('drawn', [6, 7]);

    expect($response->json('state.players.0.hand'))->toHaveCount(1)->and($response->json('state.players.1.hand'))->toBe([6, 7]);
    $this->actingAs($b)->postJson(fateUrl($game, 'draw'), ['n' => 1, 'slot' => 2])->assertForbidden();
});

it('refuses a flip that belongs to another game', function () {
    [$game, $p1, , $a] = fateGame();
    [$other, $o1, , $oa] = fateGame();
    stackDeck($o1, [9]);
    $foreign = $this->actingAs($oa)->postJson(fateUrl($other, 'flip'), ['modifier' => 0])->json('flip');

    $this->actingAs($a)->postJson(fateUrl($game, 'choose'), ['flip' => $foreign, 'card' => 9])->assertNotFound();
});

it('validates what it is sent', function () {
    [$game, , , $a] = fateGame();

    $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 0])->assertUnprocessable();
    $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 21])->assertUnprocessable();
    $this->actingAs($a)->postJson(fateUrl($game, 'discard'), ['cards' => [54]])->assertUnprocessable();
    $this->actingAs($a)->postJson(fateUrl($game, 'discard'), ['cards' => []])->assertUnprocessable();
    $this->actingAs($a)->postJson(fateUrl($game, 'put'), ['card' => 3, 'where' => 'middle'])->assertUnprocessable();
    $this->actingAs($a)->postJson(fateUrl($game, 'flip'), ['modifier' => 'lots'])->assertUnprocessable();
});

it('keeps a game\'s cards when a card is moved, one row per card with no copies', function () {
    [$game, $p1, , $a] = fateGame();
    $this->actingAs($a)->postJson(fateUrl($game, 'draw'), ['n' => 10])->assertOk();
    $this->actingAs($a)->postJson(fateUrl($game, 'flip'), ['modifier' => 2])->assertOk();

    expect(GameFateCard::query()->where('game_player_id', $p1->id)->count())->toBe(54)
        ->and(GameFateCard::query()->where('game_player_id', $p1->id)->where('zone', FateZoneEnum::Hand->value)->count())->toBe(10);
});
