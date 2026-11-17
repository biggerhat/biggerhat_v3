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
    placeReach,
    jumpCheck,
    IGNORABLE_TRAITS,
    whereabouts,
    nearestLegalSpot,
    terrainAlongLeg,
    terrainAlongPath,
    archwayAt,
    coverAt,
    insideOf,
    wallProblem,
    makeEnterable,
    addOpening,
    moveOpening,
    makeSolid,
    nearestOnOutline,
    openingSpan,
    OPENINGS_MAX,
    settleUnder,
    placeCheck,
    mmToInches,
    rotateAbout,
    rotatePiece,
    rotateShape,
    setShapeAngle,
    shapeHandles,
    dragShapeHandle,
    insertCorner,
    removeCorner,
    shapeOutline,
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

test('a rectangle turned by an angle: containment, bounds and distance follow the turned shape', () => {
    const bar = { kind: 'rect', x: 10, y: 10, w: 10, h: 2, angle: 45 };
    assert.equal(pointInShape(bar, 15, 11), true, 'the centre');
    const far = 15 + 3.5; // along the diagonal, inside
    assert.equal(pointInShape(bar, far, 11 + 3.5), true);
    assert.equal(pointInShape(bar, 19, 11), false, 'the unturned end is no longer there');
    const b = shapeBounds(bar);
    assert.ok(Math.abs(b.maxX - b.minX - (10 + 2) * Math.SQRT1_2) < 1e-9, 'a bar turned 45 degrees is 12 / sqrt2 wide');
    assert.ok(distanceToShape(bar, 15, 11) === 0 && distanceToShape(bar, 19, 11) > 1);
});

test('an ellipse: containment, turn and bounds', () => {
    const e = { kind: 'ellipse', cx: 10, cy: 10, rx: 4, ry: 2 };
    assert.equal(pointInShape(e, 13.9, 10), true);
    assert.equal(pointInShape(e, 10, 12.1), false);
    const turned = { ...e, angle: 90 };
    assert.equal(pointInShape(turned, 10, 13.9), true, 'turned a quarter, the long axis is vertical');
    const b = shapeBounds({ ...e, angle: 90 });
    assert.ok(Math.abs(b.maxY - b.minY - 8) < 1e-9 && Math.abs(b.maxX - b.minX - 4) < 1e-9);
    assert.equal(shapeOutline(e).length, 48);
});

test('rotating a piece by any angle keeps levels together; quarter turns stay axis-aligned', () => {
    const piece = {
        id: 'p',
        name: 'P',
        traits: [],
        surfaces: [
            { id: 'a', top: 1, shape: { kind: 'rect', x: 10, y: 10, w: 8, h: 4 } },
            { id: 'b', top: 2, shape: { kind: 'circle', cx: 14, cy: 12, r: 1 } },
        ],
    };
    const q = rotatePiece(piece, 90);
    assert.deepEqual(q.surfaces[0].shape, { kind: 'rect', x: 12, y: 8, w: 4, h: 8 });
    assert.equal(q.surfaces[0].shape.angle, undefined);
    const t = rotatePiece(piece, 30);
    assert.equal(t.surfaces[0].shape.angle, 30);
    assert.deepEqual(rotatePiece(t, -30).surfaces[0].shape, { kind: 'rect', x: 10, y: 10, w: 8, h: 4 }, 'turning back restores it');
    assert.equal(rotatePiece(rotatePiece(piece, 60), 30).surfaces[0].shape.w, 4, 'two turns make a quarter, which swaps the sides');
    const e = rotateShape({ kind: 'ellipse', cx: 5, cy: 5, rx: 3, ry: 1 }, [5, 5], 180);
    assert.deepEqual(e, { kind: 'ellipse', cx: 5, cy: 5, rx: 3, ry: 1 });
    assert.deepEqual(setShapeAngle({ kind: 'rect', x: 1, y: 1, w: 2, h: 2, angle: 40 }, 0), { kind: 'rect', x: 1, y: 1, w: 2, h: 2 });
    assert.deepEqual(rotateAbout([1, 0], [0, 0], 90), [0, 1]);
});

