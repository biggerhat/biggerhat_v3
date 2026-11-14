import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    AURAS_PER_MODEL,
    auraOrigin,
    auraRadius,
    baseRadius,
    clampSize,
    clampToTable,
    clampTop,
    deploymentZones,
    describeZone,
    edgeDistance,
    fallHurts,
    heightCover,
    inZone,
    modelsWithin,
    movePiece,
    pieceBounds,
    pointInShape,
    restingSupport,
    rotatePiece90,
    supportFraction,
    surfaceAt,
    tallClimbablePieces,
    terrainCoverage,
    translateShape,
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

// Area of a zone on the table, by sampling a fine grid (cells of 0.25").
const area = (shape) => {
    let n = 0;
    for (let x = 0.125; x < 36; x += 0.25) for (let y = 0.125; y < 36; y += 0.25) if (inZone(shape, x, y)) n++;
    return n * 0.0625;
};
const near = (actual, expected, tolerance, what) =>
    assert.ok(Math.abs(actual - expected) <= tolerance, `${what}: ${actual} is not within ${tolerance} of ${expected}`);

test('standard deployment is two 8" strips either side of a horizontal centerline (p.61)', () => {
    const z = deploymentZones('standard');
    near(area(z.a), 36 * 8, 1, 'strip area');
    near(area(z.b), 36 * 8, 1, 'strip area');
    assert.deepEqual(z.centerline, [
        [0, 18],
        [36, 18],
    ]);
    assert.ok(inZone(z.a, 18, 7.9) && !inZone(z.a, 18, 8.1));
    assert.ok(inZone(z.b, 18, 28.1) && !inZone(z.b, 18, 27.9));
});

test('corner deployment is a quarter circle of 12", not a square (p.61)', () => {
    const z = deploymentZones('corner');
    near(area(z.a), (Math.PI * 144) / 4, 1.5, 'quarter circle area');
    near(area(z.b), (Math.PI * 144) / 4, 1.5, 'quarter circle area');
    assert.ok(inZone(z.a, 36, 11.9), 'along the edge, 12" from the corner');
    assert.ok(!inZone(z.a, 25, 11), 'the far corner of a 12" square is outside the arc');
    assert.deepEqual(z.centerline, [
        [0, 0],
        [36, 36],
    ]);
});

test('flank deployment is the part of a quarter within 9" of its table edges (p.61)', () => {
    const z = deploymentZones('flank');
    // two 18x9 bands sharing a 9x9 corner: 162 + 162 - 81
    near(area(z.a), 243, 1.5, 'flank area');
    near(area(z.b), 243, 1.5, 'flank area');
    assert.ok(inZone(z.a, 30, 8), 'within 9" of the top edge');
    assert.ok(inZone(z.a, 35, 17), 'within 9" of the right edge');
    assert.ok(!inZone(z.a, 22, 12), 'inside the quarter but more than 9" from both edges');
    assert.ok(!inZone(z.a, 10, 5), 'wrong quarter');
});

test('wedge deployment is a triangle with its apex 12" in from the middle of the edge (p.61)', () => {
    const z = deploymentZones('wedge');
    near(area(z.a), 0.5 * 36 * 12, 1.5, 'wedge area');
    assert.ok(inZone(z.a, 18, 11.9) && !inZone(z.a, 18, 12.2));
    assert.ok(inZone(z.a, 1, 0.5) && !inZone(z.a, 1, 6), 'narrows toward the sides');
    assert.deepEqual(z.centerline, [
        [0, 18],
        [36, 18],
    ]);
});

