/**
 * Tabletop geometry for the virtual tabletop spike. Everything is in inches, measured from the top-left table corner, y growing down.
 * No imports on purpose: Node runs this file directly for `npm run test:tabletop`.
 *
 * Rules source: Malifaux 4th Edition Comprehensive Rules Guide 9.2.2026 (docs/plans/vtt-terrain-and-rules.md).
 */

export const TABLE_SIZE = 36;
export const MM_PER_INCH = 25.4;
/** A model whose base is under this share supported falls (p.20). */
export const SUPPORT_MIN = 0.5;
/** Falling two or more elevations deals 1 irreducible damage (p.20). */
export const FALL_DAMAGE_FROM = 2;
export const BASE_SIZES_MM = [30, 40, 50] as const;

export type Shape =
    | { kind: 'rect'; x: number; y: number; w: number; h: number }
    | { kind: 'circle'; cx: number; cy: number; r: number }
    | { kind: 'poly'; points: [number, number][] };

/** A standable plane. `top` is the elevation in inches of a model standing on it (already the sum of what supports it). */
export interface Surface {
    id: string;
    shape: Shape;
    top: number;
}

export interface TerrainPiece {
    id: string;
    name: string;
    traits: string[];
    surfaces: Surface[];
}

/** What the game tracker keeps about a model: health, activation, tokens on it and a note. */
export interface ModelInfo {
    health?: number;
    maxHealth?: number;
    activated?: boolean;
    tokens: { id: number; name: string }[];
    notes?: string;
}

export interface Token {
    id: string;
    name: string;
    /** 'a' and 'b' are the two players; 'n' is neutral, for markers. */
    side: 'a' | 'b' | 'n';
    /** A marker from the markers table rather than a model in a crew. */
    marker?: boolean;
    markerId?: number;
    baseMm: number;
    /** The Sz stat. Missing means unknown: sight lines assume 1. Markers are Sz 0. */
    sz?: number;
    /** The Sp stat: a Walk moves up to this many inches. Missing means unknown. */
    sp?: number;
    x: number;
    y: number;
    /** Health, activation, tokens and notes. Missing on markers. */
    info?: ModelInfo;
    /** The viewer may not move this model (it belongs to the other player). */
    locked?: boolean;
    /** Removed from play in the game tracker: drawn faded. */
    killed?: boolean;
}

export const mmToInches = (mm: number): number => mm / MM_PER_INCH;
export const baseRadius = (baseMm: number): number => mmToInches(baseMm) / 2;

export function pointInShape(shape: Shape, x: number, y: number): boolean {
    if (shape.kind === 'rect') return x >= shape.x && x <= shape.x + shape.w && y >= shape.y && y <= shape.y + shape.h;
    if (shape.kind === 'circle') return (x - shape.cx) ** 2 + (y - shape.cy) ** 2 <= shape.r ** 2;
    let inside = false;
    const p = shape.points;
    for (let i = 0, j = p.length - 1; i < p.length; j = i++) {
        const [xi, yi] = p[i];
        const [xj, yj] = p[j];
        if (yi > y !== yj > y && x < ((xj - xi) * (y - yi)) / (yj - yi) + xi) inside = !inside;
    }
    return inside;
}

/** Equal-area sample points across a disc (a sunflower spiral), so the covered share is just the share of points covered. */
export function discSamples(cx: number, cy: number, r: number, count = 96): [number, number][] {
    const golden = Math.PI * (3 - Math.sqrt(5));
    return Array.from({ length: count }, (_, i) => {
        const d = r * Math.sqrt((i + 0.5) / count);
        return [cx + d * Math.cos(i * golden), cy + d * Math.sin(i * golden)] as [number, number];
    });
}

/** The share (0..1) of a base that lies over a shape. */
export function supportFraction(token: Pick<Token, 'x' | 'y' | 'baseMm'>, shape: Shape): number {
    const pts = discSamples(token.x, token.y, baseRadius(token.baseMm));
    return pts.filter(([x, y]) => pointInShape(shape, x, y)).length / pts.length;
}

export interface Support {
    /** The surface the base rests on, or null for the bare table. */
    surface: Surface | null;
    piece: TerrainPiece | null;
    /** Elevation in inches. The table is 0. */
    elevation: number;
}

