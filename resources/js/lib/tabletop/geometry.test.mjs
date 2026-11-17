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
    distanceToShape,
    edgeDistance,
    fallHurts,
    heightCover,
    inZone,
    lineOfSight,
    modelsWithin,
    moveCost,
    movePiece,
    pathCost,
    pieceBounds,
    placementProblems,
    pointInShape,
    restingSupport,
    rotatePiece90,
    searchMarkers,
    shapeBounds,
    sideSightLines,
    supportFraction,
    surfaceAt,
    tallClimbablePieces,
    terrainCoverage,
    traceLine,
    translateShape,
    walkBudget,
    withinAura,
} from './geometry.ts';
import { TERRAIN_LAYOUTS } from './terrainLayouts.ts';

const rect = (x, y, w, h) => ({ kind: 'rect', x, y, w, h });
const piece = (id, shape, top) => ({ id, name: id, traits: ['climbable'], surfaces: [{ id: `${id}-s`, shape, top }] });
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
    assert.equal(clampTop(0), 0);
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
            p.traits.every((t) => ['blocking', 'climbable', 'impassable', 'concealing', 'dense', 'hazardous', 'destructible', 'severe'].includes(t)),
            `${preset.key}: traits`,
        );
        const b = pieceBounds(p);
        assert.ok(b.minX >= 0 && b.minY >= 0 && b.maxX <= 36 && b.maxY <= 36, `${preset.key}: fits the table when centred`);
        assert.ok(
            p.surfaces.every((s) => s.top >= 0 && s.top <= 12),
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

test('marker search needs every word, in any order, and puts names that start with the text first', () => {
    const list = [
        { id: 1, name: 'Scheme Marker', base_mm: 30 },
        { id: 2, name: 'Strategy Marker', base_mm: 40 },
        { id: 3, name: 'Blast Marker', base_mm: 50 },
        { id: 4, name: 'Marker of the Dead', base_mm: 30 },
        { id: 5, name: 'Corpse Marker', base_mm: 30 },
    ];
    assert.deepEqual(
        searchMarkers(list, 'marker').map((o) => o.id),
        [4, 3, 5, 1, 2],
    );
    assert.deepEqual(
        searchMarkers(list, 'mark dead').map((o) => o.id),
        [4],
    );
    assert.deepEqual(
        searchMarkers(list, 'DEAD marker').map((o) => o.id),
        [4],
    );
    assert.deepEqual(searchMarkers(list, 'zzz'), []);
    assert.equal(searchMarkers(list, '').length, 5, 'an empty search lists everything, up to the limit');
    assert.equal(searchMarkers(list, '', 2).length, 2);
    assert.deepEqual(
        searchMarkers(list, 'sch').map((o) => o.name),
        ['Scheme Marker'],
    );
});

test('moving costs the ground covered plus any climb, and a drop is reported apart', () => {
    const flat = moveCost({ x: 0, y: 0 }, { x: 3, y: 4 }, 0, 0);
    assert.equal(flat.ground, 5);
    assert.equal(flat.total, 5);
    const up = moveCost({ x: 0, y: 0 }, { x: 3, y: 4 }, 0, 2);
    assert.deepEqual([up.climb, up.drop, up.total], [2, 0, 7]);
    const down = moveCost({ x: 0, y: 0 }, { x: 0, y: 6 }, 3, 0);
    assert.deepEqual([down.climb, down.drop, down.total], [0, 3, 6]);
});

// --- line of sight ---
const man = (id, side, x, y, sz = 2, extra = {}) => ({ id, name: id, side, baseMm: 30, sz, x, y, ...extra });
const wall = (top, x = 9, w = 2, traits = ['blocking']) => ({
    id: 'w',
    name: 'Wall',
    traits,
    surfaces: [{ id: 'w1', top, shape: { kind: 'rect', x, y: 5, w, h: 30 } }],
});

test('terrain as tall as both models blocks sight, shorter than both does not', () => {
    const a = man('a', 'a', 4, 18);
    const b = man('b', 'b', 16, 18);
    assert.equal(lineOfSight(a, b, [a, b], [wall(2)]).clear, false);
    assert.equal(lineOfSight(a, b, [a, b], [wall(2)]).blockedBy, 'Wall');
    assert.equal(lineOfSight(a, b, [a, b], [wall(1)]).clear, true);
    assert.equal(lineOfSight(a, b, [a, b], []).clear, true);
});

test('terrain taller than only one model blocks only when the shorter one is within 2 inches', () => {
    const tall = man('b', 'b', 20, 18, 3);
    const far = man('a', 'a', 4, 18, 1);
    const close = man('a', 'a', 8, 18, 1);
    assert.equal(lineOfSight(far, tall, [far, tall], [wall(2)]).clear, true);
    assert.equal(lineOfSight(close, tall, [close, tall], [wall(2)]).clear, false);
});

test('a model standing on a piece is not blocked by it', () => {
    const roof = {
        id: 'r',
        name: 'Roof',
        traits: ['blocking', 'climbable'],
        surfaces: [{ id: 'r1', top: 3, shape: { kind: 'rect', x: 4, y: 14, w: 8, h: 8 } }],
    };
    const up = man('a', 'a', 6, 18);
    const target = man('b', 'b', 20, 18);
    assert.equal(lineOfSight(up, target, [up, target], [roof]).clear, true);
});

test('dense terrain blocks a line across it but not one that only leaves it', () => {
    const woods = {
        id: 'f',
        name: 'Woods',
        traits: ['dense', 'concealing'],
        surfaces: [{ id: 'f1', top: 1, shape: { kind: 'rect', x: 8, y: 5, w: 6, h: 30 } }],
    };
    const a = man('a', 'a', 3, 18);
    const outside = man('b', 'b', 20, 18);
    const inside = man('c', 'b', 11, 18);
    assert.equal(lineOfSight(a, outside, [a, outside], [woods]).clear, false);
    assert.equal(lineOfSight(a, inside, [a, inside], [woods]).clear, true);
    assert.equal(lineOfSight(a, inside, [a, inside], [woods]).concealed, true);
});

test('only enemy models of enough size block; friendly models and markers never do', () => {
    const a = man('a', 'a', 4, 18);
    const b = man('b', 'b', 16, 18);
    const foe = man('e', 'b', 10, 18);
    const friend = man('f', 'a', 10, 18);
    const small = man('s', 'b', 10, 18, 1);
    const marker = man('m', 'b', 10, 18, 5, { marker: true });
    assert.equal(lineOfSight(a, b, [a, b, foe], []).clear, false);
    assert.equal(lineOfSight(a, b, [a, b, foe], []).blockedBy, 'e');
    assert.equal(lineOfSight(a, b, [a, b, friend], []).clear, true);
    assert.equal(lineOfSight(a, b, [a, b, small], []).clear, true);
    assert.equal(lineOfSight(a, b, [a, b, marker], []).clear, true);
});

test('a sight line can slip round the edge of a base', () => {
    // A small enemy beside the straight line does not hide the target.
    const a = man('a', 'a', 4, 18);
    const b = man('b', 'b', 16, 18);
    const foe = man('e', 'b', 10, 19.2);
    assert.equal(lineOfSight(a, b, [a, b, foe], []).clear, true);
});

test('cover comes from height, or from blocking terrain within 2 inches of the target that a clear line crosses', () => {
    const a = man('a', 'a', 4, 18);
    const nearWall = man('b', 'b', 12.2, 18, 3);
    const farWall = man('c', 'b', 20, 18, 3);
    assert.equal(lineOfSight(a, nearWall, [a, nearWall], [wall(1)]).cover, 'terrain');
    assert.equal(lineOfSight(a, farWall, [a, farWall], [wall(1)]).cover, null);
    const hill = {
        id: 'h',
        name: 'Hill',
        traits: ['climbable'],
        surfaces: [{ id: 'h1', top: 2, shape: { kind: 'rect', x: 18, y: 14, w: 6, h: 8 } }],
    };
    const high = man('d', 'b', 21, 18);
    assert.equal(lineOfSight(a, high, [a, high], [hill]).cover, 'height');
});

test('models in base contact always see each other, and distances to shapes work', () => {
    const a = man('a', 'a', 10, 18);
    const b = man('b', 'b', 10.9, 18);
    assert.equal(lineOfSight(a, b, [a, b], [wall(9, 9.5, 1)]).clear, true);
    assert.equal(distanceToShape({ kind: 'rect', x: 0, y: 0, w: 2, h: 2 }, 5, 1), 3);
    assert.equal(distanceToShape({ kind: 'circle', cx: 0, cy: 0, r: 1 }, 4, 0), 3);
    assert.equal(
        distanceToShape(
            {
                kind: 'poly',
                points: [
                    [0, 0],
                    [4, 0],
                    [0, 4],
                ],
            },
            1,
            1,
        ),
        0,
    );
});

test('only climbable terrain holds a model up', () => {
    const steep = {
        id: 's',
        name: 'Steep',
        traits: ['blocking', 'impassable'],
        surfaces: [{ id: 's1', top: 4, shape: { kind: 'rect', x: 0, y: 0, w: 10, h: 10 } }],
    };
    assert.equal(restingSupport({ x: 5, y: 5, baseMm: 30 }, [steep]).elevation, 0);
});

test('every ready-made table follows the guide: 45-65% covered, three to eight tall standable pieces, nothing overlapping, all on the table', () => {
    for (const layout of TERRAIN_LAYOUTS) {
        const pieces = layout.pieces();
        const coverage = terrainCoverage(pieces);
        assert.ok(coverage >= 0.45 && coverage <= 0.65, `${layout.key}: ${Math.round(coverage * 100)}% covered`);
        const tall = tallClimbablePieces(pieces);
        assert.ok(tall >= 3 && tall <= 8, `${layout.key}: ${tall} tall climbable pieces`);
        assert.equal(new Set(pieces.map((p) => p.id)).size, pieces.length, `${layout.key}: piece ids`);
        assert.equal(
            new Set(pieces.flatMap((p) => p.surfaces.map((s) => s.id))).size,
            pieces.flatMap((p) => p.surfaces).length,
            `${layout.key}: surface ids`,
        );
        for (const s of pieces.flatMap((p) => p.surfaces)) {
            const b = shapeBounds(s.shape);
            assert.ok(b.minX >= 0 && b.minY >= 0 && b.maxX <= 36 && b.maxY <= 36, `${layout.key}: ${s.id} is on the table`);
            assert.ok(s.top >= 0 && s.top <= 12);
        }
        for (let x = 0.25; x < 36; x += 0.5) {
            for (let y = 0.25; y < 36; y += 0.5) {
                const hits = pieces.filter((p) => p.surfaces.some((s) => pointInShape(s.shape, x, y)));
                assert.ok(hits.length <= 1, `${layout.key}: ${hits.map((h) => h.id)} overlap at ${x},${y}`);
            }
        }
        // fair: turning the table half a turn gives the same table, level for level
        for (let x = 0.5; x < 36; x += 1) {
            for (let y = 0.5; y < 36; y += 1) {
                const here = surfaceAt(pieces, x, y);
                const there = surfaceAt(pieces, 36 - x, 36 - y);
                assert.equal(here?.surface.top ?? 0, there?.surface.top ?? 0, `${layout.key}: not symmetric at ${x},${y}`);
            }
        }
    }
});

test('bases may touch but not overlap, and nothing ends on impassable terrain', () => {
    const a = man('a', 'a', 10, 10);
    const touching = man('b', 'b', 10 + 30 / 25.4, 10);
    const overlapping = man('c', 'b', 10.5, 10);
    const marker = man('m', 'b', 10, 10, 0, { marker: true });
    assert.deepEqual(placementProblems(a, [a, touching], []), []);
    assert.equal(placementProblems(a, [a, overlapping], [])[0].kind, 'overlap');
    assert.deepEqual(placementProblems(a, [a, marker], []), [], 'a marker can be stood on');
    assert.deepEqual(placementProblems(a, [a, { ...overlapping, killed: true }], []), [], 'a model out of play does not count');
    const steep = {
        id: 's',
        name: 'Steep',
        traits: ['blocking', 'impassable'],
        surfaces: [{ id: 's1', top: 4, shape: { kind: 'rect', x: 9.8, y: 0, w: 5, h: 20 } }],
    };
    assert.equal(placementProblems(a, [a], [steep])[0].kind, 'impassable');
    const bog = { id: 'b', name: 'Bog', traits: ['hazardous'], surfaces: [{ id: 'b1', top: 0.5, shape: { kind: 'circle', cx: 10, cy: 10, r: 3 } }] };
    assert.equal(placementProblems(a, [a], [bog])[0].kind, 'hazardous');
    const mud = { ...bog, id: 'm', name: 'Mud', traits: ['severe'] };
    assert.deepEqual(placementProblems(a, [a], [mud]).map((p) => p.kind), ['severe']);
    assert.deepEqual(
        placementProblems(a, [a], [{ ...steep, surfaces: [{ ...steep.surfaces[0], shape: { kind: 'rect', x: 20, y: 0, w: 5, h: 5 } }] }]),
        [],
    );
});

test('a move is compared with a Walk of Sp inches', () => {
    assert.ok(Math.abs(walkBudget(5, { total: 4.2 }).left - 0.8) < 1e-9);
    assert.equal(walkBudget(5, { total: 4.2 }).within, true);
    assert.equal(walkBudget(5, { total: 5 }).within, true);
    assert.equal(walkBudget(5, { total: 6.5 }).within, false);
    assert.ok(Math.abs(walkBudget(5, { total: 6.5 }).left + 1.5) < 1e-9);
});

test('a stepped move costs each leg: 2 inches then 5 inches is 7, plus any climbing', () => {
    const flat = pathCost([
        { x: 0, y: 0, elev: 0 },
        { x: 2, y: 0, elev: 0 },
        { x: 2, y: 5, elev: 0 },
    ]);
    assert.equal(flat.ground, 7);
    assert.equal(flat.total, 7);
    assert.deepEqual(flat.legs, [2, 5]);
    const climbing = pathCost([
        { x: 0, y: 0, elev: 0 },
        { x: 3, y: 0, elev: 0 },
        { x: 3, y: 4, elev: 2 },
        { x: 3, y: 8, elev: 0 },
    ]);
    assert.deepEqual([climbing.ground, climbing.climb, climbing.drop, climbing.total], [11, 2, 2, 13]);
    assert.equal(pathCost([{ x: 1, y: 1, elev: 0 }]).total, 0);
});

// --- side sight lines ---

test('side lines leave the two sides of the base square to the aim and run parallel in its direction', () => {
    const a = man('a', 'a', 10, 18);
    const lines = sideSightLines(a, { x: 20, y: 18 });
    assert.deepEqual(
        lines.map((l) => l.side),
        ['left', 'right'],
    );
    for (const l of lines) {
        assert.ok(Math.abs(Math.hypot(l.start.x - 10, l.start.y - 18) - baseRadius(30)) < 1e-9);
        assert.ok(Math.abs(l.start.x - 10) < 1e-9); // square to a horizontal aim
        assert.ok(Math.abs(l.end.x - 20) < 1e-9 && Math.abs(l.end.y - l.start.y) < 1e-9); // straight off the base, not converging
    }
    assert.ok(Math.abs(lines[0].start.y - lines[1].start.y) > 2 * baseRadius(30) - 1e-9);
    assert.equal(sideSightLines(a, { x: 10.2, y: 18 }), null); // inside its own base
});

test('a line sees the models it reaches, nearest first, and is stopped by tall terrain', () => {
    const a = man('a', 'a', 4, 18);
    const b = man('b', 'b', 16, 18);
    const start = { x: 4 + baseRadius(30), y: 18 };
    const open = traceLine(a, start, { x: 18, y: 18 }, [a, b], []);
    assert.deepEqual(
        open.models.map((m) => [m.id, m.blockedBy]),
        [['b', null]],
    );
    // an enemy as tall as the viewer ends the drawn line at its edge: it is seen, nothing beyond it is
    assert.ok(open.stopAt && Math.abs(open.stopAt.x - (16 - baseRadius(30))) < 0.3);
    assert.equal(open.blockedBy, 'b');
    assert.equal(traceLine(a, start, { x: 12, y: 18 }, [a, b], []).stopAt, null);

    const shut = traceLine(a, start, { x: 18, y: 18 }, [a, b], [wall(2)]);
    assert.equal(shut.models[0].blockedBy, 'Wall');
    assert.ok(shut.stopAt && shut.stopAt.x >= 9 - 0.3 && shut.stopAt.x <= 9 + 0.3);
    assert.equal(shut.blockedBy, 'Wall');

    // terrain shorter than the model does not stop it
    const low = traceLine(a, start, { x: 18, y: 18 }, [a, b], [wall(1)]);
    assert.ok(low.stopAt && low.stopAt.x > 15);
    assert.equal(low.models[0].blockedBy, null);
});

test('a line stops short of a model it does not reach, and an enemy in the way hides one behind it', () => {
    const a = man('a', 'a', 4, 18);
    const b = man('b', 'b', 16, 18);
    const short = traceLine(a, { x: 4.6, y: 18 }, { x: 10, y: 18 }, [a, b], []);
    assert.equal(short.models.length, 0);

    const front = man('f', 'b', 9, 18);
    const lined = traceLine(a, { x: 4.6, y: 18 }, { x: 20, y: 18 }, [a, front, b], []);
    assert.deepEqual(
        lined.models.map((m) => m.id),
        ['f', 'b'],
    );
    assert.equal(lined.models[0].blockedBy, null);
    assert.equal(lined.models[1].blockedBy, 'f'); // as tall as both: blocked
    assert.ok(lined.stopAt && lined.stopAt.x > 8 && lined.stopAt.x < 10);

    // markers are never seen
    const marker = { id: 'm', name: 'Marker', side: 'n', marker: true, baseMm: 30, x: 8, y: 18 };
    assert.equal(traceLine(a, { x: 4.6, y: 18 }, { x: 12, y: 18 }, [a, marker], []).models.length, 0);
});

test('a model standing on a tall piece looks over terrain that would stop one on the ground', () => {
    const hill = {
        id: 'h',
        name: 'Tower',
        traits: ['climbable'],
        surfaces: [{ id: 'h1', top: 2, shape: { kind: 'rect', x: 2, y: 16, w: 5, h: 4 } }],
    };
    const a = man('a', 'a', 4, 18);
    const b = man('b', 'b', 16, 18);
    const w = wall(3); // as tall as the viewer on the ground (2) but not the viewer on the tower (4)
    assert.equal(traceLine(a, { x: 4.6, y: 18 }, { x: 18, y: 18 }, [a, b], [hill, w]).models[0].blockedBy, null);
    assert.equal(traceLine(a, { x: 4.6, y: 18 }, { x: 18, y: 18 }, [a, b], [hill, wall(4)]).models[0].blockedBy, 'Wall');
    assert.equal(traceLine(a, { x: 4.6, y: 18 }, { x: 18, y: 18 }, [a, b], [wall(3)]).models[0].blockedBy, 'Wall');
});

// --- the rules guide's own sight line examples (pp.25-26) ---

test('guide example 2: a Ht 3 wall blocks Sz 2 models, unless a line can pass its end', () => {
    const a = man('a', 'a', 4, 18);
    const b = man('b', 'b', 16, 18);
    const full = wall(3);
    assert.equal(lineOfSight(a, b, [a, b], [full]).clear, false);
    // a wall covering only the lower part of the bases' width leaves the upper lines open
    const partial = { id: 'w', name: 'Wall', traits: ['blocking'], surfaces: [{ id: 'w1', top: 3, shape: rect(9, 18.2, 2, 12) }] };
    assert.equal(lineOfSight(a, b, [a, b], [partial]).clear, true);
});

test('guide example 3: smaller enemies and friendly models are ignored, an equal enemy blocks what is behind it', () => {
    const fb = man('fb', 'a', 4, 18, 2);
    const survivor = man('s', 'b', 8, 18, 1);
    const golem = man('g', 'a', 12, 18, 4);
    const rooster = man('r', 'b', 16, 18, 2);
    const borg = man('x', 'b', 20, 18, 2);
    const all = [fb, survivor, golem, rooster, borg];
    assert.equal(lineOfSight(fb, rooster, all, []).clear, true);
    const toBorg = lineOfSight(fb, borg, all, []);
    assert.equal(toBorg.clear, false);
    assert.equal(toBorg.blockedBy, 'r');
    // and a guideline drawn straight along shows the same: the Rooster Rider is reached, Borgmann is hidden by it
    const seen = traceLine(fb, { x: 4.6, y: 18 }, { x: 22, y: 18 }, all, []).models;
    assert.deepEqual(
        seen.map((m) => [m.id, m.blockedBy]),
        [
            ['s', null],
            ['g', null],
            ['r', null],
            ['x', 'r'],
        ],
    );
});

test('guide examples 4 and 5: a wall as tall as both models blocks however close, one shorter than both does not', () => {
    const fb = man('fb', 'a', 4, 18, 2);
    const borg = man('b', 'b', 12, 18, 2);
    assert.equal(lineOfSight(fb, borg, [fb, borg], [wall(3, 7, 1)]).clear, false);
    assert.equal(lineOfSight(fb, borg, [fb, borg], [wall(3, 5, 1)]).clear, false); // right beside Firebranded
    assert.equal(lineOfSight(fb, borg, [fb, borg], [wall(1, 7, 1)]).clear, true);
});

test('guide dense terrain example: seen into the fog, not through it', () => {
    const fog = { id: 'f', name: 'Fog', traits: ['dense', 'concealing'], surfaces: [{ id: 'f1', top: 3, shape: rect(8, 10, 6, 16) }] };
    const ophelia = man('o', 'a', 4, 18);
    const jack = man('j', 'b', 10, 18);
    const montresor = man('m', 'b', 18, 18);
    const all = [ophelia, jack, montresor];
    const toJack = lineOfSight(ophelia, jack, all, [fog]);
    assert.equal(toJack.clear, true);
    assert.equal(toJack.concealed, true); // concealment against attacks on him
    assert.equal(lineOfSight(ophelia, montresor, all, [fog]).clear, false);
});

test('ground terrain presets: woods are dense, tall grass conceals without blocking, rivers and marsh are severe, water cannot be stood in', async () => {
    const { TERRAIN_PRESETS } = await import('./terrainPresets.ts');
    const make = (key, x = 18, y = 18) => TERRAIN_PRESETS.find((p) => p.key === key).make('t', x, y);
    const a = man('a', 'a', 4, 18);
    const b = man('b', 'b', 32, 18);
    assert.equal(lineOfSight(a, b, [a, b], [make('woods')]).clear, false, 'a line across woods is blocked');
    const grass = lineOfSight(a, b, [a, b], [make('tallgrass')]);
    assert.equal(grass.clear, true, 'tall grass does not block');
    assert.equal(grass.concealed, true, 'but it conceals');
    assert.equal(lineOfSight(a, b, [a, b], [make('tree', 18, 18)]).clear, false, 'a tree in the way blocks');
    assert.equal(lineOfSight(a, b, [a, b], [make('tree', 18, 24)]).clear, true, 'a tree off to one side does not');
    for (const key of ['river', 'marsh', 'rubble', 'brambles']) assert.ok(make(key).traits.includes('severe'), `${key} is severe`);
    for (const key of ['brambles', 'acid', 'fire']) assert.ok(make(key).traits.includes('hazardous'), `${key} is hazardous`);
    const inDeep = man('d', 'a', 18, 18);
    assert.equal(placementProblems(inDeep, [inDeep], [make('deepwater')])[0].kind, 'impassable');
    assert.deepEqual(placementProblems(inDeep, [inDeep], [make('river')]).map((p) => p.kind), ['severe']);
});

test('flat ground terrain has no height: models on it stand at ground level and it never blocks by height', async () => {
    const { TERRAIN_PRESETS } = await import('./terrainPresets.ts');
    for (const key of ['river', 'deepwater', 'marsh', 'rubble', 'brambles', 'acid', 'fire', 'tallgrass', 'woods', 'fog']) {
        const p = TERRAIN_PRESETS.find((x) => x.key === key).make('t', 18, 18);
        assert.ok(p.surfaces.every((s) => s.top === 0), `${key} is flat`);
    }
    const river = TERRAIN_PRESETS.find((x) => x.key === 'river').make('t', 18, 18);
    const a = man('a', 'a', 4, 18);
    const b = man('b', 'b', 32, 18);
    assert.equal(lineOfSight(a, b, [a, b], [river]).clear, true);
    const wader = man('w', 'a', 18, 18);
    assert.equal(restingSupport(wader, [river]).elevation, 0);
});