test('dragging a handle reshapes a level: rectangle corners keep the opposite corner, outlines move and gain or lose corners', () => {
    const r = { kind: 'rect', x: 10, y: 10, w: 4, h: 2 };
    const grown = dragShapeHandle(r, { kind: 'corner', index: 2 }, { x: 18.2, y: 15 });
    assert.deepEqual(grown, { kind: 'rect', x: 10, y: 10, w: 8, h: 5 }, 'the top-left corner stays put and sizes snap to half inches');
    const flipped = dragShapeHandle(r, { kind: 'corner', index: 2 }, { x: 8, y: 9 });
    assert.deepEqual([flipped.x, flipped.y, flipped.w, flipped.h], [8, 9, 2, 1], 'dragged past the other corner it still makes a rectangle');
    const turned = { ...r, angle: 90 };
    const handles = shapeHandles(turned);
    assert.equal(handles.length, 4);
    const out = dragShapeHandle(turned, handles[2], { x: handles[2].x, y: handles[2].y + 2 });
    assert.equal(out.angle, 90);
    assert.equal(out.w + out.h, 8, 'a turned rectangle grows along its own axes');
    assert.equal(dragShapeHandle({ kind: 'circle', cx: 5, cy: 5, r: 1 }, { kind: 'radius', index: 0 }, { x: 8.2, y: 5 }).r, 3);
    assert.equal(dragShapeHandle({ kind: 'ellipse', cx: 5, cy: 5, rx: 2, ry: 1 }, { kind: 'edge', index: 0 }, { x: 10, y: 5 }).rx, 5);
    const tri = { kind: 'poly', points: [[0, 0], [4, 0], [4, 4]] };
    assert.deepEqual(dragShapeHandle(tri, { kind: 'vertex', index: 2 }, { x: 40, y: -3 }).points[2], [36, 0], 'a corner stays on the table');
    const four = insertCorner(tri, 0);
    assert.deepEqual(four.points, [[0, 0], [2, 0], [4, 0], [4, 4]]);
    assert.equal(removeCorner(tri, 0), null, 'three corners is the least');
    assert.equal(removeCorner(four, 1).points.length, 3);
    const full = { kind: 'poly', points: Array.from({ length: 16 }, (_, i) => [i, (i * 7) % 5]) };
    assert.equal(insertCorner(full, 0), null);
    assert.equal(shapeHandles(tri).filter((h) => h.kind === 'mid').length, 3);
});

test('a place is measured from the closest point of the starting base, not by the middle', () => {
    const r = mmToInches(30) / 2;
    assert.ok(Math.abs(placeReach(6, 30, 30) - (6 + 2 * r)) < 1e-9, 'a single point of the new base in range: the middle may be 6" plus both radii away');
    assert.ok(Math.abs(placeReach(6, 30, 30, true) - 6) < 1e-9, 'the whole base in range: the middle may be 6" away');
    assert.ok(placeReach(6, 50, 30) > placeReach(6, 30, 30), 'a larger starting base reaches further');
    const start = { x: 10, y: 10, baseMm: 30 };
    const ok = placeCheck(6, start, { x: 16, y: 10, baseMm: 30 });
    assert.equal(ok.within, true);
    assert.ok(Math.abs(ok.gap - (6 - 2 * r)) < 1e-9, 'the gap is edge to edge');
    const far = placeCheck(6, start, { x: 18, y: 10, baseMm: 30 }, true);
    assert.equal(far.within, false);
    assert.ok(Math.abs(far.over - 2) < 1e-9);
    assert.equal(placeCheck(6, start, { x: 10.2, y: 10, baseMm: 30 }).gap, 0, 'overlapping bases have no gap');
});