/** A base rests on the highest surface holding at least half of it; otherwise on the table (p.19-20). */
export function restingSupport(token: Pick<Token, 'x' | 'y' | 'baseMm'>, pieces: TerrainPiece[]): Support {
    let best: Support = { surface: null, piece: null, elevation: 0 };
    for (const piece of pieces) {
        // Only climbable terrain supports a base (p.19): woods, fog and the like are drawn but never stood on.
        if (!piece.traits.includes('climbable')) continue;
        for (const surface of piece.surfaces) {
            if (surface.top <= best.elevation) continue;
            if (supportFraction(token, surface.shape) >= SUPPORT_MIN) best = { surface, piece, elevation: surface.top };
        }
    }
    return best;
}

/** Inches between the closest points of two bases, never negative (0 means base contact or overlap, p.12). */
export function edgeDistance(a: Pick<Token, 'x' | 'y' | 'baseMm'>, b: Pick<Token, 'x' | 'y' | 'baseMm'>): number {
    return Math.max(0, Math.hypot(a.x - b.x, a.y - b.y) - baseRadius(a.baseMm) - baseRadius(b.baseMm));
}

/** An aura or pulse of `range` inches starts at the outside edge of the base, so its circle is the base radius plus the range (p.18). */
export const auraRadius = (token: Pick<Token, 'baseMm'>, range: number): number => baseRadius(token.baseMm) + range;

/** A model is within an aura when any part of its base is at that distance or closer (p.12). */
export const withinAura = (source: Pick<Token, 'x' | 'y' | 'baseMm'>, range: number, other: Pick<Token, 'x' | 'y' | 'baseMm'>): boolean =>
    edgeDistance(source, other) <= range + 1e-9;

export const AURAS_PER_MODEL = 3;

/** An aura cast by a model. Locked, it stays at (x, y), where it was when locked, while the model moves on. */
export interface Aura {
    id: string;
    ownerId: string;
    range: number;
    locked: boolean;
    x?: number;
    y?: number;
    /** Shown to the other player too, and saved with the game. Otherwise private to this browser. */
    shared?: boolean;
}

/** Where an aura is centred, as a base of the owner's size: on the model, or at its locked spot. */
export function auraOrigin(aura: Aura, owner: Pick<Token, 'x' | 'y' | 'baseMm'>): Pick<Token, 'x' | 'y' | 'baseMm'> {
    return aura.locked && aura.x !== undefined && aura.y !== undefined ? { x: aura.x, y: aura.y, baseMm: owner.baseMm } : owner;
}

/**
 * Models inside an aura. A model never counts as inside its own following aura, but one standing in the aura it left behind does.
 */
export function modelsWithin<T extends Pick<Token, 'id' | 'x' | 'y' | 'baseMm'>>(
    aura: Aura,
    owner: Pick<Token, 'x' | 'y' | 'baseMm'>,
    tokens: T[],
): T[] {
    const origin = auraOrigin(aura, owner);
    return tokens.filter((t) => (t.id !== aura.ownerId || aura.locked) && withinAura(origin, aura.range, t));
}

/** Keep a whole base on the table: models cannot move past a table edge (p.60). */
export function clampToTable(token: Pick<Token, 'x' | 'y' | 'baseMm'>): { x: number; y: number } {
    const r = baseRadius(token.baseMm);
    return { x: Math.min(TABLE_SIZE - r, Math.max(r, token.x)), y: Math.min(TABLE_SIZE - r, Math.max(r, token.y)) };
}

/** Elevation lost between two supports. Two or more means the model takes 1 irreducible damage (p.20). */
export function fallDistance(from: number, to: number): number {
    return Math.max(0, from - to);
}

export const fallHurts = (from: number, to: number): boolean => fallDistance(from, to) >= FALL_DAMAGE_FROM;

/**
 * Movement spent getting from one spot to another in a straight line: the horizontal distance (centre to centre, which is how far
 * the whole base travelled) plus any climb, because climbing costs movement for the vertical distance (p.20). Dropping costs
 * nothing as a fall, so it is reported on its own.
 */
export function moveCost(
    from: { x: number; y: number },
    to: { x: number; y: number },
    fromElevation: number,
    toElevation: number,
): { ground: number; climb: number; drop: number; total: number } {
    const ground = Math.hypot(to.x - from.x, to.y - from.y);
    const climb = Math.max(0, toElevation - fromElevation);
    return { ground, climb, drop: fallDistance(fromElevation, toElevation), total: ground + climb };
}

/**
 * Movement spent along a path of waypoints, a leg at a time: each leg is the ground covered plus any climb on that leg (p.20), so
 * going 2" one way and 5" another costs 7" (plus climbing), not the straight line between the ends. Drops are summed apart, as in
 * `moveCost`.
 */
