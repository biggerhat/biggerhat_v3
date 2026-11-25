import assert from 'node:assert/strict';
import { test } from 'node:test';
import { TABLE_SIZE, deploymentZones, pieceBounds, tableChecks, tallClimbablePieces, terrainCoverage } from './geometry.ts';
import { randomTable, seededRandom } from './randomTable.ts';

const SEEDS = Array.from({ length: 25 }, (_, i) => 100 + i * 37);

test('the same seed always gives the same table, and different seeds give different ones', () => {
    assert.deepEqual(randomTable(4242).pieces, randomTable(4242).pieces);
    assert.notDeepEqual(randomTable(4242).pieces, randomTable(4243).pieces);
    const a = seededRandom(7);
    const b = seededRandom(7);
    assert.deepEqual([a(), a(), a()], [b(), b(), b()]);
});

test('every piece is on the table, ids are unique, and the other half is the first turned round', () => {
    for (const seed of SEEDS) {
        const { pieces } = randomTable(seed);
        assert.ok(pieces.length >= 6, `seed ${seed}: ${pieces.length} pieces`);
        assert.equal(new Set(pieces.map((p) => p.id)).size, pieces.length, `seed ${seed}: ids are unique`);
        assert.equal(new Set(pieces.flatMap((p) => p.surfaces.map((s) => s.id))).size, pieces.flatMap((p) => p.surfaces).length);
        for (const p of pieces) {
            const b = pieceBounds(p);
            assert.ok(
                b.minX >= -1e-6 && b.minY >= -1e-6 && b.maxX <= TABLE_SIZE + 1e-6 && b.maxY <= TABLE_SIZE + 1e-6,
                `seed ${seed}: ${p.id} is off the table`,
            );
        }
        // a piece and its twin are a half turn apart: their bounds mirror about the centre
        for (const p of pieces.filter((x) => !x.id.endsWith('t') && x.id !== 'centre')) {
            const twin = pieces.find((x) => x.id === `${p.id}t`);
            assert.ok(twin, `seed ${seed}: ${p.id} has a twin`);
            const a = pieceBounds(p);
            const t = pieceBounds(twin);
            assert.ok(Math.abs(a.minX - (TABLE_SIZE - t.maxX)) < 1e-6 && Math.abs(a.minY - (TABLE_SIZE - t.maxY)) < 1e-6);
        }
    }
});

test('the table meets the guide: lanes wide enough, nothing walled off, about half covered, a few places to stand', () => {
    const zones = deploymentZones('standard');
    let tallOk = 0;
    for (const seed of SEEDS) {
        const { pieces } = randomTable(seed, zones);
        const notes = tableChecks(pieces, zones).filter((c) => c.status === 'note');
        assert.deepEqual(
            notes.map((c) => c.id),
            [],
            `seed ${seed}: ${notes.map((c) => c.text).join(' | ')}`,
        );
        const cover = terrainCoverage(pieces);
        assert.ok(cover >= 0.38 && cover <= 0.68, `seed ${seed}: ${Math.round(cover * 100)}% covered`);
        const tall = tallClimbablePieces(pieces);
        if (tall >= 3 && tall <= 8) tallOk++;
    }
    assert.ok(tallOk >= SEEDS.length * 0.9, `${tallOk} of ${SEEDS.length} had three to eight tall pieces to stand on`);
});

test('a table can be made for any deployment', () => {
    for (const kind of ['standard', 'flank', 'corner', 'wedge']) {
        const zones = deploymentZones(kind);
        const { pieces } = randomTable(31337, zones);
        assert.ok(pieces.length > 0);
    }
});