test('a bridge: a model beneath the archway is not on the deck, sight passes under it, and only a model that fits may stand there', () => {
    const bridge = {
        id: 'br',
        name: 'Bridge',
        traits: ['blocking', 'climbable'],
        surfaces: [{ id: 'br-1', top: 3, under: 2, shape: { kind: 'rect', x: 10, y: 15, w: 12, h: 4 } }],
    };
    const below = man('u', 'a', 16, 17, 1, { under: true });
    const above = man('o', 'a', 16, 17, 1);
    assert.equal(restingSupport(below, [bridge]).elevation, 0, 'beneath it, on the ground');
    assert.equal(restingSupport(above, [bridge]).elevation, 3, 'on it, at the deck');
    assert.equal(archwayAt(below, [bridge])?.piece.id, 'br');
    assert.equal(archwayAt(man('z', 'a', 2, 2), [bridge]), null);
    assert.equal(settleUnder(below, 0, [bridge]), true, 'came from the ground: went under');
    assert.equal(settleUnder(below, 3, [bridge]), false, 'was already as high as the deck: on it');
    assert.equal(settleUnder(man('z', 'a', 2, 2), 0, [bridge]), undefined);
    // sight under the bridge: two small models see each other through the archway; a tall one does not
    const a = man('a', 'a', 4, 17, 1);
    const b = man('b', 'b', 28, 17, 1);
    assert.equal(lineOfSight(a, b, [a, b], [bridge]).clear, true, 'Sz 1 models fit under the 2" archway');
    const tall = man('t', 'b', 28, 17, 3);
    assert.equal(lineOfSight(a, tall, [a, tall], [bridge]).clear, false, 'a Sz 3 model does not fit, so the deck blocks');
    const noArch = { ...bridge, surfaces: [{ ...bridge.surfaces[0], under: undefined }] };
    assert.equal(lineOfSight(a, b, [a, b], [noArch]).clear, false, 'a solid piece of the same size would block');
    // fit
    const big = man('g', 'a', 16, 17, 3, { under: true });
    assert.equal(placementProblems(big, [big], [bridge])[0].kind, 'impassable');
    assert.match(placementProblems(big, [big], [bridge])[0].text, /too tall/);
    assert.deepEqual(placementProblems(below, [below], [bridge]), []);
});

const house = (extra = {}) => ({
    id: 'h',
    name: 'House',
    traits: ['blocking', 'climbable'],
    surfaces: [{ id: 'h-1', top: 3, shape: { kind: 'rect', x: 10, y: 10, w: 6, h: 6 } }],
    interior: {
        floor: 0,
        openings: [
            { id: 'door', kind: 'door', x: 13, y: 16, w: 2 },
            { id: 'win', kind: 'window', x: 16, y: 13, w: 1.5 },
        ],
    },
    ...extra,
});

test('inside a building: under the roof, standing on the floor, and on the roof when high enough', () => {
    const h = house();
    const inside = man('i', 'a', 13, 13, 2, { under: true });
    const onRoof = man('r', 'a', 13, 13, 2);
    assert.equal(insideOf(inside, [h])?.id, 'h');
    assert.equal(insideOf(onRoof, [h]), null, 'not under the roof, so not inside');
    assert.equal(restingSupport(inside, [h]).elevation, 0, 'on the ground floor');
    assert.equal(restingSupport(inside, [house({ interior: { ...h.interior, floor: 1 } })]).elevation, 1, 'on a raised floor');
    assert.equal(restingSupport(onRoof, [h]).elevation, 3, 'on the roof');
    assert.equal(coverAt(inside, [h])?.kind, 'building');
    assert.equal(settleUnder(man('g', 'a', 13, 13), 0, [h]), true, 'came from the ground: inside');
    assert.equal(settleUnder(man('g', 'a', 13, 13), 3, [h]), false, 'came from the roof: on it');
    assert.equal(insideOf(man('o', 'a', 3, 3, 2, { under: true }), [h]), null);
});