export function pathCost(points: { x: number; y: number; elev: number }[]): {
    ground: number;
    climb: number;
    drop: number;
    total: number;
    legs: number[];
} {
    let ground = 0;
    let climb = 0;
    let drop = 0;
    const legs: number[] = [];
    for (let i = 1; i < points.length; i++) {
        const leg = moveCost(points[i - 1], points[i], points[i - 1].elev, points[i].elev);
        ground += leg.ground;
        climb += leg.climb;
        drop += leg.drop;
        legs.push(leg.ground);
    }
    return { ground, climb, drop, total: ground + climb, legs };
}

/**
 * How a move compares with a Walk (Sp inches, p.20: climbing counts as movement). `left` is negative when it went further than one
 * Walk, which then takes more than one action. Straight line only, and Sp as printed: effects that change it are not known here.
 */
export function walkBudget(sp: number, cost: { total: number }): { left: number; within: boolean } {
    const left = sp - cost.total;
    return { left, within: left >= -1e-9 };
}

/** Effective Sz of a model: its Sz plus the height it stands on (p.19). */
export const effectiveSize = (sz: number, elevation: number): number => sz + elevation;

/** Cover from height: the target is at least 2" higher than the initiator (p.57). */
export const heightCover = (initiatorElevation: number, targetElevation: number): boolean => targetElevation - initiatorElevation >= 2;

export type DeploymentKind = 'standard' | 'flank' | 'corner' | 'wedge';
export const DEPLOYMENT_KINDS: { value: DeploymentKind; label: string }[] = [
    { value: 'standard', label: 'Standard (within 8" of an edge)' },
    { value: 'flank', label: 'Flank (within 9" of a quarter\'s edges)' },
    { value: 'corner', label: 'Corner (within 12" of a corner)' },
    { value: 'wedge', label: 'Wedge (12" in from the middle of an edge)' },
];

export interface DeploymentZones {
    a: Shape;
    b: Shape;
    /** The line that divides the table into the two halves (p.60-61). */
    centerline: [[number, number], [number, number]];
}

const T = TABLE_SIZE;
const half = T / 2;
const rectPoly = (x: number, y: number, w: number, h: number): Shape => ({
    kind: 'poly',
    points: [
        [x, y],
        [x + w, y],
        [x + w, y + h],
        [x, y + h],
    ],
});

/** The zones as the guide draws them (p.61), with side A at the top or top-right. A zone is always inside the table. */
function baseZones(kind: DeploymentKind): DeploymentZones {
    switch (kind) {
        case 'standard':
            return {
                a: rectPoly(0, 0, T, 8),
                b: rectPoly(0, T - 8, T, 8),
                centerline: [
                    [0, half],
                    [T, half],
                ],
            };
        case 'wedge':
            return {
                a: {
                    kind: 'poly',
                    points: [
                        [0, 0],
                        [T, 0],
                        [half, 12],
                    ],
                },
                b: {
                    kind: 'poly',
                    points: [
                        [0, T],
                        [T, T],
                        [half, T - 12],
                    ],
                },
                centerline: [
                    [0, half],
                    [T, half],
                ],
            };
        case 'corner':
            // A quarter circle: a 12" circle centred on the table corner, cut off by the table edge when drawn.
            return {
                a: { kind: 'circle', cx: T, cy: 0, r: 12 },
                b: { kind: 'circle', cx: 0, cy: T, r: 12 },
                centerline: [
                    [0, 0],
                    [T, T],
                ],
            };
        case 'flank':
            // The part of a table quarter within 9" of the two table edges that bound it: a 9" band along each edge.
            return {
                a: {
                    kind: 'poly',
                    points: [
                        [half, 0],
                        [T, 0],
                        [T, half],
                        [T - 9, half],
                        [T - 9, 9],
                        [half, 9],
                    ],
                },
                b: {
                    kind: 'poly',
                    points: [
                        [0, half],
                        [9, half],
                        [9, T - 9],
                        [half, T - 9],
                        [half, T],
                        [0, T],
                    ],
                },
                centerline: [
                    [0, 0],
                    [T, T],
                ],
            };
    }
}

/** Turn the table a quarter turn about its centre: (x, y) goes to (T - y, x). */
function quarterTurn(shape: Shape): Shape {
    const turn = ([x, y]: [number, number]): [number, number] => [T - y, x];
    if (shape.kind === 'circle') {
        const [cx, cy] = turn([shape.cx, shape.cy]);
        return { ...shape, cx, cy };
    }
    if (shape.kind === 'rect') return quarterTurn(rectPoly(shape.x, shape.y, shape.w, shape.h));
    return { kind: 'poly', points: shape.points.map(turn) };
}

/**
 * The zones for a deployment. Variant 0 is as drawn in the guide; 1, 2 and 3 turn the table by a quarter each, which covers every
 * way a player can choose an edge, corner or quarter (with the opponent opposite).
 */