test('every deployment, in every orientation, gives two equal zones that never touch each other or cross the centerline', () => {
    const cells = [...Array(72)].flatMap((_, i) => [...Array(72)].map((__, j) => [i * 0.5 + 0.25, j * 0.5 + 0.25]));
    for (const kind of ['standard', 'flank', 'corner', 'wedge']) {
        for (let v = 0; v < 4; v++) {
            const z = deploymentZones(kind, v);
            near(area(z.a), area(z.b), 0.01, `${kind} ${v}: equal zones`);
            assert.equal(cells.filter(([x, y]) => inZone(z.a, x, y) && inZone(z.b, x, y)).length, 0, `${kind} ${v}: zones overlap`);
            const [[x1, y1], [x2, y2]] = z.centerline;
            const side = (x, y) => Math.sign((x2 - x1) * (y - y1) - (y2 - y1) * (x - x1));
            const sides = (zone) => new Set(cells.filter(([x, y]) => inZone(zone, x, y)).map(([x, y]) => side(x, y)));
            assert.equal(sides(z.a).size, 1, `${kind} ${v}: zone A crosses the centerline`);
            assert.equal(sides(z.b).size, 1, `${kind} ${v}: zone B crosses the centerline`);
            assert.notDeepEqual([...sides(z.a)], [...sides(z.b)], `${kind} ${v}: both zones on one side`);
        }
    }
});

test('turning the table moves the zones round the edges', () => {
    assert.equal(describeZone(deploymentZones('standard', 0).a), 'top');
    assert.equal(describeZone(deploymentZones('standard', 1).a), 'right');
    assert.equal(describeZone(deploymentZones('standard', 2).a), 'bottom');
    assert.equal(describeZone(deploymentZones('standard', 3).a), 'left');
    assert.equal(describeZone(deploymentZones('corner', 0).a), 'top-right');
    assert.equal(describeZone(deploymentZones('corner', 1).a), 'bottom-right');
    assert.equal(describeZone(deploymentZones('corner', 2).a), 'bottom-left');
    assert.equal(describeZone(deploymentZones('corner', 3).a), 'top-left');
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
    assert.deepEqual(
        modelsWithin(following, owner, [owner, near]).map((t) => t.id),
        ['n'],
    );

    const locked = { ...following, locked: true, x: 10, y: 10 };
    const moved = { ...owner, x: 30, y: 30 };
    assert.equal(auraOrigin(locked, moved).x, 10);
    assert.equal(auraOrigin(following, moved).x, 30);
    // the spot keeps its place: the nearby model is still inside, and the owner who walked off is not
    assert.deepEqual(
        modelsWithin(locked, moved, [moved, near]).map((t) => t.id),
        ['n'],
    );
    // an owner who stays inside the aura they left behind counts as inside it
    assert.deepEqual(
        modelsWithin(locked, owner, [owner, near])
            .map((t) => t.id)
            .sort(),
        ['n', 'o'],
    );
});

test('a model can cast up to three auras', () => {
    assert.equal(AURAS_PER_MODEL, 3);
});

const piece2 = (id, shapes, traits = ['climbable']) => ({
    id,
    name: id,
    traits,
    surfaces: shapes.map(([shape, top], i) => ({ id: `${id}-${i}`, shape, top })),
});

test('moving a piece moves every level and keeps it on the table', () => {
    const tower = piece2('t', [
        [rect(2, 2, 6, 6), 3],
        [rect(2, 2, 3, 3), 5],
    ]);
    const moved = movePiece(tower, 10, 4);
    assert.deepEqual(moved.surfaces[0].shape, rect(12, 6, 6, 6));
    assert.deepEqual(moved.surfaces[1].shape, rect(12, 6, 3, 3));
    // dragged far off the table: stops at the edge, the whole piece still inside
    const edge = movePiece(tower, 100, 100);
    assert.deepEqual(pieceBounds(edge), { minX: 30, minY: 30, maxX: 36, maxY: 36 });
    const corner = movePiece(tower, -100, -100);
    assert.deepEqual(pieceBounds(corner), { minX: 0, minY: 0, maxX: 6, maxY: 6 });
    // a polygon and a circle translate too
    assert.deepEqual(translateShape({ kind: 'circle', cx: 5, cy: 5, r: 2 }, 1, 2), { kind: 'circle', cx: 6, cy: 7, r: 2 });
    assert.deepEqual(
        translateShape(
            {
                kind: 'poly',
                points: [
                    [0, 0],
                    [2, 0],
                    [0, 2],
                ],
            },
            1,
            1,
        ).points,
        [
            [1, 1],
            [3, 1],
            [1, 3],
        ],
    );
});

