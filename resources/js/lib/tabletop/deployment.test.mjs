import assert from 'node:assert/strict';
import { test } from 'node:test';
import { deployWords } from './deployment.ts';

const players = [
    { slot: 1, name: 'Ann', mine: true },
    { slot: 2, name: 'Bo', mine: false },
];
const state = (phase, actor_slot) => ({ phase, actor_slot, placing: [], later: [], first: null, skipped: [], defender_slot: 2, attacker_slot: 1 });

test('each phase names who acts and says whether it is the viewer', () => {
    assert.deepEqual(deployWords(state('choose', 1), players).yourTurn, true);
    assert.match(deployWords(state('choose', 1), players).headline, /^You choose/);
    assert.equal(deployWords(state('split', 2), players).yourTurn, false);
    assert.match(deployWords(state('split', 2), players).headline, /^Bo divides/);
    assert.match(deployWords(state('attacker', 1), players).headline, /whole crew/);
    assert.match(deployWords(state('first', 2), players).headline, /first group/);
    assert.match(deployWords(state('second', 2), players).headline, /rest of the crew/);
});

test('a finished deployment points on to the schemes', () => {
    const words = deployWords(state('done', null), players);
    assert.equal(words.yourTurn, false);
    assert.match(words.headline, /scheme/);
});