export function deploymentZones(kind: DeploymentKind, variant = 0): DeploymentZones {
    let z = baseZones(kind);
    for (let i = 0; i < ((variant % 4) + 4) % 4; i++) {
        const turn = ([x, y]: [number, number]): [number, number] => [T - y, x];
        z = { a: quarterTurn(z.a), b: quarterTurn(z.b), centerline: [turn(z.centerline[0]), turn(z.centerline[1])] };
    }
    return z;
}

/** Where a zone sits, in words: "top", "bottom-right", and so on. */
export function describeZone(shape: Shape): string {
    const pts =
        shape.kind === 'circle' ? [[shape.cx, shape.cy]] : shape.kind === 'poly' ? shape.points : [[shape.x + shape.w / 2, shape.y + shape.h / 2]];
    const cx = pts.reduce((n, p) => n + p[0], 0) / pts.length;
    const cy = pts.reduce((n, p) => n + p[1], 0) / pts.length;
    const v = cy < T / 3 ? 'top' : cy > (2 * T) / 3 ? 'bottom' : '';
    const h = cx < T / 3 ? 'left' : cx > (2 * T) / 3 ? 'right' : '';
    return [v, h].filter(Boolean).join('-') || 'middle';
}

/** Is a point inside a zone, on the table? */
export const inZone = (shape: Shape, x: number, y: number): boolean => x >= 0 && x <= T && y >= 0 && y <= T && pointInShape(shape, x, y);

// ---- terrain editing -------------------------------------------------------------------------------------------------------------

export const TERRAIN_TRAITS = ['blocking', 'climbable', 'impassable', 'concealing', 'dense', 'hazardous', 'destructible'] as const;
export type TerrainTrait = (typeof TERRAIN_TRAITS)[number];
export const SURFACE_MIN_TOP = 0.5;
export const SURFACE_MAX_TOP = 12;
export const PIECE_MAX = 40;
export const SURFACES_PER_PIECE = 6;

export interface Bounds {
    minX: number;
    minY: number;
    maxX: number;
    maxY: number;
}

export function shapeBounds(shape: Shape): Bounds {
    if (shape.kind === 'rect') return { minX: shape.x, minY: shape.y, maxX: shape.x + shape.w, maxY: shape.y + shape.h };
    if (shape.kind === 'circle') return { minX: shape.cx - shape.r, minY: shape.cy - shape.r, maxX: shape.cx + shape.r, maxY: shape.cy + shape.r };
    const xs = shape.points.map((p) => p[0]);
    const ys = shape.points.map((p) => p[1]);
    return { minX: Math.min(...xs), minY: Math.min(...ys), maxX: Math.max(...xs), maxY: Math.max(...ys) };
}

export function pieceBounds(piece: TerrainPiece): Bounds {
    const all = piece.surfaces.map((s) => shapeBounds(s.shape));
    return {
        minX: Math.min(...all.map((b) => b.minX)),
        minY: Math.min(...all.map((b) => b.minY)),
        maxX: Math.max(...all.map((b) => b.maxX)),
        maxY: Math.max(...all.map((b) => b.maxY)),
    };
}

export function translateShape(shape: Shape, dx: number, dy: number): Shape {
    if (shape.kind === 'rect') return { ...shape, x: shape.x + dx, y: shape.y + dy };
    if (shape.kind === 'circle') return { ...shape, cx: shape.cx + dx, cy: shape.cy + dy };
    return { kind: 'poly', points: shape.points.map(([x, y]) => [x + dx, y + dy] as [number, number]) };
}

/** Move a whole piece, every level together, nudged back so that none of it leaves the table. */
export function movePiece(piece: TerrainPiece, dx: number, dy: number): TerrainPiece {
    const b = pieceBounds(piece);
    const x = Math.min(TABLE_SIZE - b.maxX, Math.max(-b.minX, dx));
    const y = Math.min(TABLE_SIZE - b.maxY, Math.max(-b.minY, dy));
    return { ...piece, surfaces: piece.surfaces.map((s) => ({ ...s, shape: translateShape(s.shape, x, y) })) };
}

/** The topmost surface under a point (the highest level wins), with the piece it belongs to. */
export function surfaceAt(pieces: TerrainPiece[], x: number, y: number): { piece: TerrainPiece; surface: Surface } | null {
    let best: { piece: TerrainPiece; surface: Surface } | null = null;
    for (const piece of pieces) {
        for (const surface of piece.surfaces) {
            if (pointInShape(surface.shape, x, y) && (!best || surface.top >= best.surface.top)) best = { piece, surface };
        }
    }
    return best;
}