test('the highest level under a point is the one picked', () => {
    const tower = piece2('t', [
        [rect(2, 2, 6, 6), 3],
        [rect(2, 2, 3, 3), 5],
    ]);
    assert.equal(surfaceAt([tower], 3, 3).surface.top, 5);
    assert.equal(surfaceAt([tower], 6, 6).surface.top, 3);
    assert.equal(surfaceAt([tower], 20, 20), null);
});

test('coverage counts the share of the table under terrain and ignores overlap', () => {
    assert.equal(terrainCoverage([]), 0);
    assert.ok(Math.abs(terrainCoverage([piece2('a', [[rect(0, 0, 18, 36), 2]])]) - 0.5) < 0.001);
    const stacked = piece2('b', [
        [rect(0, 0, 18, 18), 2],
        [rect(0, 0, 9, 9), 4],
    ]);
    assert.ok(Math.abs(terrainCoverage([stacked]) - 0.25) < 0.001);
});

test('the guide wants three to eight standable pieces of Ht 2 or more', () => {
    const tall = piece2('tall', [[rect(0, 0, 3, 3), 2]]);
    const low = piece2('low', [[rect(5, 5, 3, 3), 1]]);
    const steep = piece2('steep', [[rect(9, 9, 3, 3), 4]], ['impassable']);
    assert.equal(tallClimbablePieces([tall, low, steep]), 1);
});

test('editor input is kept to half inches inside the allowed range', () => {
    assert.equal(clampTop(0), 0.5);
    assert.equal(clampTop(99), 12);
    assert.equal(clampTop(2.3), 2.5);
    assert.equal(clampSize(0), 0.5);
    assert.equal(clampSize(50), 36);
});

test('every preset builds a valid piece on the table', async () => {
    const { TERRAIN_PRESETS } = await import('./terrainPresets.ts');
    assert.ok(TERRAIN_PRESETS.length >= 8);
    for (const preset of TERRAIN_PRESETS) {
        const p = preset.make('x', 18, 18);
        assert.ok(p.surfaces.length >= 1 && p.surfaces.length <= 6, `${preset.key}: surface count`);
        assert.ok(
            p.traits.every((t) => ['blocking', 'climbable', 'impassable', 'concealing', 'dense', 'hazardous', 'destructible'].includes(t)),
            `${preset.key}: traits`,
        );
        const b = pieceBounds(p);
        assert.ok(b.minX >= 0 && b.minY >= 0 && b.maxX <= 36 && b.maxY <= 36, `${preset.key}: fits the table when centred`);
        assert.ok(
            p.surfaces.every((s) => s.top >= 0.5 && s.top <= 12),
            `${preset.key}: heights`,
        );
    }
});

test('turning a piece a quarter swaps the sides of a rectangle about the piece centre', () => {
    const wall = piece2('w', [[rect(10, 10, 8, 2), 2]]);
    const turned = rotatePiece90(wall);
    assert.deepEqual(pieceBounds(turned), { minX: 13, minY: 7, maxX: 15, maxY: 15 });
    // four turns come back to where it started
    const back = rotatePiece90(rotatePiece90(rotatePiece90(turned)));
    assert.deepEqual(back.surfaces[0].shape, rect(10, 10, 8, 2));
    // a stack turns together
    const tower = piece2('t', [
        [rect(2, 2, 6, 4), 3],
        [rect(2, 2, 2, 2), 5],
    ]);
    const t = rotatePiece90(tower);
    assert.deepEqual(pieceBounds(t), { minX: 3, minY: 1, maxX: 7, maxY: 7 });
    assert.equal(t.surfaces.length, 2);
    // a piece at the table edge is nudged back inside after turning
    const edge = piece2('e', [[rect(0, 0, 12, 2), 2]]);
    const b = pieceBounds(rotatePiece90(edge));
    assert.ok(b.minX >= 0 && b.minY >= 0);
});
