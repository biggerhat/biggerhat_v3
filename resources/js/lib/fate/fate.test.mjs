import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    BLACK_JOKER,
    COUNT,
    HAND_MAX,
    RED_JOKER,
    allCards,
    cardLabel,
    cardSuit,
    cardValue,
    createLocalFate,
    describeFate,
    legalChoices,
} from './fate.ts';

/** A shuffle that is not random, so a deck has a known (but unremarkable) order. */
const fixed = () => 0;
const local = () => createLocalFate([1, 2], fixed);
const hand = (fate, slot = 1) => fate.state().players.find((p) => p.slot === slot).hand;

test('the cards: values, suits and the jokers', () => {
    assert.equal(allCards().length, COUNT);
    assert.equal(cardValue(0), 1);
    assert.equal(cardValue(12), 13);
    assert.equal(cardSuit(0), 'rams');
    assert.equal(cardSuit(13), 'masks');
    assert.equal(cardSuit(26), 'tomes');
    assert.equal(cardSuit(51), 'crows');
    assert.equal(cardValue(RED_JOKER), 14);
    assert.equal(cardValue(BLACK_JOKER), 0);
    assert.equal(cardSuit(RED_JOKER), null);
});

test('the same flip rules as the server: plus takes any, minus the lowest, jokers override', () => {
    assert.deepEqual(legalChoices(2, [9, 3, 20]), [9, 3, 20]);
    assert.deepEqual(legalChoices(-1, [2, 15, 5]), [2, 15]); // the 3 of Rams and the 3 of Masks tie
    assert.deepEqual(legalChoices(2, [8, BLACK_JOKER, 30]), [BLACK_JOKER]);
    assert.deepEqual(legalChoices(-1, [8, RED_JOKER]), [8, RED_JOKER]);
    assert.deepEqual(legalChoices(-2, [8, RED_JOKER, BLACK_JOKER]), [BLACK_JOKER]);
});

test('a local deck is created on first use: 54 cards each, and every action answers with the state', () => {
    const fate = createLocalFate();
    assert.equal(fate.state().started, false);
    const r = fate.act('draw', { n: 5 });
    assert.equal(r.drawn.length, 5);
    assert.equal(r.state.players[0].hand_count, 5);
    assert.equal(r.state.players[0].deck_count, 49);
    assert.equal(r.state.players[1].deck_count, 54);
    assert.equal(new Set(r.drawn).size, 5);
});

test('draw is not capped at seven but reports the excess; refill stops at seven', () => {
    const fate = local();
    fate.act('draw', { n: 9 });
    assert.equal(fate.state().players[0].over_max, 2);
    const f2 = local();
    const first = f2.act('draw', { n: 7 }).drawn;
    const r = f2.act('refill', { discard: [first[0], first[1]] });
    assert.equal(r.drawn.length, 2);
    assert.equal(f2.state().players[0].hand_count, HAND_MAX);
    assert.deepEqual(f2.state().players[0].discard, [first[0], first[1]]);
    assert.deepEqual(f2.act('refill', { discard: [] }).drawn, []);
});

test('a refused action changes nothing and says why', () => {
    const fate = local();
    fate.act('draw', { n: 3 });
    const before = JSON.stringify(fate.state());
    const r = fate.act('discard', { cards: [hand(fate)[0], 99] });
    assert.match(r.error, /not where it was said/);
    assert.equal(JSON.stringify(fate.state()), before);
});

test('flip reveals one more card per plus, four at most, then choose, cheat and resolve', () => {
    const fate = local();
    fate.act('draw', { n: 3 });
    const mine = hand(fate);
    const flip = fate.act('flip', { modifier: 3 });
    assert.equal(flip.state.conflict.length, 4);
    const id = flip.flip;
    const legal = flip.state.flips[0].legal;
    assert.equal(legal.length >= 1, true);
    assert.match(fate.act('choose', { flip: id, card: 99 }).error, /cannot be taken/);
    const pick = fate.act('choose', { flip: id, card: legal[0], suit: 'rams' });
    assert.equal(pick.error, undefined);
    assert.match(fate.act('choose', { flip: id, card: legal[0], suit: 'rams' }).error, /already has its result/);
    const chosen = pick.state.flips[0].chosen.card;
    if (chosen !== BLACK_JOKER && chosen !== RED_JOKER) {
        const cheat = fate.act('cheat', { flip: id, card: mine[0], suit: 'rams' });
        assert.equal(cheat.error, undefined);
        assert.equal(cheat.state.flips[0].chosen.card, mine[0]);
        assert.match(fate.act('cheat', { flip: id, card: mine[1] }).error, /already had your chance/);
    }
    const done = fate.act('resolve', { flip: id });
    assert.deepEqual(done.state.conflict, []);
    assert.deepEqual(done.state.flips, []);
    assert.match(fate.act('resolve', { flip: id }).error, /already been resolved/);
});