/** Share of the table covered by terrain, 0..1 (sampled on a 0.5" grid). The guide wants roughly 45-65% (p.60). */
export function terrainCoverage(pieces: TerrainPiece[]): number {
    const step = 0.5;
    let covered = 0;
    let total = 0;
    for (let x = step / 2; x < TABLE_SIZE; x += step) {
        for (let y = step / 2; y < TABLE_SIZE; y += step) {
            total++;
            if (pieces.some((p) => p.surfaces.some((s) => pointInShape(s.shape, x, y)))) covered++;
        }
    }
    return covered / total;
}

/** Pieces a model can stand on at Ht 2 or more: the guide wants three to eight (p.60). */
export const tallClimbablePieces = (pieces: TerrainPiece[]): number =>
    pieces.filter((p) => p.traits.includes('climbable') && p.surfaces.some((s) => s.top >= 2)).length;

/** Keep editor input inside what the data model and the server accept. */
export const clampTop = (n: number): number => Math.min(SURFACE_MAX_TOP, Math.max(SURFACE_MIN_TOP, Math.round(n * 2) / 2));
export const clampSize = (n: number): number => Math.min(TABLE_SIZE, Math.max(0.5, Math.round(n * 2) / 2));

/** Turn a piece a quarter turn about its own centre (every level together), kept inside the table. */
export function rotatePiece90(piece: TerrainPiece): TerrainPiece {
    const b = pieceBounds(piece);
    const cx = (b.minX + b.maxX) / 2;
    const cy = (b.minY + b.maxY) / 2;
    const turn = ([x, y]: [number, number]): [number, number] => [cx - (y - cy), cy + (x - cx)];
    const surfaces = piece.surfaces.map((s) => {
        const sh = s.shape;
        if (sh.kind === 'circle') {
            const [x, y] = turn([sh.cx, sh.cy]);
            return { ...s, shape: { ...sh, cx: x, cy: y } };
        }
        if (sh.kind === 'rect') {
            const [x1, y1] = turn([sh.x, sh.y]);
            const [x2, y2] = turn([sh.x + sh.w, sh.y + sh.h]);
            return { ...s, shape: { kind: 'rect' as const, x: Math.min(x1, x2), y: Math.min(y1, y2), w: sh.h, h: sh.w } };
        }
        return { ...s, shape: { kind: 'poly' as const, points: sh.points.map(turn) } };
    });
    return movePiece({ ...piece, surfaces }, 0, 0);
}

/** A marker a player can put on the table: name and base size come from the markers table. */
export interface MarkerOption {
    id: number;
    name: string;
    base_mm: number;
}

/** Search the marker list: every word typed must appear in the name, in any order, ignoring case. Names starting with the text come first. */
export function searchMarkers(options: MarkerOption[], query: string, limit = 12): MarkerOption[] {
    const words = query.toLowerCase().split(/\s+/).filter(Boolean);
    const hits = options.filter((o) => words.every((w) => o.name.toLowerCase().includes(w)));
    const first = query.trim().toLowerCase();
    return hits
        .map((o) => ({ o, rank: o.name.toLowerCase().startsWith(first) ? 0 : 1 }))
        .sort((a, b) => a.rank - b.rank || a.o.name.localeCompare(b.o.name))
        .slice(0, limit)
        .map((x) => x.o);
}

// --- line of sight (p.24-28) and cover (p.19-20, 56) ---

type Pt = { x: number; y: number };

/** Distance from a point to the nearest part of a shape; 0 inside it. */
export function distanceToShape(shape: Shape, x: number, y: number): number {
    if (shape.kind === 'rect') {
        const dx = Math.max(shape.x - x, 0, x - (shape.x + shape.w));
        const dy = Math.max(shape.y - y, 0, y - (shape.y + shape.h));
        return Math.hypot(dx, dy);
    }
    if (shape.kind === 'circle') return Math.max(0, Math.hypot(x - shape.cx, y - shape.cy) - shape.r);
    if (pointInShape(shape, x, y)) return 0;
    let best = Infinity;
    for (let i = 0, j = shape.points.length - 1; i < shape.points.length; j = i++) {
        const [ax, ay] = shape.points[j];
        const [bx, by] = shape.points[i];
        const len2 = (bx - ax) ** 2 + (by - ay) ** 2;
        const t = len2 === 0 ? 0 : Math.max(0, Math.min(1, ((x - ax) * (bx - ax) + (y - ay) * (by - ay)) / len2));
        best = Math.min(best, Math.hypot(x - (ax + t * (bx - ax)), y - (ay + t * (by - ay))));
    }
    return best;
}