test('sight into and out of a building goes through its doors and windows, not its walls', () => {
    const h = house();
    const inside = man('i', 'a', 13, 13, 2, { under: true });
    const south = man('s', 'b', 13, 24, 2); // straight out through the door
    const east = man('e', 'b', 24, 13, 2); // out through the window
    const west = man('w', 'b', 2, 13, 2); // a wall with no opening
    assert.equal(lineOfSight(inside, south, [inside, south], [h]).clear, true, 'through the open door');
    assert.equal(lineOfSight(south, inside, [inside, south], [h]).clear, true, 'and back again');
    assert.equal(lineOfSight(inside, east, [inside, east], [h]).clear, true, 'through the window');
    assert.equal(lineOfSight(inside, west, [inside, west], [h]).clear, false, 'the wall blocks');
    assert.equal(lineOfSight(inside, west, [inside, west], [h]).blockedBy, 'House');
    const mate = man('m', 'a', 14, 14, 2, { under: true });
    assert.equal(lineOfSight(inside, mate, [inside, mate], [h]).clear, true, 'two models in the same building see each other');
    // neither inside: the building is just a blocking piece, openings do not matter
    const a = man('a', 'a', 13, 4, 2);
    assert.equal(lineOfSight(a, south, [a, south], [h]).clear, false, 'a line through the whole building is blocked');
    // the same models on the roof ignore the building they stand on
    const roof = man('r', 'a', 13, 13, 2);
    assert.equal(lineOfSight(roof, west, [roof, west], [h]).clear, true, 'a model on the roof sees out over the walls');
});

test('walking into a building needs a door as wide as the base', () => {
    const h = house();
    const door = { x: 13, y: 22 };
    assert.equal(wallProblem(h, door, { x: 13, y: 13 }, 30), null, 'in through the 2" door');
    assert.match(wallProblem(h, { x: 22, y: 13 }, { x: 13, y: 13 }, 30), /enters by a door/, 'a window is not a way in');
    assert.match(wallProblem(h, { x: 2, y: 13 }, { x: 13, y: 13 }, 30), /through the wall of House/, 'through a plain wall');
    const narrow = house({ interior: { floor: 0, openings: [{ id: 'd', kind: 'door', x: 13, y: 16, w: 1 }] } });
    assert.match(wallProblem(narrow, door, { x: 13, y: 13 }, 30), /no door as wide/, 'a 1" door is narrower than a 30 mm base');
    assert.equal(wallProblem(narrow, door, { x: 13, y: 13 }, 20), null, 'but not than a 20 mm base');
    assert.equal(wallProblem(h, { x: 12, y: 12 }, { x: 14, y: 14 }, 30), null, 'moving about inside crosses no wall');
});

test('making a building enterable, and moving its doors along the walls', () => {
    const solid = { id: 's', name: 'Hall', traits: ['blocking', 'climbable'], surfaces: [{ id: 's-1', top: 3, shape: { kind: 'rect', x: 4, y: 4, w: 8, h: 4 } }] };
    const e = makeEnterable(solid);
    assert.equal(e.interior.openings.length, 1);
    const d = e.interior.openings[0];
    assert.equal(d.kind, 'door');
    assert.ok(Math.abs(nearestOnOutline(solid.surfaces[0].shape, d.x, d.y).x - d.x) < 1e-9, 'on the outline');
    const win = addOpening(e, 'window');
    assert.equal(win.interior.openings.length, 2);
    assert.notDeepEqual([win.interior.openings[1].x, win.interior.openings[1].y], [d.x, d.y], 'a second opening goes on another wall');
    const moved = moveOpening(win, d.id, { x: 20, y: 6 });
    const m = moved.interior.openings.find((o) => o.id === d.id);
    assert.equal(m.x, 12, 'pulled to the nearest wall');
    assert.equal(m.y, 6);
    const span = openingSpan(moved, m);
    assert.ok(Math.abs(Math.hypot(span[1].x - span[0].x, span[1].y - span[0].y) - m.w) < 1e-9, 'the gap is as wide as the opening');
    let full = e;
    for (let i = 0; i < 20; i++) full = addOpening(full, 'window') ?? full;
    assert.equal(full.interior.openings.length, OPENINGS_MAX);
    assert.equal(addOpening(full, 'door'), null);
    assert.equal(makeSolid(e).interior, undefined);
});