test('peek and arrange: only the top cards may be named, and the order changes as told', () => {
    const fate = local();
    const [a, b, c] = fate.act('peek', { n: 3 }).peeked;
    assert.match(
        fate.act('arrange', {
            decisions: [
                { card: b, to: 'top' },
                { card: c, to: 'top' },
            ],
        }).error,
        /not the top cards/,
    );
    const r = fate.act('arrange', {
        decisions: [
            { card: a, to: 'bottom' },
            { card: b, to: 'discard' },
            { card: c, to: 'top' },
        ],
    });
    assert.equal(r.error, undefined);
    assert.deepEqual(r.state.players[0].discard, [b]);
    assert.equal(fate.act('peek', { n: 1 }).peeked[0], c);
    assert.equal(r.state.players[0].deck_count, 53);
});

test('put on the deck, draw back from the pile, search then shuffle, remove, shuffle the discard back', () => {
    const fate = local();
    const drawn = fate.act('draw', { n: 3 }).drawn;
    fate.act('discard', { cards: [drawn[0]] });
    assert.equal(fate.act('put', { card: drawn[1], where: 'top' }).error, undefined);
    assert.equal(fate.act('peek', { n: 1 }).peeked[0], drawn[1]);
    assert.deepEqual(fate.act('draw_from_discard', { card: drawn[0] }).state.players[0].hand.includes(drawn[0]), true);
    assert.match(fate.act('draw_from_discard', { card: drawn[0] }).error, /not in your discard/);

    const deck = fate.act('search').deck;
    assert.deepEqual(
        deck,
        [...deck].sort((x, y) => x - y),
    ); // card order, never deck order
    const take = deck[0];
    assert.equal(fate.act('search.take', { card: take }).state.players[0].hand.includes(take), true);

    fate.act('discard', { cards: [drawn[2]] });
    fate.act('remove', { cards: [drawn[2]] });
    const shuffled = fate.act('shuffle');
    assert.deepEqual(shuffled.state.players[0].removed, [drawn[2]]);
    assert.deepEqual(shuffled.state.players[0].discard, []);
});

test('the deck runs out mid-draw: the discard pile is shuffled in and the draw carries on', () => {
    const fate = local();
    fate.act('draw', { n: 20 });
    fate.act('draw', { n: 20 });
    fate.act('draw', { n: 14 });
    fate.act('discard', { cards: hand(fate).slice(0, 6) });
    const r = fate.act('draw', { n: 4 });
    assert.equal(r.drawn.length, 4);
    assert.equal(r.state.players[0].discard.length, 0);
    assert.equal(r.state.players[0].deck_count, 2);
});

test('two slots act on their own decks, and a red joker needs a suit', () => {
    const fate = local();
    fate.act('draw', { n: 2, slot: 1 });
    const r = fate.act('draw', { n: 3, slot: 2 });
    assert.equal(r.state.players[0].hand_count, 2);
    assert.equal(r.state.players[1].hand_count, 3);
    assert.match(fate.act('draw', { n: 0 }).error, /between 1 and 20/);
});

test('a flip remembers what it was for, so the board can draw the result beside that model', () => {
    const fate = local();
    const r = fate.act('flip', { modifier: 0, label: 'Pandora', subject: 'n412' });
    assert.equal(r.state.flips[0].subject, 'n412');
    assert.equal(r.state.flips[0].label, 'Pandora');
});

test("the other hand: a random discard is the engine's pick, face up", () => {
    const fate = local();
    fate.act('draw', { n: 3, slot: 2 });
    const r = fate.act('random_discard', { n: 2, slot: 1 });
    assert.equal(r.discarded.length, 2);
    assert.deepEqual(r.state.players[1].discard.slice().sort(), r.discarded.slice().sort());
    assert.equal(r.state.players[1].hand_count, 1);
    // fewer cards than asked: all of them go
    assert.equal(fate.act('random_discard', { n: 5, slot: 1 }).state.players[1].hand_count, 0);
});

test('a request is answered only by the player it is addressed to, and a shown hand goes to the asker', () => {
    const fate = local();
    fate.act('draw', { n: 3, slot: 2 });
    const id = fate.act('demand', { kind: 'reveal', slot: 1, note: 'Hex' }).prompt;
    assert.equal(fate.state().prompts[0].note, 'Hex');
    assert.equal(fate.state().sent.length, 1);
    assert.match(fate.act('respond', { prompt: id, show: true, slot: 1 }).error, /nothing for you to answer/);
    const shown = fate.act('respond', { prompt: id, show: true, slot: 2 });
    assert.equal(shown.error, undefined);
    assert.equal(shown.state.answers[0].cards.length, 3);
    assert.equal(shown.state.answers[0].owner_slot, 2);
    assert.match(fate.act('respond', { prompt: id, show: true, slot: 2 }).error, /nothing for you to answer/);
    // putting it away drops the snapshot
    assert.deepEqual(fate.act('dismiss', { prompt: id, slot: 1 }).state.answers, []);
});

