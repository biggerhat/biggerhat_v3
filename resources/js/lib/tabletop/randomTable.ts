import {
    LANE_MIN,
    TABLE_SIZE,
    deploymentZones,
    distanceToShape,
    tableChecks,
    tallClimbablePieces,
    terrainCoverage,
    translateShape,
    type DeploymentZones,
    type Shape,
    type TerrainPiece,
} from './geometry.ts';

/**
 * A random table, in the spirit of the Gaining Grounds table-building guide (p.60): about half the table covered, three to eight pieces a
 * model can stand on at Ht 2 or more, mostly sight-blocking terrain split between blocking and concealing, lanes at least 50mm wide, and
 * nothing walled off. Half the table is laid out and turned 180 degrees about the centre for the other half, so neither player gets the
 * better ground (the ready-made tables are built the same way).
 *
 * The same seed always gives the same table, so a table can be shared as a number and rolled again to get a different one. Several candidates
 * are laid out for a seed and the one that does best on the table checks is kept; they are estimates, and the TO has the last word.
 */

/** Small, fast, seedable generator (mulberry32): the same seed gives the same run of numbers in every browser. */
export function seededRandom(seed: number): () => number {
    let a = seed >>> 0;
    return () => {
        a = (a + 0x6d2b79f5) >>> 0;
        let t = a;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

export const MAX_SEED = 999999;
/** A fresh seed to roll with. */
export const newSeed = (): number => 1 + Math.floor(Math.random() * MAX_SEED);

type Rand = () => number;

const BUILD = ['blocking', 'climbable'];

/** A rectangle of the table a piece is made to fit. */
interface Cell {
    x: number;
    y: number;
    w: number;
    h: number;
}

interface Kind {
    key: string;
    name: string;
    /** The texture kind. */
    kind: string;
    make: (r: Rand, c: Cell) => { traits: string[]; levels: [Shape, number][] };
}

const clampTo = (n: number, hi: number) => Math.min(n, hi);
/** A rectangle of a given size somewhere inside the cell. */
const inCell = (r: Rand, c: Cell, w: number, h: number): Shape => {
    const ww = Math.min(w, c.w);
    const hh = Math.min(h, c.h);
    return { kind: 'rect', x: c.x + r() * (c.w - ww), y: c.y + r() * (c.h - hh), w: ww, h: hh };
};
/** A circle inside the cell, as big as `fill` of the cell's shorter side allows. */
const disc = (r: Rand, c: Cell, fill: number, cap: number): Shape => {
    const rad = Math.max(1, Math.floor(clampTo((Math.min(c.w, c.h) / 2) * fill, cap) * 2) / 2);
    return { kind: 'circle', cx: c.x + rad + r() * Math.max(0, c.w - 2 * rad), cy: c.y + rad + r() * Math.max(0, c.h - 2 * rad), r: rad };
};
/** An ellipse that fills most of the cell, for woods and hills that follow the ground they are on. */
const oval = (r: Rand, c: Cell, fill: number, cap: number): Extract<Shape, { kind: 'ellipse' }> => {
    const rx = Math.max(1, Math.floor(clampTo((c.w / 2) * fill, cap) * 2) / 2);
    const ry = Math.max(1, Math.floor(clampTo((c.h / 2) * fill, cap) * 2) / 2);
    return { kind: 'ellipse', cx: c.x + rx + r() * Math.max(0, c.w - 2 * rx), cy: c.y + ry + r() * Math.max(0, c.h - 2 * ry), rx, ry };
};
const fillOf = (r: Rand, lo: number) => lo + r() * (1 - lo);
const snap = (n: number) => Math.max(1, Math.round(n * 2) / 2);

const KINDS: Record<string, Kind> = {
    building: {
        key: 'building',
        name: 'Building',
        kind: 'building',
        make: (r, c) => ({
            traits: BUILD,
            levels: [[inCell(r, c, snap(clampTo(c.w * fillOf(r, 0.88), 11)), snap(clampTo(c.h * fillOf(r, 0.88), 9))), 3]],
        }),
    },
    block: {
        key: 'block',
        name: 'Block',
        kind: 'block',
        make: (r, c) => ({
            traits: BUILD,
            levels: [[inCell(r, c, snap(clampTo(c.w * fillOf(r, 0.9), 12)), snap(clampTo(c.h * fillOf(r, 0.8), 11))), 3]],
        }),
    },
    tower: {
        key: 'tower',
        name: 'Tower',
        kind: 'tower',
        make: (r, c) => {
            const base = inCell(r, c, snap(clampTo(c.w * fillOf(r, 0.88), 8)), snap(clampTo(c.h * fillOf(r, 0.88), 8))) as Extract<
                Shape,
                { kind: 'rect' }
            >;
            return {
                traits: BUILD,
                levels: [
                    [base, 3],
                    [{ kind: 'rect', x: base.x, y: base.y, w: snap(base.w / 2), h: snap(base.h / 2) }, 5],
                ],
            };
        },
    },
    steep: {
        key: 'steep',
        name: 'Steep building',
        kind: 'steep',
        make: (r, c) => ({
            traits: ['blocking', 'impassable'],
            levels: [[inCell(r, c, snap(clampTo(c.w * fillOf(r, 0.8), 7)), snap(clampTo(c.h * fillOf(r, 0.8), 5))), 4]],
        }),
    },
    wall: {
        key: 'wall',
        name: 'Wall',
        kind: 'wall',
        make: (r, c) => {
            const len = snap(clampTo(Math.max(c.w, c.h) * fillOf(r, 0.7), 9));
            return { traits: BUILD, levels: [[c.w >= c.h ? inCell(r, c, len, 1.5) : inCell(r, c, 1.5, len), 2]] };
        },
    },
    crates: {
        key: 'crates',
        name: 'Crates',
        kind: 'crates',
        make: (r, c) => ({
            traits: ['blocking', 'climbable', 'destructible'],
            levels: [[inCell(r, c, snap(clampTo(c.w, 4)), snap(clampTo(c.h, 2.5))), 1]],
        }),
    },
    rocks: {
        key: 'rocks',
        name: 'Rocks',
        kind: 'rocks',
        make: (r, c) => ({ traits: ['blocking', 'climbable'], levels: [[disc(r, c, 0.9, 3), 2]] }),
    },
    hill: {
        key: 'hill',
        name: 'Hill',
        kind: 'hill',
        make: (r, c) => {
            const outer = oval(r, c, fillOf(r, 0.9), 7);
            return {
                traits: ['climbable'],
                levels: [
                    [outer, 1],
                    [{ kind: 'ellipse', cx: outer.cx, cy: outer.cy, rx: Math.max(1, outer.rx / 2), ry: Math.max(1, outer.ry / 2) }, 2],
                ],
            };
        },
    },
    woods: {
        key: 'woods',
        name: 'Woods',
        kind: 'woods',
        make: (r, c) => ({ traits: ['concealing', 'dense'], levels: [[oval(r, c, fillOf(r, 0.85), 5), 0]] }),
    },
    fog: {
        key: 'fog',
        name: 'Fog bank',
        kind: 'fog',
        make: (r, c) => ({ traits: ['concealing', 'dense'], levels: [[oval(r, c, fillOf(r, 0.85), 4), 0]] }),
    },
};

/** What goes in a cell: mostly large pieces, some small ones, and a share of concealing terrain. */
const BIG = ['building', 'building', 'block', 'tower', 'steep'];
const SMALL = ['wall', 'wall', 'crates', 'rocks'];
const SIGHT = ['woods', 'woods', 'fog'];

const GAP = LANE_MIN + 0.1;
const TABLE_MARGIN = 0;

/**
 * A piece near the edge of the table is pushed flush against it: a gap narrower than a lane between a piece and the edge is not one a model
 * can use (and the table checks flag it), so the choice is touching the edge or a whole lane away from it.
 */
function flushToEdges(levels: [Shape, number][]): [Shape, number][] {
    const outer = levels[0][0];
    const b =
        outer.kind === 'rect'
            ? { x0: outer.x, y0: outer.y, x1: outer.x + outer.w, y1: outer.y + outer.h }
            : outer.kind === 'circle'
              ? { x0: outer.cx - outer.r, y0: outer.cy - outer.r, x1: outer.cx + outer.r, y1: outer.cy + outer.r }
              : outer.kind === 'ellipse'
                ? { x0: outer.cx - outer.rx, y0: outer.cy - outer.ry, x1: outer.cx + outer.rx, y1: outer.cy + outer.ry }
                : null;
    if (!b) return levels;
    let dx = 0;
    let dy = 0;
    if (b.x0 > 0 && b.x0 < GAP) dx = -b.x0;
    else if (TABLE_SIZE - b.x1 > 0 && TABLE_SIZE - b.x1 < GAP) dx = TABLE_SIZE - b.x1;
    if (b.y0 > 0 && b.y0 < GAP) dy = -b.y0;
    return dx || dy ? levels.map(([shape, top]) => [translateShape(shape, dx, dy), top]) : levels;
}

/** Turn a piece half a turn about the centre of the table. */
function turned(p: TerrainPiece): TerrainPiece {
    const flip = (s: Shape): Shape =>
        s.kind === 'rect'
            ? { ...s, x: TABLE_SIZE - s.x - s.w, y: TABLE_SIZE - s.y - s.h }
            : s.kind === 'circle' || s.kind === 'ellipse'
              ? { ...s, cx: TABLE_SIZE - s.cx, cy: TABLE_SIZE - s.cy }
              : { kind: 'poly', points: s.points.map(([x, y]) => [TABLE_SIZE - x, TABLE_SIZE - y] as [number, number]) };
    return { ...p, id: `${p.id}t`, surfaces: p.surfaces.map((s) => ({ id: `${s.id}t`, top: s.top, shape: flip(s.shape) })) };
}

/** Split a length into `n` pieces of random sizes, none under `min`. */
function split(r: Rand, total: number, n: number, min: number): number[] {
    const weights = Array.from({ length: n }, () => 0.6 + r());
    const sum = weights.reduce((a, b) => a + b, 0);
    const sizes = weights.map((w) => Math.max(min, (w / sum) * total));
    const scale = total / sizes.reduce((a, b) => a + b, 0);
    return sizes.map((x) => x * scale);
}

/**
 * Lay out one candidate for a seed. The half table is cut into columns and rows with a lane between each, and a piece is made to fit most
 * cells, so the lanes are wide enough by construction. Cells left empty open up the table, and an optional bank of fog or woods sits on the
 * centre. The other half is the same turned half way round.
 */
function lay(seed: number): TerrainPiece[] {
    const r = seededRandom(seed);
    const cols = r() < 0.55 ? 3 : r() < 0.5 ? 2 : 4;
    const rows = r() < 0.7 ? 2 : 3;
    const centre = r() < 0.45;
    const width = TABLE_SIZE - 2 * TABLE_MARGIN - GAP * (cols - 1);
    const top = TABLE_MARGIN;
    const bottom = TABLE_SIZE / 2 - GAP / 2;
    const height = bottom - top - GAP * (rows - 1);
    const colW = split(r, width, cols, 4);
    const rowH = split(r, height, rows, 3.5);

    const cells: Cell[] = [];
    let x = TABLE_MARGIN;
    for (let c = 0; c < cols; c++) {
        let y = top;
        for (let rr = 0; rr < rows; rr++) {
            cells.push({ x, y, w: colW[c], h: rowH[rr] });
            y += rowH[rr] + GAP;
        }
        x += colW[c] + GAP;
    }
    // The bank on the centre needs room: the cell next to the centreline in the middle column stays empty, and the bank is sized to the column.
    const middle = Math.floor(cols / 2);
    const centreRow = rows - 1;
    const keepClear = centre ? cells[middle * rows + centreRow] : null;

    const out: TerrainPiece[] = [];
    let n = 0;
    const add = (kind: Kind, cell: Cell, mirror: boolean) => {
        const id = `${kind.key}${n++}`;
        const made = kind.make(r, cell);
        const p: TerrainPiece = {
            id,
            name: kind.name,
            kind: kind.kind,
            traits: made.traits,
            surfaces: flushToEdges(made.levels).map(([shape, topAt], i) => ({ id: `${id}-${i + 1}`, shape, top: topAt })),
        };
        out.push(p);
        if (mirror) out.push(turned(p));
    };

    // one hill that does not block sight, somewhere that is not the middle: the guide wants a few pieces that do not
    // shuffle the cells (Fisher-Yates), then give each a kind: the first is the hill
    const order = cells.filter((c) => c !== keepClear);
    for (let i = order.length - 1; i > 0; i--) {
        const j = Math.floor(r() * (i + 1));
        [order[i], order[j]] = [order[j], order[i]];
    }
    const pickOf = (list: string[]) => list[Math.floor(r() * list.length)];
    const plan: { cell: Cell; key: string }[] = [];
    for (const cell of order.slice(1)) {
        const roll = r();
        if (roll < 0.06) continue; // left open
        plan.push({ cell, key: roll < 0.55 ? pickOf(BIG) : roll < 0.7 ? pickOf(SMALL) : pickOf(SIGHT) });
    }
    // the guide wants concealing and blocking terrain roughly evenly split, and a few places to stand at Ht 2: settle both by construction
    const isSight = (k: string) => SIGHT.includes(k);
    const TALL = ['building', 'block', 'tower'];
    const sights = () => plan.filter((x) => isSight(x.key)).length + (keepClear ? 0.5 : 0);
    const blocks = () => plan.filter((x) => !isSight(x.key)).length;
    const share = () => sights() / Math.max(1, sights() + blocks());
    for (let guard = 0; guard < 20 && share() < 0.38; guard++) {
        const target =
            plan.find((x) => SMALL.includes(x.key)) ??
            plan.find((x) => !isSight(x.key) && !TALL.includes(x.key)) ??
            plan.find((x) => !isSight(x.key));
        if (!target) break;
        target.key = pickOf(SIGHT);
    }
    for (let guard = 0; guard < 20 && share() > 0.6; guard++) {
        const target = plan.find((x) => isSight(x.key));
        if (!target) break;
        target.key = pickOf(BIG);
    }
    if (!plan.some((x) => TALL.includes(x.key))) {
        const target = plan.find((x) => !isSight(x.key)) ?? plan[0];
        if (target) target.key = pickOf(TALL);
    }
    add(KINDS.hill, order[0], true);
    for (const { cell, key } of plan) add(KINDS[key], cell, true);
    if (keepClear) {
        // as big as fits: a lane away from every other piece
        const here = { x: TABLE_SIZE / 2, y: TABLE_SIZE / 2 };
        const room = Math.min(...out.flatMap((p) => p.surfaces.map((sf) => distanceToShape(sf.shape, here.x, here.y)))) - GAP;
        const rad = Math.floor(Math.min(room, 3.5) * 2) / 2;
        if (rad >= 2)
            out.push({
                id: 'centre',
                name: 'Fog bank',
                kind: 'fog',
                traits: ['concealing', 'dense'],
                surfaces: [{ id: 'centre-1', top: 0, shape: { kind: 'circle', cx: here.x, cy: here.y, r: rad } }],
            });
    }
    return out;
}

export interface RandomTable {
    seed: number;
    pieces: TerrainPiece[];
    /** How many of the table guidelines it misses (0 is a table that satisfies every measured one). */
    notes: number;
}

/** How far a candidate is from the guide's advice, lower is better. */
function penalty(pieces: TerrainPiece[], zones: DeploymentZones): number {
    const checks = tableChecks(pieces, zones);
    let bad = checks.filter((c) => c.status === 'note').length * 3;
    const cover = terrainCoverage(pieces);
    bad += Math.max(0, 0.45 - cover, cover - 0.65) * 40;
    const tall = tallClimbablePieces(pieces);
    if (tall < 3) bad += (3 - tall) * 2;
    if (tall > 8) bad += tall - 8;
    return bad;
}

/**
 * The table for a seed. `zones` are the deployment zones to judge lanes and unreachable ground against (a standard deployment when none is
 * chosen on the board).
 */
export function randomTable(seed: number, zones?: DeploymentZones | null, candidates = 24): RandomTable {
    const z = zones ?? deploymentZones('standard');
    let best: { pieces: TerrainPiece[]; score: number } | null = null;
    for (let i = 0; i < candidates; i++) {
        // candidate 0 uses the seed itself, so a seed always starts from the same place
        const pieces = lay(seed * 1009 + i * 7919);
        const score = penalty(pieces, z);
        if (!best || score < best.score) best = { pieces, score };
        if (score === 0) break;
    }
    const final = best!.pieces;
    return { seed, pieces: final, notes: tableChecks(final, z).filter((c) => c.status === 'note').length };
}