test('a walk across severe, hazardous and impassable terrain says what it met and what it costs extra', () => {
    const rect = (x, y, w, h) => ({ kind: 'rect', x, y, w, h });
    const river = { id: 'r', name: 'River', traits: ['severe'], surfaces: [{ id: 'r1', top: 0, shape: rect(14, 5, 3, 30) }] };
    const acid = { id: 'a', name: 'Acid pool', traits: ['hazardous'], surfaces: [{ id: 'a1', top: 0, shape: { kind: 'circle', cx: 30, cy: 10, r: 2 } }] };
    const deep = { id: 'd', name: 'Deep water', traits: ['impassable'], surfaces: [{ id: 'd1', top: 0, shape: rect(5, 20, 4, 4) }] };
    const r = mmToInches(30) / 2;
    // across the 3" river: the base is in it from one radius before the bank to one radius past the far bank
    const across = terrainAlongLeg({ x: 10, y: 10 }, { x: 22, y: 10 }, 30, [river, acid, deep]);
    assert.ok(Math.abs(across.severe - (3 + 2 * r)) < 0.2, `expected about ${3 + 2 * r} in severe terrain, saw ${across.severe}`);
    assert.deepEqual(across.hazardous, []);
    assert.deepEqual(across.impassable, []);
    // not touching it costs nothing extra
    assert.equal(terrainAlongLeg({ x: 2, y: 10 }, { x: 10, y: 10 }, 30, [river]).severe, 0);
    // through the acid pool, and past it without touching
    assert.deepEqual(terrainAlongLeg({ x: 26, y: 10 }, { x: 34, y: 10 }, 30, [acid]).hazardous, ['Acid pool']);
    assert.deepEqual(terrainAlongLeg({ x: 26, y: 20 }, { x: 34, y: 20 }, 30, [acid]).hazardous, []);
    // in base contact counts: a base that only grazes the edge
    assert.deepEqual(terrainAlongLeg({ x: 26, y: 12.4 }, { x: 34, y: 12.4 }, 30, [acid]).hazardous, ['Acid pool']);
    // the impassable piece is in the way of a walk through it
    assert.deepEqual(terrainAlongLeg({ x: 2, y: 22 }, { x: 12, y: 22 }, 30, [deep]).impassable, ['Deep water']);
    // a path adds its legs up
    const path = terrainAlongPath([{ x: 10, y: 10 }, { x: 22, y: 10 }, { x: 10, y: 10 }], 30, [river]);
    assert.ok(Math.abs(path.severe - 2 * across.severe) < 0.3);
});

test('a model in an illegal spot goes to the nearest legal one', () => {
    const r = mmToInches(30) / 2;
    const a = man('a', 'a', 10, 10, 2);
    const onTop = man('b', 'b', 10.4, 10, 2);
    const spot = nearestLegalSpot(onTop, [a, onTop], []);
    assert.ok(spot, 'a spot is found');
    const gap = Math.hypot(spot.x - a.x, spot.y - a.y);
    assert.ok(gap >= 2 * r - 1e-6 && gap < 2 * r + 0.3, `just clear of the other base, ${gap}" between centres`);
    assert.ok(Math.hypot(spot.x - onTop.x, spot.y - onTop.y) < 2 * r + 0.1, 'and close to where it was');
    assert.equal(nearestLegalSpot(man('c', 'b', 20, 20), [a, onTop], []), null, 'a legal model stays where it is');
    // on impassable terrain it goes to the edge
    const steep = { id: 's', name: 'Steep', traits: ['blocking', 'impassable'], surfaces: [{ id: 's1', top: 4, shape: { kind: 'rect', x: 10, y: 10, w: 4, h: 4 } }] };
    const stuck = man('d', 'a', 12, 12, 2);
    const out = nearestLegalSpot(stuck, [stuck], [steep]);
    assert.ok(out);
    assert.deepEqual(placementProblems({ ...stuck, ...out }, [stuck], [steep]).filter((p) => p.kind === 'impassable'), []);
    assert.ok(Math.hypot(out.x - 12, out.y - 12) < 2 + r + 0.3, 'the nearest edge, not across the table');
    // hazardous terrain is legal to stand in
    const bog = { id: 'h', name: 'Brambles', traits: ['hazardous'], surfaces: [{ id: 'h1', top: 0, shape: { kind: 'circle', cx: 5, cy: 5, r: 2 } }] };
    assert.equal(nearestLegalSpot(man('e', 'a', 5, 5), [], [bog]), null);
    // a model pinned in a corner by a wall of bases still finds somewhere, or says there is nowhere
    const crowd = [];
    for (let x = 0; x <= 36; x += 1) for (let y = 0; y <= 36; y += 1) crowd.push(man(`c${x}-${y}`, 'b', x, y));
    assert.equal(nearestLegalSpot(man('z', 'a', 10, 10), [...crowd], []), null, 'no room anywhere near');
});