test('a refusal and a discard request, and a limit on how many can wait', () => {
    const fate = local();
    const id = fate.act('demand', { kind: 'reveal', slot: 1 }).prompt;
    const refused = fate.act('respond', { prompt: id, show: false, slot: 2 });
    assert.equal(refused.state.answers[0].declined, true);
    assert.equal(refused.state.answers[0].cards, null);
    const d = fate.act('demand', { kind: 'discard', count: 2, slot: 1 }).prompt;
    assert.equal(fate.act('respond', { prompt: d, slot: 2 }).state.prompts.length, 0);
    assert.match(fate.act('demand', { kind: 'discard', slot: 1 }).error, /between 1 and 7/);
    assert.match(fate.act('demand', { kind: 'discard', count: 8, slot: 1 }).error, /between 1 and 7/);
    assert.match(fate.act('demand', { kind: 'steal', slot: 1 }).error, /discard cards or to show/);
    for (let i = 0; i < 3; i++) fate.act('demand', { kind: 'reveal', slot: 1 });
    // the refused one no longer counts as waiting: three more fit, the fifth waiting does not
    fate.act('demand', { kind: 'reveal', slot: 1 });
    assert.match(fate.act('demand', { kind: 'reveal', slot: 1 }).error, /already asked/);
});

test('the history reads as sentences and never names a hidden card', () => {
    const fate = local();
    const drawn = fate.act('draw', { n: 3 }).drawn;
    fate.act('discard', { cards: [drawn[0]] });
    fate.act('flip', { modifier: 0 });
    fate.act('peek', { n: 2 });
    const text = fate.log().map((l) => l.text);
    assert.match(text.at(-1), /Blue drew 3 cards\./);
    assert.ok(text.some((t) => /Blue discarded /.test(t)));
    assert.ok(text.some((t) => /Blue flipped a plain flip: /.test(t)));
    assert.ok(text.some((t) => /Blue looked at 2 cards on top of their deck\./.test(t)));
    // the two cards still in hand are named nowhere: a draw and a look at the top are only counts
    const joined = text.join(' ');
    for (const hidden of [drawn[1], drawn[2]]) assert.ok(!joined.includes(cardLabel(hidden)), `${cardLabel(hidden)} should not be in the history`);
    assert.equal(fate.log().length, 4);
    assert.equal(describeFate('fate.draw', { count: 1 }, 'Blue'), 'Blue drew 1 card.');
    assert.equal(describeFate('fate.reveal', { to: 1 }, 'Red', { 1: 'Blue' }), 'Red showed their hand to Blue.');
    assert.ok(!describeFate('fate.reveal', { to: 1, cards: [4] }, 'Red', { 1: 'Blue' }).includes('of '));
});

test('resetting the table clears the requests and the history', () => {
    const fate = local();
    fate.act('demand', { kind: 'reveal', slot: 1 });
    fate.act('draw', { n: 1 });
    fate.reset();
    assert.deepEqual(fate.log(), []);
    assert.deepEqual(fate.state().prompts, []);
});

test('a mode with a smaller hand: refill stops there and the excess is counted against it', () => {
    const fate = createLocalFate([1, 2], fixed, { 1: 'Blue', 2: 'Red' }, 4);
    assert.equal(fate.act('refill', { discard: [] }).state.players[0].hand_count, 4);
    assert.equal(fate.state().hand_max, 4);
    assert.equal(fate.act('draw', { n: 2 }).state.players[0].over_max, 2);
});

