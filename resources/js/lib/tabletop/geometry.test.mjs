import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    AURAS_PER_MODEL,
    auraOrigin,
    auraRadius,
    baseRadius,
    clampToTable,
    deploymentZones,
    edgeDistance,
    fallHurts,
    heightCover,
    modelsWithin,
    pointInShape,
    restingSupport,
    supportFraction,
    withinAura,
} from './geometry.ts';

const rect = (x, y, w, h) => ({ kind: 'rect', x, y, w, h });
const piece = (id, shape, top) => ({ id, name: id, traits: [], surfaces: [{ id: `${id}-s`, shape, top }] });
const tok = (x, y, baseMm = 30) => ({ id: 't', name: 't', side: 'a', baseMm, x, y });

test('base radius is half the diameter in inches', () => {
    assert.ok(Math.abs(baseRadius(30) - 0.5906) < 0.001);
    assert.ok(Math.abs(baseRadius(50) - 0.9843) < 0.001);
});

test('point in rectangle, circle and polygon', () => {
    assert.ok(pointInShape(rect(0, 0, 4, 4), 2, 2));
    assert.ok(!pointInShape(rect(0, 0, 4, 4), 5, 2));
    assert.ok(pointInShape({ kind: 'circle', cx: 5, cy: 5, r: 2 }, 6, 5));
    assert.ok(!pointInShape({ kind: 'circle', cx: 5, cy: 5, r: 2 }, 8, 5));
    const tri = {
        kind: 'poly',
        points: [
            [0, 0],
            [10, 0],
            [0, 10],
        ],
    };
    assert.ok(pointInShape(tri, 2, 2));
    assert.ok(!pointInShape(tri, 8, 8));
});

test('a base fully on, half on and off a surface', () => {
    const s = rect(10, 10, 6, 6);
    assert.equal(supportFraction(tok(13, 13), s), 1);
    assert.equal(supportFraction(tok(30, 30), s), 0);
    const half = supportFraction(tok(10, 13), s);
    assert.ok(half > 0.45 && half < 0.55, `expected about half, got ${half}`);
});

test('a model rests on the highest surface holding at least half its base', () => {
    const house = piece('house', rect(10, 10, 6, 6), 3);
    const box = piece('box', rect(12, 12, 2, 2), 1);
    assert.equal(restingSupport(tok(13, 13, 30), [house, box]).elevation, 3);
    // the box top is inside the house footprint, but the higher surface wins
    assert.equal(restingSupport(tok(13, 13, 30), [box]).elevation, 1);
    assert.equal(restingSupport(tok(30, 30), [house, box]).surface, null);
});

test('less than half supported falls to what is below (p.20)', () => {
    const wall = piece('wall', rect(10, 10, 6, 1), 2);
    // centre on the far edge: only a little over a quarter of a 50 mm base is on the wall
    assert.equal(restingSupport(tok(13, 11.9, 50), [wall]).elevation, 0);
    assert.equal(restingSupport(tok(13, 10.5, 30), [wall]).elevation, 2);
});

test('edge distance is horizontal and never negative', () => {
    const a = tok(10, 10, 30);
    const b = { ...tok(13, 10, 30) };
    assert.ok(Math.abs(edgeDistance(a, b) - (3 - 2 * baseRadius(30))) < 1e-9);
    assert.equal(edgeDistance(a, tok(10.5, 10, 30)), 0);
});

test('bases stay on the table', () => {
    const c = clampToTable(tok(-5, 99, 50));
    assert.equal(c.x, baseRadius(50));
    assert.equal(c.y, 36 - baseRadius(50));
});

test('falls and height cover', () => {
    assert.equal(fallHurts(3, 2), false);
    assert.equal(fallHurts(3, 1), true);
    assert.equal(heightCover(0, 2), true);
    assert.equal(heightCover(0, 1), false);
});

test('deployment zones sit on opposite sides of the table', () => {
    const std = deploymentZones('standard');
    assert.equal(std.a.h, 8);
    assert.equal(std.b.y, 28);
    const corner = deploymentZones('corner');
    assert.equal(corner.a.w, 12);
    assert.equal(corner.b.x, 24);
});

test('an aura starts at the outside edge of the base', () => {
    assert.ok(Math.abs(auraRadius(tok(0, 0, 30), 6) - (6 + baseRadius(30))) < 1e-9);
    assert.ok(Math.abs(auraRadius(tok(0, 0, 50), 6) - (6 + baseRadius(50))) < 1e-9);
});

test('within an aura means any part of the base reaches it', () => {
    const source = tok(10, 10, 30);
    // Edge to edge exactly 6": centres 6 + both radii apart.
    const exactly = tok(10 + 6 + 2 * baseRadius(30), 10, 30);
    assert.ok(withinAura(source, 6, exactly));
    assert.ok(!withinAura(source, 6, tok(10 + 6.05 + 2 * baseRadius(30), 10, 30)));
    // base contact is within 0"
    assert.ok(withinAura(source, 0, tok(10 + 2 * baseRadius(30), 10, 30)));
    // a bigger base reaches in from farther out
    assert.ok(withinAura(source, 6, tok(10 + 6 + baseRadius(30) + baseRadius(50) - 0.1, 10, 50)));
});

test('a locked aura stays where it was locked while the model moves on', () => {
    const owner = { ...tok(10, 10, 30), id: 'o' };
    const near = { ...tok(14, 10, 30), id: 'n' };
    const following = { id: 'a1', ownerId: 'o', range: 6, locked: false };
    assert.deepEqual(modelsWithin(following, owner, [owner, near]).map((t) => t.id), ['n']);

    const locked = { ...following, locked: true, x: 10, y: 10 };
    const moved = { ...owner, x: 30, y: 30 };
    assert.equal(auraOrigin(locked, moved).x, 10);
    assert.equal(auraOrigin(following, moved).x, 30);
    // the spot keeps its place: the nearby model is still inside, and the owner who walked off is not
    assert.deepEqual(modelsWithin(locked, moved, [moved, near]).map((t) => t.id), ['n']);
    // an owner who stays inside the aura they left behind counts as inside it
    assert.deepEqual(modelsWithin(locked, owner, [owner, near]).map((t) => t.id).sort(), ['n', 'o']);
});

test('a model can cast up to three auras', () => {
    assert.equal(AURAS_PER_MODEL, 3);
});
