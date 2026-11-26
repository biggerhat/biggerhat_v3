import assert from 'node:assert/strict';
import { test } from 'node:test';
import { baseRadius } from './geometry.ts';
import { capLeg, defaultAim, legLabels, pathLength, trailPoints, walkRing, withoutLastLeg } from './stepMove.ts';

const trail = (over = {}) => ({
    id: 'a1',
    from: { x: 10, y: 10 },
    to: { x: 10, y: 10 },
    baseMm: 50,
    startElevation: 0,
    sp: 5,
    kind: 'walk',
    inside: null,
    range: 6,
    whole: false,
    path: [{ x: 10, y: 10, elev: 0, spent: 0 }],
    ...over,
});
const near = (a, b, tol = 1e-9) => assert.ok(Math.abs(a - b) <= tol, `${a} is not ${b}`);

test('capLeg: a step move stops a Walk at what is left of its Sp, and leaves everything else alone', () => {
    // 5" to spend, 3" already spent: only 2" of the next leg
    const t = trail({
        path: [
            { x: 10, y: 10, elev: 0, spent: 0 },
            { x: 10, y: 13, elev: 0, spent: 3 },
        ],
        to: { x: 10, y: 13 },
    });
    const capped = capLeg(t, 'a1', true, { x: 10, y: 30 });
    near(capped.x, 10);
    near(capped.y, 15);
    // diagonal drags are cut along the same line
    const diag = capLeg(t, 'a1', true, { x: 10 + 30, y: 13 + 40 });
    near(Math.hypot(diag.x - 10, diag.y - 13), 2);
    // within what is left: untouched
    assert.deepEqual(capLeg(t, 'a1', true, { x: 10, y: 14 }), { x: 10, y: 14 });
    // not a step move, another model, no Sp on the card, a jump: no cap
    assert.deepEqual(capLeg(t, 'a1', false, { x: 10, y: 30 }), { x: 10, y: 30 });
    assert.deepEqual(capLeg(t, 'zz', true, { x: 10, y: 30 }), { x: 10, y: 30 });
    assert.deepEqual(capLeg(trail({ sp: undefined }), 'a1', true, { x: 10, y: 30 }), { x: 10, y: 30 });
    assert.deepEqual(capLeg(trail({ kind: 'jump' }), 'a1', true, { x: 10, y: 30 }), { x: 10, y: 30 });
    assert.deepEqual(capLeg(null, 'a1', true, { x: 1, y: 1 }), { x: 1, y: 1 });
    // flight is capped like a walk
    near(capLeg(trail({ kind: 'fly' }), 'a1', true, { x: 10, y: 30 }).y, 15);
    // nothing left: the leg cannot leave its start
    const spent = trail({ path: [{ x: 10, y: 10, elev: 0, spent: 5 }] });
    assert.deepEqual(capLeg(spent, 'a1', true, { x: 10, y: 30 }), { x: 10, y: 10 });
});

test('walkRing: measured from the edge of the base, and in a step move smaller by what is spent', () => {
    const rim = baseRadius(50);
    // not a step move: Sp plus the rim, around the start
    const flat = walkRing(trail(), false, false, 0);
    near(flat.r, 5 + rim);
    assert.equal(flat.x, 10);
    // step move, between legs: around where the model is now, less what it has spent
    const mid = walkRing(trail({ to: { x: 10, y: 13 } }), true, false, 3);
    near(mid.r, 2 + rim);
    assert.equal(mid.y, 13);
    // while a leg is being dragged the ring stays where the leg began
    const t = trail({
        path: [
            { x: 10, y: 10, elev: 0, spent: 0 },
            { x: 10, y: 12, elev: 0, spent: 2 },
        ],
        to: { x: 10, y: 15 },
    });
    const dragging = walkRing(t, true, true, 5);
    assert.equal(dragging.y, 12);
    near(dragging.r, 3 + rim);
    // overspent never goes below the rim; no Sp means no ring
    near(walkRing(trail(), true, false, 99).r, rim);
    assert.equal(walkRing(trail({ sp: undefined }), true, false, 0), null);
    assert.equal(walkRing(null, true, false, 0), null);
});

test('the points and legs of a move: lengths add up, and legs under an inch are not labelled', () => {
    const t = trail({
        path: [
            { x: 0, y: 0, elev: 0 },
            { x: 0, y: 3, elev: 0 },
            { x: 0.5, y: 3, elev: 0 },
        ],
        to: { x: 4, y: 3 },
    });
    const pts = trailPoints(t);
    assert.equal(pts.length, 4);
    near(pathLength(pts), 3 + 0.5 + 3.5);
    const labels = legLabels(pts);
    assert.equal(labels.length, 2, 'the half inch leg is left off');
    near(labels[0].length, 3);
    assert.equal(legLabels(pts.slice(0, 2)).length, 0, 'one leg needs no labels');
    assert.deepEqual(trailPoints(null), []);
    assert.equal(pathLength([]), 0);
});

test('withoutLastLeg: takes the last leg back, and refuses when there is only the first', () => {
    const t = trail({
        path: [
            { x: 10, y: 10, elev: 0, spent: 0 },
            { x: 10, y: 12, elev: 0, spent: 2 },
        ],
        to: { x: 10, y: 16 },
    });
    const undone = withoutLastLeg(t);
    assert.deepEqual(undone.back, { x: 10, y: 12, elev: 0, spent: 2 });
    assert.equal(undone.trail.path.length, 1);
    assert.deepEqual(undone.trail.to, { x: 10, y: 12 });
    assert.equal(withoutLastLeg(undone.trail), null);
});

test('defaultAim: the nearest model on the other side, or else across the table', () => {
    const me = { id: 'a1', side: 'a', x: 10, y: 5 };
    const toks = [
        me,
        { id: 'a2', side: 'a', x: 11, y: 5 },
        { id: 'b1', side: 'b', x: 30, y: 30 },
        { id: 'b2', side: 'b', x: 12, y: 20 },
        { id: 'b3', side: 'b', x: 11, y: 6, killed: true },
        { id: 'm1', side: 'b', x: 10, y: 6, marker: true },
        { id: 'n1', side: 'n', x: 10, y: 7 },
    ];
    assert.deepEqual(defaultAim(me, toks), { x: 12, y: 20 });
    // alone: eight inches toward the middle of the table
    assert.deepEqual(defaultAim(me, [me]), { x: 18, y: 13 });
    assert.deepEqual(defaultAim({ ...me, y: 30 }, [{ ...me, y: 30 }]), { x: 18, y: 22 });
});