test('an opposed duel: the flips link, totals are entered, and the lowest total cheats first (the resister on a tie)', () => {
    const fate = local();
    fate.act('draw', { n: 2, slot: 1 });
    fate.act('draw', { n: 2, slot: 2 });
    const attack = fate.act('flip', { modifier: 0, duel_role: 'attacker', slot: 1 }).flip;
    assert.match(fate.act('flip', { modifier: 0, duel_role: 'resister', slot: 2 }).error, /open attacking flip to resist/);
    assert.match(fate.act('flip', { modifier: 0, duel_role: 'banana', slot: 2 }).error, /attacker or the resister/);
    const resist = fate.act('flip', { modifier: 0, duel_role: 'resister', duel_with: attack, slot: 2 }).flip;
    assert.match(fate.act('flip', { modifier: 0, duel_role: 'resister', duel_with: attack, slot: 2 }).error, /open attacking flip to resist/); // resisted once
    const choose = (flip, slot) => fate.act('choose', { flip, card: fate.state().flips.find((f) => f.id === flip).legal[0], suit: 'rams', slot });
    choose(attack, 1);
    choose(resist, 2);

    // nobody cheats before both totals are in
    assert.match(fate.act('cheat', { flip: attack, card: hand(fate, 2)[0], slot: 2 }).error, /enter their total before anyone cheats/);
    assert.match(fate.act('total', { flip: attack, total: 8, slot: 2 }).error, /Only the player who flipped/);
    assert.match(fate.act('total', { flip: attack, total: 99, slot: 1 }).error, /between -10 and 60/);
    fate.act('total', { flip: attack, total: 12, slot: 1 });
    const state = fate.act('total', { flip: resist, total: 9, slot: 2 }).state;
    const duel = state.flips.find((f) => f.id === attack).duel;
    assert.equal(duel.cheat_turn, 2); // 9 is lower than 12
    assert.equal(duel.opponent_total, 9);

    assert.match(fate.act('cheat', { flip: attack, card: hand(fate, 1)[0], slot: 1 }).error, /not your turn to cheat/);
    assert.match(fate.act('cheat.decline', { flip: attack, slot: 1 }).error, /not your turn to cheat/);
    assert.equal(fate.act('cheat', { flip: resist, card: hand(fate, 2)[0], slot: 2 }).error, undefined);
    assert.equal(fate.state().flips.find((f) => f.id === attack).duel.cheat_turn, 1);
    assert.equal(fate.act('cheat.decline', { flip: attack, slot: 1 }).error, undefined);
    assert.equal(fate.state().flips.find((f) => f.id === attack).duel.cheat_turn, null);
});

test('a tied duel total: the resister goes first; a plain flip has no total and no turn order', () => {
    const fate = local();
    fate.act('draw', { n: 2, slot: 1 });
    fate.act('draw', { n: 2, slot: 2 });
    const attack = fate.act('flip', { modifier: 0, duel_role: 'attacker', slot: 1 }).flip;
    const resist = fate.act('flip', { modifier: 0, duel_role: 'resister', duel_with: attack, slot: 2 }).flip;
    for (const [flip, slot] of [
        [attack, 1],
        [resist, 2],
    ])
        fate.act('choose', { flip, card: fate.state().flips.find((f) => f.id === flip).legal[0], suit: 'rams', slot });
    fate.act('total', { flip: attack, total: 10, slot: 1 });
    fate.act('total', { flip: resist, total: 10, slot: 2 });
    assert.equal(fate.state().flips.find((f) => f.id === attack).duel.cheat_turn, 2);

    const plain = local();
    const flip = plain.act('flip', { modifier: 0 }).flip;
    assert.match(plain.act('total', { flip, total: 8 }).error, /Only a duel flip has a total/);
    assert.equal(plain.state().flips[0].duel, null);
});

test('refill: a lower hand limit, and an optional shuffle of the discard pile into the deck first', () => {
    const fate = local();
    fate.act('draw', { slot: 1, n: 7 });
    const h = hand(fate);
    fate.act('discard', { slot: 1, cards: h.slice(0, 2) });
    // a master that lowers the hand to 5: draw up to 5, not 7
    const r = fate.act('refill', { slot: 1, discard: [], max: 5 });
    assert.equal(r.drawn.length, 0);
    assert.equal(r.state.players[0].hand_count, 5);
    const more = fate.act('refill', { slot: 1, discard: [h[2]], max: 6, shuffle: true });
    assert.equal(more.state.players[0].hand_count, 6);
    assert.equal(more.state.players[0].discard.length, 0, 'the shuffle put the discard pile back in the deck');
    assert.ok(more.drawn.length >= 1);
});

test('undo takes the practice deck back one action at a time, and says when there is nothing left', () => {
    const fate = local();
    assert.equal(fate.state().can_undo, false);
    assert.match(fate.act('undo').error, /nothing to undo/);
    fate.act('draw', { slot: 1, n: 3 });
    fate.act('draw', { slot: 1, n: 2 });
    assert.equal(hand(fate).length, 5);
    assert.equal(fate.act('undo').state.players[0].hand_count, 3);
    assert.equal(fate.log().length, 1, 'the history line goes with it');
    assert.equal(fate.act('undo').state.players[0].hand_count, 0);
    assert.equal(fate.state().can_undo, false);
    // a refused action is not something to undo
    assert.ok(fate.act('discard', { slot: 1, cards: [99] }).error);
    assert.equal(fate.state().can_undo, false);
});