test('where a base is on the table: quarters, nearest edge and corner, and the half and zone of a deployment', () => {
    const r = mmToInches(30) / 2;
    const w = whereabouts({ x: 5, y: 4, baseMm: 30 });
    assert.deepEqual(w.quarters, ['top-left']);
    assert.equal(w.edge.side, 'top');
    assert.ok(Math.abs(w.edge.gap - (4 - r)) < 1e-9);
    assert.equal(w.corner.name, 'top-left');
    assert.ok(Math.abs(w.corner.gap - (Math.hypot(5, 4) - r)) < 1e-9);
    // across the middle: all the quarters it touches
    assert.deepEqual(whereabouts({ x: 18, y: 18, baseMm: 30 }).quarters.sort(), ['bottom-left', 'bottom-right', 'top-left', 'top-right']);
    assert.deepEqual(whereabouts({ x: 18, y: 5, baseMm: 30 }).quarters.sort(), ['top-left', 'top-right']);
    assert.equal(whereabouts({ x: 0, y: 20, baseMm: 30 }).edge.gap, 0, 'touching the edge');
    // a deployment: standard has the centerline across the middle; side a is the top strip
    const z = deploymentZones('standard');
    const top = whereabouts({ x: 10, y: 5, baseMm: 30 }, z);
    assert.equal(top.half, 'a');
    assert.equal(top.zone, 'a');
    const bottom = whereabouts({ x: 10, y: 31, baseMm: 30 }, z);
    assert.equal(bottom.half, 'b');
    assert.equal(bottom.zone, 'b');
    const middle = whereabouts({ x: 10, y: 18.2, baseMm: 30 }, z);
    assert.equal(middle.half, 'line', 'on the centerline');
    assert.equal(middle.zone, null);
    const between = whereabouts({ x: 10, y: 13, baseMm: 30 }, z);
    assert.equal(between.half, 'a');
    assert.equal(between.zone, null, 'in a half but outside its 8" strip');
    assert.equal(whereabouts({ x: 10, y: 10, baseMm: 30 }).half, undefined, 'no deployment, no half');
});

test('a model unaffected by a terrain trait ignores it: sight lines and walks', () => {
    const rect = (x, y, w, h) => ({ kind: 'rect', x, y, w, h });
    const wall = { id: 'w', name: 'Wall', traits: ['blocking'], surfaces: [{ id: 'w1', top: 4, shape: rect(16, 5, 2, 30) }] };
    const fog = { id: 'f', name: 'Fog', traits: ['concealing', 'dense'], surfaces: [{ id: 'f1', top: 0.5, shape: rect(16, 5, 2, 30) }] };
    const a = man('a', 'a', 4, 18, 2);
    const b = man('b', 'b', 30, 18, 2);
    assert.equal(lineOfSight(a, b, [a, b], [wall]).clear, false);
    const blind = { ...a, ignores: ['blocking'] };
    assert.equal(lineOfSight(blind, b, [blind, b], [wall]).clear, true, 'a model unaffected by blocking sees past the wall');
    assert.equal(lineOfSight(a, { ...b, ignores: ['blocking'] }, [a, b], [wall]).clear, false, 'only the model drawing the lines counts');
    assert.equal(lineOfSight(a, b, [a, b], [fog]).clear, false, 'dense fog blocks');
    const sees = { ...a, ignores: ['dense'] };
    const seen = lineOfSight(sees, b, [sees, b], [fog]);
    assert.equal(seen.clear, true);
    assert.equal(seen.concealed, true, 'but the target is still concealed unless concealing is ignored too');
    const both = { ...a, ignores: ['dense', 'concealing'] };
    assert.equal(lineOfSight(both, b, [both, b], [fog]).concealed, false);
    // walks
    const river = { id: 'r', name: 'River', traits: ['severe'], surfaces: [{ id: 'r1', top: 0, shape: rect(14, 5, 3, 30) }] };
    const acid = { id: 'p', name: 'Acid', traits: ['hazardous'], surfaces: [{ id: 'p1', top: 0, shape: rect(14, 5, 3, 30) }] };
    assert.ok(terrainAlongLeg({ x: 10, y: 10 }, { x: 22, y: 10 }, 30, [river]).severe > 3);
    assert.equal(terrainAlongLeg({ x: 10, y: 10 }, { x: 22, y: 10 }, 30, [river], ['severe']).severe, 0);
    assert.deepEqual(terrainAlongLeg({ x: 10, y: 10 }, { x: 22, y: 10 }, 30, [acid], ['hazardous']).hazardous, []);
    const m = man('m', 'a', 15, 10, 2, { ignores: ['hazardous', 'severe'] });
    assert.deepEqual(placementProblems(m, [m], [acid, river]), [], 'standing in terrain it is unaffected by raises nothing');
    assert.deepEqual(IGNORABLE_TRAITS.length, 6);
});