/** Shortest distance from a point to a segment. */
export function distanceToSegment(p: Pt, a: Pt, b: Pt): number {
    const len2 = (b.x - a.x) ** 2 + (b.y - a.y) ** 2;
    const t = len2 === 0 ? 0 : Math.max(0, Math.min(1, ((p.x - a.x) * (b.x - a.x) + (p.y - a.y) * (b.y - a.y)) / len2));
    return Math.hypot(p.x - (a.x + t * (b.x - a.x)), p.y - (a.y + t * (b.y - a.y)));
}

/** Within this distance of the intervening object, a shorter model is hidden by it (p.24), and a target gets cover from blocking terrain (p.56). */
export const SIGHT_NEAR = 2;
const SIGHT_POINTS = 16;
const SIGHT_STEP = 0.2;

export interface SightResult {
    /** At least one sight line from the first model reaches the second without being blocked. */
    clear: boolean;
    /** A line to draw: a clear one when there is one, else the straight line between centres. */
    line: { from: Pt; to: Pt };
    /** What blocks the drawn line when none is clear. */
    blockedBy: string | null;
    /** The target gets cover: from height (2+ higher, p.19) or from blocking terrain within 2" that a clear sight line crosses (p.56). */
    cover: 'height' | 'terrain' | null;
    /** Every clear line crosses concealing terrain the initiator is not standing in (p.56). */
    concealed: boolean;
    initiator: { elevation: number; sz: number };
    target: { elevation: number; sz: number };
}

const szOf = (t: Pick<Token, 'sz' | 'marker'>): number => (t.marker ? 0 : (t.sz ?? 1));

/** Does the segment p->q touch the shape? Sampled every 0.2", fine against pieces of at least a few inches. */
function crossesShape(p: Pt, q: Pt, shape: Shape): boolean {
    const steps = Math.max(1, Math.ceil(Math.hypot(q.x - p.x, q.y - p.y) / SIGHT_STEP));
    for (let i = 0; i <= steps; i++) {
        const k = i / steps;
        if (pointInShape(shape, p.x + (q.x - p.x) * k, p.y + (q.y - p.y) * k)) return true;
    }
    return false;
}

/** Points on a base's edge, the first facing `toward`. */
function edgePoints(t: Pt & { baseMm: number }, toward: Pt): Pt[] {
    const r = baseRadius(t.baseMm);
    const start = Math.atan2(toward.y - t.y, toward.x - t.x);
    return Array.from({ length: SIGHT_POINTS }, (_, i) => {
        const a = start + (i * 2 * Math.PI) / SIGHT_POINTS;
        return { x: t.x + r * Math.cos(a), y: t.y + r * Math.sin(a) };
    });
}

/** Does the segment p->q leave a base (rather than cross it)? Sight lines are never drawn across either base (p.24). */
const leaves = (c: Pt, from: Pt, to: Pt): boolean => (from.x - c.x) * (to.x - from.x) + (from.y - c.y) * (to.y - from.y) >= 0;

/**
 * Can `from` see `to`? Draws sight lines between points on the two bases (p.24) and checks each against blocking and dense terrain
 * and enemy models:
 * - blocking terrain or an enemy model as tall as both models blocks; shorter than both never does; taller than only one of them
 *   (the shorter one) blocks when the shorter model's base is within 2" of it. Heights are effective: Sz plus elevation, and the
 *   terrain's height is its top above the table.
 * - dense terrain blocks a line that crosses it completely, not one that only enters or leaves it.
 * - terrain either model stands on never blocks, nor does the part of a piece below them.
 * - friendly models never block, and markers do not either.
 * Cover and concealment come from the lines that do get through.
 */
