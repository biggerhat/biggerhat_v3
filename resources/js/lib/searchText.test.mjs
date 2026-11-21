import assert from 'node:assert/strict';
import { test } from 'node:test';
import { normalizeText, sameText, textHas } from './searchText.ts';

test('apostrophes of every kind, or none, match each other', () => {
    const card = 'Deal damage equal to this action’s damage';
    for (const typed of [
        "this action's damage",
        'this action’s damage',
        'this action‘s damage',
        'this action`s damage',
        'this actions damage',
        "THIS  ACTION'S   DAMAGE",
    ])
        assert.ok(textHas(card, typed), typed);
    assert.ok(!textHas(card, 'this action damage'), 'a missing word is still a miss');
    assert.ok(textHas("Explorer's Society", 'explorer’s society') && sameText('Explorer’s Society', "explorer's society"));
});

test('accents, case and spacing are ignored, an empty search matches all, and missing text does not throw', () => {
    assert.ok(textHas('Rasputina, Winter Witch', 'rasputína'));
    assert.ok(textHas('Pandora', ''));
    assert.ok(!textHas(undefined, 'x') && !textHas(null, 'x') && textHas(undefined, ''));
    assert.equal(normalizeText('  Lucas   McCabe,  Tomb Delver '), 'lucas mccabe, tomb delver');
    assert.ok(!sameText('Master', 'Masters'));
});