test('a jump (p.22): no higher than it began, at most 1" lower, and over nothing taller than where it started', () => {
    const rect = (x, y, w, h) => ({ kind: 'rect', x, y, w, h });
    const wall3 = { id: 'a', name: 'Ht 3 wall', traits: ['blocking', 'climbable'], surfaces: [{ id: 'a1', top: 3, shape: rect(2, 10, 6, 4) }] };
    const wall2 = { id: 'b', name: 'Ht 2 wall', traits: ['blocking', 'climbable'], surfaces: [{ id: 'b1', top: 2, shape: rect(11, 10, 6, 4) }] };
    const wall1 = { id: 'c', name: 'Ht 1 wall', traits: ['blocking', 'climbable'], surfaces: [{ id: 'c1', top: 1, shape: rect(20, 10, 4, 4) }] };
    const pieces = [wall3, wall2, wall1];
    const start = { x: 7, y: 12, baseMm: 30 };
    // the guide's example: from the Ht 3 wall to the Ht 2 wall, one inch lower
    const ok = jumpCheck(start, 3, man('j', 'a', 12, 12, 2), pieces);
    assert.deepEqual(ok.reasons, []);
    assert.equal(ok.endElevation, 2);
    assert.ok(Math.abs(ok.distance - 5) < 1e-9);
    // landing on the ground is a drop of 3: not a jump
    assert.match(jumpCheck(start, 3, man('j', 'a', 9.5, 5, 2), pieces).reasons[0], /at most 1" below/);
    // landing higher than it began
    assert.match(jumpCheck({ x: 13, y: 12, baseMm: 30 }, 2, man('j', 'a', 5, 12, 2), pieces).reasons[0], /cannot end higher/);
    // across a piece taller than where it started
    const tall = { id: 't', name: 'Ht 5 wall', traits: ['blocking'], surfaces: [{ id: 't1', top: 5, shape: rect(9, 11, 1, 2) }] };
    const blocked = jumpCheck(start, 3, man('j', 'a', 12, 12, 2), [...pieces, tall]);
    assert.ok(blocked.reasons.some((x) => /Ht 5 wall \(Ht 5\) is in the way/.test(x)), blocked.reasons.join(' | '));
    // a piece no taller than the starting elevation is jumped over
    const low = { id: 'l', name: 'Ht 2 fence', traits: [], surfaces: [{ id: 'l1', top: 2, shape: rect(9, 11, 1, 2) }] };
    assert.deepEqual(jumpCheck(start, 3, man('j', 'a', 12, 12, 2), [...pieces, low]).reasons, []);
    // level ground to level ground is a legal jump
    assert.deepEqual(jumpCheck({ x: 2, y: 2, baseMm: 30 }, 0, man('j', 'a', 6, 2, 2), []).reasons, []);
});