export function lineOfSight(from: Token, to: Token, tokens: Token[], pieces: TerrainPiece[]): SightResult {
    const { supportA, supportB, surfaces, near, blocker } = prepareSight(from, to, tokens, pieces);
    const touching = edgeDistance(from, to) <= 0;

    // Models always see what they are in base contact with (p.24).
    const A = edgePoints(from, to);
    const B = edgePoints(to, from);
    let clearLine: { from: Pt; to: Pt } | null = null;
    let coverTerrain = false;
    let allConcealed = true;
    let anyClear = false;
    for (const p of A) {
        for (const q of B) {
            if (!touching && (!leaves(from, p, q) || !leaves(to, q, p))) continue;
            if (!touching && blocker(p, q) !== null) continue;
            anyClear = true;
            if (!clearLine) clearLine = { from: p, to: q };
            let covered = false;
            let concealed = false;
            for (const { s, blocking, concealing } of surfaces) {
                const crosses = crossesShape(p, q, s.shape);
                if (!crosses) continue;
                if (blocking && near(distanceToShape(s.shape, to.x, to.y) - baseRadius(to.baseMm))) covered = true;
                if (concealing && !pointInShape(s.shape, from.x, from.y)) concealed = true;
            }
            if (covered) coverTerrain = true;
            if (!concealed) allConcealed = false;
        }
    }

    const centre = { from: { x: from.x, y: from.y }, to: { x: to.x, y: to.y } };
    return {
        clear: anyClear,
        line: clearLine ?? centre,
        blockedBy: anyClear ? null : (blocker(centre.from, centre.to) ?? "the other model's own base"),
        cover: heightCover(supportA.elevation, supportB.elevation) ? 'height' : coverTerrain ? 'terrain' : null,
        concealed: anyClear && allConcealed,
        initiator: { elevation: supportA.elevation, sz: szOf(from) },
        target: { elevation: supportB.elevation, sz: szOf(to) },
    };
}

/** The terrain and models that can stand between two models, and the test for whether one sight line is stopped by them. */
function prepareSight(from: Token, to: Token, tokens: Token[], pieces: TerrainPiece[]) {
    const supportA = restingSupport(from, pieces);
    const supportB = restingSupport(to, pieces);
    const effA = szOf(from) + supportA.elevation;
    const effB = szOf(to) + supportB.elevation;
    const lo = Math.min(effA, effB);
    const hi = Math.max(effA, effB);
    const shorter = effA <= effB ? from : to;

    // Terrain that can matter: blocking or dense, minus what either model stands on or above.
    const surfaces = pieces.flatMap((piece) =>
        piece.surfaces
            .filter((s) => ![supportA, supportB].some((sup) => sup.surface?.id === s.id || (sup.piece?.id === piece.id && s.top <= sup.elevation)))
            .map((s) => ({
                piece,
                s,
                blocking: piece.traits.includes('blocking'),
                dense: piece.traits.includes('dense'),
                concealing: piece.traits.includes('concealing'),
            })),
    );

    // Enemy models in the way, with their effective size.
    const enemies = tokens
        .filter((t) => t.id !== from.id && t.id !== to.id && !t.marker && t.side !== 'n' && t.side !== from.side)
        .map((t) => ({ t, h: szOf(t) + restingSupport(t, pieces).elevation }));

    const near = (d: number) => d <= SIGHT_NEAR + 1e-9;

    function blocker(p: Pt, q: Pt): string | null {
        for (const { piece, s, blocking, dense } of surfaces) {
            if (!blocking && !dense) continue;
            if (!crossesShape(p, q, s.shape)) continue;
            if (dense && !pointInShape(s.shape, p.x, p.y) && !pointInShape(s.shape, q.x, q.y)) {
                // inside somewhere in the middle with both ends outside: the line crosses it completely
                return piece.name;
            }
            if (blocking) {
                if (s.top >= hi) return piece.name;
                if (s.top >= lo && near(distanceToShape(s.shape, shorter.x, shorter.y) - baseRadius(shorter.baseMm))) return piece.name;
            }
        }
        for (const { t, h } of enemies) {
            // a line that only touches a base's edge is still blocked by it
            if (distanceToSegment(t, p, q) > baseRadius(t.baseMm) + 1e-9) continue;
            if (h >= hi) return t.name;
            if (h >= lo && near(edgeDistance(shorter, t))) return t.name;
        }
        return null;
    }

    return { supportA, supportB, surfaces, near, blocker };
}

// --- side sight lines: one line from each side of a base, running parallel in the direction of the aim ---

export interface SideLine {
    side: 'left' | 'right';
    start: Pt;
    end: Pt;
}

/**
 * The two lines that leave the sides of `owner`'s base (the points square to the aim) and point straight at `aim`.
 * Null when the aim is inside the base, where there is no direction to speak of.
 */
export function sideSightLines(owner: Pick<Token, 'x' | 'y' | 'baseMm'>, aim: Pt): SideLine[] | null {
    const dx = aim.x - owner.x;
    const dy = aim.y - owner.y;
    const d = Math.hypot(dx, dy);
    const r = baseRadius(owner.baseMm);
    if (d <= r + 1e-6) return null;
    // square to the aim: left and right as seen looking from the model toward the aim
    const nx = -dy / d;
    const ny = dx / d;
    return [
        { side: 'left', start: { x: owner.x + nx * r, y: owner.y + ny * r }, end: { x: owner.x + nx * r + dx, y: owner.y + ny * r + dy } },
        { side: 'right', start: { x: owner.x - nx * r, y: owner.y - ny * r }, end: { x: owner.x - nx * r + dx, y: owner.y - ny * r + dy } },
    ];
}

export interface TraceSeen {
    id: string;
    name: string;
    /** Distance along the line to where it first touches this model's base. */
    along: number;
    /** Null when the line gets there; otherwise what stops it. */
    blockedBy: string | null;
}

export interface LineTrace {
    /** Every model the line touches, nearest first. */
    models: TraceSeen[];
    /** Where the line is first stopped by something as tall as the model it leaves, or null if nothing stops it. */
    stopAt: Pt | null;
    blockedBy: string | null;
    length: number;
}

/** Where along start->end a circle of radius r at (cx, cy) is first entered; 0 when the start is already inside. */
function entryAlong(start: Pt, dir: Pt, length: number, c: Pt, r: number): number | null {
    const fx = start.x - c.x;
    const fy = start.y - c.y;
    const b = fx * dir.x + fy * dir.y;
    const k = fx * fx + fy * fy - r * r;
    if (k <= 0) return 0;
    const disc = b * b - k;
    if (disc < 0) return null;
    const t = -b - Math.sqrt(disc);
    return t >= 0 && t <= length ? t : null;
}

/**
 * Follow a sight line from `from` and say what it sees. For every model the line touches, the stretch up to that model is run through
 * the same sight rules as a full check (blocking and dense terrain, enemy models in the way, heights compared with that model).
 * The line is drawn as stopped where terrain or an enemy at least as tall as `from` first gets in the way, so it can be read
 * at a glance. Markers are not seen and never stop it.
 */
export function traceLine(from: Token, start: Pt, end: Pt, tokens: Token[], pieces: TerrainPiece[]): LineTrace {
    const length = Math.hypot(end.x - start.x, end.y - start.y);
    if (length < 1e-6) return { models: [], stopAt: null, blockedBy: null, length: 0 };
    const dir = { x: (end.x - start.x) / length, y: (end.y - start.y) / length };

    const models: TraceSeen[] = [];
    for (const t of tokens) {
        if (t.id === from.id || t.marker) continue;
        const along = entryAlong(start, dir, length, t, baseRadius(t.baseMm));
        if (along === null) continue;
        const entry = { x: start.x + dir.x * along, y: start.y + dir.y * along };
        const blockedBy = prepareSight(from, t, tokens, pieces).blocker(start, entry);
        models.push({ id: t.id, name: t.name, along, blockedBy });
    }
    models.sort((a, b) => a.along - b.along);

    // The first place a model of this height could no longer see past, found by walking the line.
    const generic = prepareSight(from, from, tokens, pieces).blocker;
    let stopAt: Pt | null = null;
    let blockedBy: string | null = null;
    const step = 0.25;
    for (let d = step; d <= length + 1e-9; d += step) {
        const at = Math.min(d, length);
        const q = { x: start.x + dir.x * at, y: start.y + dir.y * at };
        const b = generic(start, q);
        if (b) {
            stopAt = q;
            blockedBy = b;
            break;
        }
    }
    return { models, stopAt, blockedBy, length };
}

// --- where a model may stand ---

export interface PlacementProblem {
    kind: 'overlap' | 'impassable' | 'hazardous';
    text: string;
}

/**
 * Problems with where a model stands. Bases may touch but not overlap another model's base, and nothing ends a move on impassable
 * terrain (p.20: a model that would is moved to the nearest legal spot). Hazardous terrain is only flagged. Markers and models removed
 * from play do not count.
 */
export function placementProblems(token: Token, tokens: Token[], pieces: TerrainPiece[]): PlacementProblem[] {
    if (token.marker || token.killed) return [];
    const problems: PlacementProblem[] = [];
    for (const other of tokens) {
        if (other.id === token.id || other.marker || other.killed) continue;
        if (Math.hypot(token.x - other.x, token.y - other.y) < baseRadius(token.baseMm) + baseRadius(other.baseMm) - 1e-6)
            problems.push({ kind: 'overlap', text: `overlaps ${other.name}'s base` });
    }
    for (const piece of pieces) {
        const touches = piece.surfaces.some((s) => supportFraction(token, s.shape) > 0);
        if (!touches) continue;
        if (piece.traits.includes('impassable')) problems.push({ kind: 'impassable', text: `is on ${piece.name}, which is impassable` });
        else if (piece.traits.includes('hazardous')) problems.push({ kind: 'hazardous', text: `is in hazardous terrain (${piece.name})` });
    }
    return problems;
}
