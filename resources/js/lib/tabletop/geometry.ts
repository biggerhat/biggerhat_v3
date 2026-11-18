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
    /** `angle` (degrees, clockwise on the table) turns it about its own centre; x, y, w and h describe it before the turn. */
    | { kind: 'rect'; x: number; y: number; w: number; h: number; angle?: number }
    | { kind: 'circle'; cx: number; cy: number; r: number }
    | { kind: 'ellipse'; cx: number; cy: number; rx: number; ry: number; angle?: number }
    | { kind: 'poly'; points: [number, number][] };

/** A standable plane. `top` is the elevation in inches of a model standing on it (already the sum of what supports it). */
export interface Surface {
    id: string;
    shape: Shape;
    top: number;
    /**
     * An archway beneath this level (the open part of a Bridge, p.57): models whose Sz plus elevation fits under this height stand and walk
     * beneath it, and sight passes under it. Models that are "under" do not stand on this level.
     */
    under?: number;
}

/** A door or window in the wall of an enterable building: the middle of the gap, on the building's outline, and how wide it is. */
export interface Opening {
    id: string;
    kind: 'door' | 'window';
    x: number;
    y: number;
    w: number;
}

/** What makes a building enterable (the Advanced Building Rules, p.58): the floor inside and the openings in its walls. Its inside is the outline of its lowest level. */
export interface Interior {
    /** The elevation of the floor inside: models inside stand at this height, not on the roof. */
    floor: number;
    openings: Opening[];
}

export interface TerrainPiece {
    id: string;
    name: string;
    /** Which kind of terrain it is (a preset key such as 'tower'), so it is drawn with that kind's texture. */
    kind?: string;
    traits: string[];
    surfaces: Surface[];
    /** Present when the building can be entered. */
    interior?: Interior;
    /** Special terrain (p.59): the text of its rules card, and how many of it may be on the table (1 is unique, "multiple (X)" is X). */
    rules?: string;
    limit?: number;
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
    /** Beneath the archway it overlaps (a bridge) rather than on top of it. */
    under?: boolean;
    /** A Strategy marker (p.46): a model may move through it but may not end a move on it. */
    strategy?: boolean;
    /** Terrain traits this model is unaffected by (p.57): blocking, concealing and dense are about the sight lines it draws; hazardous, severe and impassable about how it moves. */
    ignores?: string[];
}

/** The terrain traits a model can be made unaffected by. */
export const IGNORABLE_TRAITS = ['blocking', 'concealing', 'dense', 'hazardous', 'severe', 'impassable'] as const;

export const mmToInches = (mm: number): number => mm / MM_PER_INCH;
export const baseRadius = (baseMm: number): number => mmToInches(baseMm) / 2;

/** Turn a point about a centre by `deg` degrees (clockwise on the table, y pointing down). Quarter turns are exact. */
export function rotateAbout(p: [number, number], c: [number, number], deg: number): [number, number] {
    const d = ((deg % 360) + 360) % 360;
    const dx = p[0] - c[0];
    const dy = p[1] - c[1];
    if (d === 0) return [p[0], p[1]];
    if (d === 90) return [c[0] - dy, c[1] + dx];
    if (d === 180) return [c[0] - dx, c[1] - dy];
    if (d === 270) return [c[0] + dy, c[1] - dx];
    const cs = Math.cos((d * Math.PI) / 180);
    const sn = Math.sin((d * Math.PI) / 180);
    return [c[0] + dx * cs - dy * sn, c[1] + dx * sn + dy * cs];
}

/** How far a rectangle or ellipse is turned, in degrees; 0 for the shapes that have no turn of their own. */
export const shapeAngle = (shape: Shape): number => (shape.kind === 'rect' || shape.kind === 'ellipse' ? (shape.angle ?? 0) : 0);

/** The middle of a shape (for a drawn outline, the middle of its bounding box). */
export function shapeCentre(shape: Shape): [number, number] {
    if (shape.kind === 'rect') return [shape.x + shape.w / 2, shape.y + shape.h / 2];
    if (shape.kind === 'circle' || shape.kind === 'ellipse') return [shape.cx, shape.cy];
    const b = shapeBounds(shape);
    return [(b.minX + b.maxX) / 2, (b.minY + b.maxY) / 2];
}

/** A shape as a polygon: its corners as turned, or a ring of points for a curved shape. */
export function shapeOutline(shape: Shape, ring = 48): [number, number][] {
    if (shape.kind === 'poly') return shape.points;
    if (shape.kind === 'rect') {
        const c = shapeCentre(shape);
        const corners: [number, number][] = [
            [shape.x, shape.y],
            [shape.x + shape.w, shape.y],
            [shape.x + shape.w, shape.y + shape.h],
            [shape.x, shape.y + shape.h],
        ];
        return shape.angle ? corners.map((p) => rotateAbout(p, c, shape.angle ?? 0)) : corners;
    }
    const c: [number, number] = [shape.cx, shape.cy];
    const rx = shape.kind === 'circle' ? shape.r : shape.rx;
    const ry = shape.kind === 'circle' ? shape.r : shape.ry;
    const angle = shape.kind === 'ellipse' ? (shape.angle ?? 0) : 0;
    return Array.from({ length: ring }, (_, i) => {
        const t = (i / ring) * Math.PI * 2;
        return rotateAbout([c[0] + rx * Math.cos(t), c[1] + ry * Math.sin(t)], c, angle);
    });
}

export function pointInShape(shape: Shape, x: number, y: number): boolean {
    if (shape.kind === 'rect') {
        if (shape.angle) [x, y] = rotateAbout([x, y], shapeCentre(shape), -shape.angle);
        return x >= shape.x && x <= shape.x + shape.w && y >= shape.y && y <= shape.y + shape.h;
    }
    if (shape.kind === 'circle') return (x - shape.cx) ** 2 + (y - shape.cy) ** 2 <= shape.r ** 2;
    if (shape.kind === 'ellipse') {
        if (shape.angle) [x, y] = rotateAbout([x, y], [shape.cx, shape.cy], -shape.angle);
        return ((x - shape.cx) / shape.rx) ** 2 + ((y - shape.cy) / shape.ry) ** 2 <= 1;
    }
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
    const unit = unitDisc(count);
    return unit.map(([ux, uy]) => [cx + r * ux, cy + r * uy] as [number, number]);
}

/** The sample pattern for a disc of radius 1, worked out once per count (it is asked for on every drag move). */
const UNIT_DISCS = new Map<number, [number, number][]>();
function unitDisc(count: number): [number, number][] {
    let unit = UNIT_DISCS.get(count);
    if (!unit) {
        const golden = Math.PI * (3 - Math.sqrt(5));
        unit = Array.from({ length: count }, (_, i) => {
            const d = Math.sqrt((i + 0.5) / count);
            return [d * Math.cos(i * golden), d * Math.sin(i * golden)] as [number, number];
        });
        UNIT_DISCS.set(count, unit);
    }
    return unit;
}

/** A shape's bounds, kept per shape object: pieces are replaced, not edited, so a stale entry cannot be reached. */
const BOUNDS = new WeakMap<object, Bounds>();
function cachedBounds(shape: Shape): Bounds {
    let b = BOUNDS.get(shape);
    if (!b) {
        b = shapeBounds(shape);
        BOUNDS.set(shape, b);
    }
    return b;
}

/** The share (0..1) of a base that lies over a shape. */
export function supportFraction(token: Pick<Token, 'x' | 'y' | 'baseMm'>, shape: Shape): number {
    const r = baseRadius(token.baseMm);
    const b = cachedBounds(shape);
    // a base nowhere near the shape needs none of the 96 sample points
    if (token.x + r < b.minX || token.x - r > b.maxX || token.y + r < b.minY || token.y - r > b.maxY) return 0;
    const pts = discSamples(token.x, token.y, r);
    let hits = 0;
    for (const [x, y] of pts) if (pointInShape(shape, x, y)) hits++;
    return hits / pts.length;
}

/** The archway a base overlaps (any part of it), if there is one: the level and the piece it belongs to. */
export function archwayAt(token: Pick<Token, 'x' | 'y' | 'baseMm'>, pieces: TerrainPiece[]): { piece: TerrainPiece; surface: Surface } | null {
    for (const piece of pieces) {
        for (const surface of piece.surfaces) {
            if (surface.under !== undefined && supportFraction(token, surface.shape) > 0) return { piece, surface };
        }
    }
    return null;
}

/**
 * What a base overlaps that it could be beneath: an archway (a bridge) or the roof of an enterable building. `roof` is the height of the
 * highest level, which is how high a model must already be to be on top of it rather than under or inside.
 */
export function coverAt(
    token: Pick<Token, 'x' | 'y' | 'baseMm'>,
    pieces: TerrainPiece[],
): { kind: 'archway' | 'building'; piece: TerrainPiece; roof: number } | null {
    const arch = archwayAt(token, pieces);
    if (arch) return { kind: 'archway', piece: arch.piece, roof: arch.surface.top };
    for (const piece of pieces) {
        const region = interiorShape(piece);
        if (piece.interior && region && supportFraction(token, region) > 0)
            return { kind: 'building', piece, roof: Math.max(...piece.surfaces.map((x) => x.top)) };
    }
    return null;
}

/**
 * Whether a model that has just been put down is under the archway it overlaps (or inside the building it overlaps) or on top of it. One
 * that came from below the roof is under or inside; one that was already as high is on it. undefined when it overlaps neither.
 */
export function settleUnder(token: Pick<Token, 'x' | 'y' | 'baseMm'>, startElevation: number, pieces: TerrainPiece[]): boolean | undefined {
    const cover = coverAt(token, pieces);
    if (!cover) return undefined;
    return startElevation < cover.roof - 1e-9;
}

export interface Support {
    /** The surface the base rests on, or null for the bare table. */
    surface: Surface | null;
    piece: TerrainPiece | null;
    /** Elevation in inches. The table is 0. */
    elevation: number;
}

/** A base rests on the highest surface holding at least half of it; otherwise on the table (p.19-20). */
export function restingSupport(token: Pick<Token, 'x' | 'y' | 'baseMm' | 'under'>, pieces: TerrainPiece[]): Support {
    let best: Support = { surface: null, piece: null, elevation: 0 };
    for (const piece of pieces) {
        // Only climbable terrain supports a base (p.19): woods, fog and the like are drawn but never stood on.
        if (!piece.traits.includes('climbable')) continue;
        for (const surface of piece.surfaces) {
            // A model beneath an archway, or inside a building, is not standing on it.
            if (token.under && (surface.under !== undefined || piece.interior)) continue;
            if (surface.top <= best.elevation) continue;
            if (supportFraction(token, surface.shape) >= SUPPORT_MIN) best = { surface, piece, elevation: surface.top };
        }
    }
    // Inside an enterable building a model stands on its floor.
    const building = token.under ? insideOf(token, pieces) : null;
    if (building?.interior && building.interior.floor >= best.elevation)
        best = { surface: null, piece: building, elevation: building.interior.floor };
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
    /** Terrain traits the area counts as (aura token terrain, p.57): concealing, dense, hazardous or severe. */
    traits?: string[];
}

/** The traits an aura can give the ground it covers. */
export const AURA_TRAITS = ['concealing', 'dense', 'hazardous', 'severe'] as const;

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

/**
 * How far from where it started the middle of a placed model may end (p.17). A place is measured from the closest point of the starting
 * base: if the effect only needs a single point of the new base within range, the middle may be the range plus both radii away; if the new
 * base must be completely within range, the range plus the starting radius minus the new one.
 */
export function placeReach(range: number, startMm: number, endMm: number, whole = false): number {
    return range + baseRadius(startMm) + (whole ? -baseRadius(endMm) : baseRadius(endMm));
}

/** A place compared with its range: the gap between the two bases (0 if they overlap), whether it is in range, and how far past it is when not. */
export function placeCheck(
    range: number,
    from: Pick<Token, 'x' | 'y' | 'baseMm'>,
    to: Pick<Token, 'x' | 'y' | 'baseMm'>,
    whole = false,
): { gap: number; within: boolean; over: number } {
    const centres = Math.hypot(to.x - from.x, to.y - from.y);
    const reach = placeReach(range, from.baseMm, to.baseMm, whole);
    return {
        gap: Math.max(0, centres - baseRadius(from.baseMm) - baseRadius(to.baseMm)),
        within: centres <= reach + 1e-9,
        over: Math.max(0, centres - reach),
    };
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
    return { kind: 'poly', points: shapeOutline(shape).map(turn) };
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
    const pts = shape.kind === 'poly' ? shape.points : [shapeCentre(shape)];
    const cx = pts.reduce((n, p) => n + p[0], 0) / pts.length;
    const cy = pts.reduce((n, p) => n + p[1], 0) / pts.length;
    const v = cy < T / 3 ? 'top' : cy > (2 * T) / 3 ? 'bottom' : '';
    const h = cx < T / 3 ? 'left' : cx > (2 * T) / 3 ? 'right' : '';
    return [v, h].filter(Boolean).join('-') || 'middle';
}

/** Is a point inside a zone, on the table? */
export const inZone = (shape: Shape, x: number, y: number): boolean => x >= 0 && x <= T && y >= 0 && y <= T && pointInShape(shape, x, y);

// ---- terrain editing -------------------------------------------------------------------------------------------------------------

export const TERRAIN_TRAITS = ['blocking', 'climbable', 'impassable', 'concealing', 'dense', 'hazardous', 'destructible', 'severe'] as const;
export type TerrainTrait = (typeof TERRAIN_TRAITS)[number];
export const SURFACE_MIN_TOP = 0;
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
    if (shape.kind === 'rect' && !shape.angle) return { minX: shape.x, minY: shape.y, maxX: shape.x + shape.w, maxY: shape.y + shape.h };
    if (shape.kind === 'circle') return { minX: shape.cx - shape.r, minY: shape.cy - shape.r, maxX: shape.cx + shape.r, maxY: shape.cy + shape.r };
    if (shape.kind === 'ellipse') {
        const a = ((shape.angle ?? 0) * Math.PI) / 180;
        const hw = Math.hypot(shape.rx * Math.cos(a), shape.ry * Math.sin(a));
        const hh = Math.hypot(shape.rx * Math.sin(a), shape.ry * Math.cos(a));
        return { minX: shape.cx - hw, minY: shape.cy - hh, maxX: shape.cx + hw, maxY: shape.cy + hh };
    }
    const pts = shapeOutline(shape);
    const xs = pts.map((p) => p[0]);
    const ys = pts.map((p) => p[1]);
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
    if (shape.kind === 'circle' || shape.kind === 'ellipse') return { ...shape, cx: shape.cx + dx, cy: shape.cy + dy };
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

/** An angle in degrees folded into (-90, 90], which is every distinct turn of a shape that looks the same turned half way round. */
const foldAngle = (deg: number): number => {
    let a = ((((deg % 180) + 180) % 180) * 1e6) / 1e6;
    if (a > 90) a -= 180;
    return Math.round(a * 1e6) / 1e6;
};

/** One shape turned about a point. A rectangle or ellipse keeps its own angle; a quarter turn just swaps its sides. */
export function rotateShape(shape: Shape, about: [number, number], deg: number): Shape {
    if (shape.kind === 'circle') {
        const [cx, cy] = rotateAbout([shape.cx, shape.cy], about, deg);
        return { ...shape, cx, cy };
    }
    if (shape.kind === 'poly') return { kind: 'poly', points: shape.points.map((p) => rotateAbout(p, about, deg)) };
    const [nx, ny] = rotateAbout(shapeCentre(shape), about, deg);
    let a = foldAngle(shapeAngle(shape) + deg);
    if (shape.kind === 'rect') {
        let { w, h } = shape;
        if (Math.abs(a) === 90) [w, h, a] = [h, w, 0];
        const out: Shape = { kind: 'rect', x: nx - w / 2, y: ny - h / 2, w, h };
        return a ? { ...out, angle: a } : out;
    }
    let { rx, ry } = shape;
    if (Math.abs(a) === 90) [rx, ry, a] = [ry, rx, 0];
    const out: Shape = { kind: 'ellipse', cx: nx, cy: ny, rx, ry };
    return a ? { ...out, angle: a } : out;
}

/** Turn a whole piece, every level together, about the middle of its bounds, kept inside the table. */
export function rotatePiece(piece: TerrainPiece, deg: number): TerrainPiece {
    const b = pieceBounds(piece);
    const about: [number, number] = [(b.minX + b.maxX) / 2, (b.minY + b.maxY) / 2];
    return movePiece({ ...piece, surfaces: piece.surfaces.map((s) => ({ ...s, shape: rotateShape(s.shape, about, deg) })) }, 0, 0);
}

/** Turn a piece a quarter turn about its own centre (every level together), kept inside the table. */
export const rotatePiece90 = (piece: TerrainPiece): TerrainPiece => rotatePiece(piece, 90);

/** Set one rectangle or ellipse to an exact angle about its own middle; other shapes are left alone. */
export function setShapeAngle(shape: Shape, deg: number): Shape {
    if (shape.kind !== 'rect' && shape.kind !== 'ellipse') return shape;
    const a = foldAngle(deg);
    const rest: Shape = { ...shape };
    delete rest.angle;
    return a ? { ...rest, angle: a } : rest;
}

// ---- reshaping a level by dragging its handles -----------------------------------------------------------------------------------

export type HandleKind = 'corner' | 'edge' | 'radius' | 'vertex' | 'mid' | 'opening';
export interface ShapeHandle {
    /** Stable within a shape: corner-0..3, edge-0..3 (rx/ry ends), radius, vertex-N, mid-N (the middle of the edge after corner N). */
    id: string;
    kind: HandleKind;
    index: number;
    x: number;
    y: number;
}

export const POLY_MAX_POINTS = 16;
const snapHalfInch = (n: number): number => Math.round(n * 2) / 2;
const clampCoord = (n: number): number => Math.min(TABLE_SIZE, Math.max(0, n));

/** Where the handles of a level sit: corners of a rectangle, the ends of an ellipse's axes, a circle's east edge, a drawn outline's corners and edge middles. */
export function shapeHandles(shape: Shape): ShapeHandle[] {
    if (shape.kind === 'rect') return shapeOutline(shape).map(([x, y], i) => ({ id: `corner-${i}`, kind: 'corner' as const, index: i, x, y }));
    if (shape.kind === 'circle') return [{ id: 'radius', kind: 'radius', index: 0, x: shape.cx + shape.r, y: shape.cy }];
    if (shape.kind === 'ellipse') {
        const c: [number, number] = [shape.cx, shape.cy];
        const ends: [number, number][] = [
            [shape.cx + shape.rx, shape.cy],
            [shape.cx, shape.cy + shape.ry],
            [shape.cx - shape.rx, shape.cy],
            [shape.cx, shape.cy - shape.ry],
        ];
        return ends.map((p, i) => {
            const [x, y] = rotateAbout(p, c, shape.angle ?? 0);
            return { id: `edge-${i}`, kind: 'edge' as const, index: i, x, y };
        });
    }
    const out: ShapeHandle[] = shape.points.map(([x, y], i) => ({ id: `vertex-${i}`, kind: 'vertex' as const, index: i, x, y }));
    if (shape.points.length < POLY_MAX_POINTS)
        shape.points.forEach(([x, y], i) => {
            const [nx, ny] = shape.points[(i + 1) % shape.points.length];
            out.push({ id: `mid-${i}`, kind: 'mid', index: i, x: (x + nx) / 2, y: (y + ny) / 2 });
        });
    return out;
}

/**
 * Drag one handle of a level to a point on the table. A rectangle keeps the opposite corner where it is and follows its own angle; an
 * ellipse's axis end sets that radius; a circle's handle sets the radius; an outline's corner moves. Sizes and corners snap to half inches.
 */
export function dragShapeHandle(shape: Shape, handle: Pick<ShapeHandle, 'kind' | 'index'>, to: { x: number; y: number }): Shape {
    if (shape.kind === 'rect' && handle.kind === 'corner') {
        const corners = shapeOutline(shape);
        const [ox, oy] = corners[(handle.index + 2) % 4];
        const a = ((shape.angle ?? 0) * Math.PI) / 180;
        const u = [Math.cos(a), Math.sin(a)];
        const v = [-Math.sin(a), Math.cos(a)];
        const dx = to.x - ox;
        const dy = to.y - oy;
        const pu = dx * u[0] + dy * u[1];
        const pv = dx * v[0] + dy * v[1];
        const w = Math.min(TABLE_SIZE, Math.max(0.5, snapHalfInch(Math.abs(pu))));
        const h = Math.min(TABLE_SIZE, Math.max(0.5, snapHalfInch(Math.abs(pv))));
        const su = pu < 0 ? -1 : 1;
        const sv = pv < 0 ? -1 : 1;
        const cx = ox + (su * w * u[0] + sv * h * v[0]) / 2;
        const cy = oy + (su * w * u[1] + sv * h * v[1]) / 2;
        const out: Shape = { kind: 'rect', x: cx - w / 2, y: cy - h / 2, w, h };
        return shape.angle ? { ...out, angle: shape.angle } : out;
    }
    if (shape.kind === 'circle' && handle.kind === 'radius') {
        const r = Math.min(TABLE_SIZE, Math.max(0.5, snapHalfInch(Math.hypot(to.x - shape.cx, to.y - shape.cy))));
        return { ...shape, r };
    }
    if (shape.kind === 'ellipse' && handle.kind === 'edge') {
        const [lx, ly] = rotateAbout([to.x, to.y], [shape.cx, shape.cy], -(shape.angle ?? 0));
        if (handle.index % 2 === 0) return { ...shape, rx: Math.min(TABLE_SIZE, Math.max(0.5, snapHalfInch(Math.abs(lx - shape.cx)))) };
        return { ...shape, ry: Math.min(TABLE_SIZE, Math.max(0.5, snapHalfInch(Math.abs(ly - shape.cy)))) };
    }
    if (shape.kind === 'poly' && handle.kind === 'vertex') {
        const points = shape.points.map((p, i) =>
            i === handle.index ? ([clampCoord(snapHalfInch(to.x)), clampCoord(snapHalfInch(to.y))] as [number, number]) : p,
        );
        return { kind: 'poly', points };
    }
    return shape;
}

/** Put a new corner on a drawn outline in the middle of the edge after corner `index`; null when it is full. */
export function insertCorner(shape: Shape, index: number): Shape | null {
    if (shape.kind !== 'poly' || shape.points.length >= POLY_MAX_POINTS) return null;
    const [x, y] = shape.points[index];
    const [nx, ny] = shape.points[(index + 1) % shape.points.length];
    const points = [...shape.points];
    points.splice(index + 1, 0, [snapHalfInch((x + nx) / 2), snapHalfInch((y + ny) / 2)]);
    return { kind: 'poly', points };
}

/** Take a corner off a drawn outline; null when only three remain. */
export function removeCorner(shape: Shape, index: number): Shape | null {
    if (shape.kind !== 'poly' || shape.points.length <= 3) return null;
    return { kind: 'poly', points: shape.points.filter((_, i) => i !== index) };
}

/** A marker a player can put on the table: name and base size come from the markers table. */
export interface MarkerOption {
    id: number;
    name: string;
    base_mm: number;
    /** A Strategy marker (p.46). */
    strategy?: boolean;
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
    if (shape.kind === 'rect' && !shape.angle) {
        const dx = Math.max(shape.x - x, 0, x - (shape.x + shape.w));
        const dy = Math.max(shape.y - y, 0, y - (shape.y + shape.h));
        return Math.hypot(dx, dy);
    }
    if (shape.kind === 'circle') return Math.max(0, Math.hypot(x - shape.cx, y - shape.cy) - shape.r);
    if (pointInShape(shape, x, y)) return 0;
    const pts = shapeOutline(shape);
    let best = Infinity;
    for (let i = 0, j = pts.length - 1; i < pts.length; j = i++) {
        const [ax, ay] = pts[j];
        const [bx, by] = pts[i];
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
const fmtNum = (n: number): string => String(Math.round(n * 10) / 10);

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
    // A model inside a building does not ignore it (p.58): its walls block, except through its doors and windows.
    const insideA = from.under ? insideOf(from, pieces) : null;
    const insideB = to.under ? insideOf(to, pieces) : null;
    const walled = pieces.filter((pc) => pc.interior && (pc.id === insideA?.id || pc.id === insideB?.id));
    const effA = szOf(from) + supportA.elevation;
    const effB = szOf(to) + supportB.elevation;
    const lo = Math.min(effA, effB);
    const hi = Math.max(effA, effB);
    const shorter = effA <= effB ? from : to;

    // The model drawing the lines may be unaffected by some traits (p.57): then that kind of terrain cannot block it or hide its targets.
    const unaffected = new Set(from.ignores ?? []);
    // Terrain that can matter: blocking or dense, minus what either model stands on or above.
    const surfaces = pieces
        .filter((piece) => !walled.includes(piece))
        .flatMap((piece) =>
            piece.surfaces
                .filter(
                    (s) => ![supportA, supportB].some((sup) => sup.surface?.id === s.id || (sup.piece?.id === piece.id && s.top <= sup.elevation)),
                )
                // Sight passes under an archway when both models fit beneath it.
                .filter((s) => s.under === undefined || hi > s.under + 1e-9)
                .map((s) => ({
                    piece,
                    s,
                    blocking: piece.traits.includes('blocking') && !unaffected.has('blocking'),
                    dense: piece.traits.includes('dense') && !unaffected.has('dense'),
                    concealing: piece.traits.includes('concealing') && !unaffected.has('concealing'),
                })),
        );

    // Enemy models in the way, with their effective size.
    const enemies = tokens
        .filter((t) => t.id !== from.id && t.id !== to.id && !t.marker && t.side !== 'n' && t.side !== from.side)
        .map((t) => ({ t, h: szOf(t) + restingSupport(t, pieces).elevation }));

    const near = (d: number) => d <= SIGHT_NEAR + 1e-9;

    function blocker(p: Pt, q: Pt): string | null {
        for (const piece of walled) {
            const wall = wallBlocks(piece, p, q, insideA?.id === piece.id && insideB?.id === piece.id);
            if (wall) return piece.name;
        }
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
    // Once the line is blocked it stays blocked further along, so the first blocked quarter inch is found by bisection, not by walking.
    const step = 0.25;
    const steps = Math.ceil(length / step - 1e-9);
    const at = (i: number) => Math.min(i * step, length);
    const pointAt = (i: number) => ({ x: start.x + dir.x * at(i), y: start.y + dir.y * at(i) });
    if (steps >= 1 && generic(start, pointAt(steps))) {
        let lo = 1;
        let hi = steps;
        while (lo < hi) {
            const mid = (lo + hi) >> 1;
            if (generic(start, pointAt(mid))) hi = mid;
            else lo = mid + 1;
        }
        stopAt = pointAt(lo);
        blockedBy = generic(start, stopAt);
    }
    return { models, stopAt, blockedBy, length };
}

// --- where a model may stand ---

export interface PlacementProblem {
    kind: 'overlap' | 'impassable' | 'hazardous' | 'severe';
    text: string;
}

/**
 * Problems with where a model stands. Bases may touch but not overlap another model's base, and nothing ends a move on impassable
 * terrain (p.20: a model that would is moved to the nearest legal spot). Hazardous and severe terrain are only flagged. Markers and models removed
 * from play do not count.
 */
export function placementProblems(token: Token, tokens: Token[], pieces: TerrainPiece[]): PlacementProblem[] {
    if (token.killed) return [];
    const problems: PlacementProblem[] = [];
    // A marker may overlap models and other markers, but may not be made on impassable terrain (p.45).
    if (token.marker) {
        for (const piece of pieces) {
            if (piece.traits.includes('impassable') && piece.surfaces.some((s) => supportFraction(token, s.shape) > 0))
                problems.push({
                    kind: 'impassable',
                    text: `is on ${piece.name}, which is impassable (a marker may not be put on impassable terrain)`,
                });
        }
        return problems;
    }
    for (const other of tokens) {
        if (other.id === token.id || other.killed) continue;
        // Models usually move through and overlap markers, but a Strategy marker may be moved through, not ended on (p.46).
        if (other.marker && !other.strategy) continue;
        if (Math.hypot(token.x - other.x, token.y - other.y) < baseRadius(token.baseMm) + baseRadius(other.baseMm) - 1e-6)
            problems.push({
                kind: 'overlap',
                text: other.marker
                    ? `ends on the ${other.name}: a model may move through a Strategy marker but not end a move on it`
                    : `overlaps ${other.name}'s base`,
            });
    }
    for (const piece of pieces) {
        const touches = piece.surfaces.some((s) => supportFraction(token, s.shape) > 0);
        if (!touches) continue;
        // Under an archway only a model that fits beneath it may stand there (p.57: Sz plus elevation lower than the archway).
        const arch = token.under ? piece.surfaces.find((s) => s.under !== undefined && supportFraction(token, s.shape) > 0) : undefined;
        if (arch && arch.under !== undefined) {
            const height = szOf(token) + restingSupport(token, pieces).elevation;
            if (height > arch.under + 1e-9)
                problems.push({
                    kind: 'impassable',
                    text: `is too tall (Sz ${fmtNum(height)}) to fit under ${piece.name}, whose archway is ${fmtNum(arch.under)}" high`,
                });
            continue;
        }
        if (piece.traits.includes('impassable')) problems.push({ kind: 'impassable', text: `is on ${piece.name}, which is impassable` });
        else {
            if (piece.traits.includes('hazardous') && !token.ignores?.includes('hazardous'))
                problems.push({ kind: 'hazardous', text: `is in hazardous terrain (${piece.name})` });
            if (piece.traits.includes('severe') && !token.ignores?.includes('severe'))
                problems.push({ kind: 'severe', text: `is in severe terrain (${piece.name}), where movement costs double` });
        }
    }
    return problems;
}

// ---- enterable buildings: inside, walls, doors and windows (Advanced Building Rules, p.58) ---------------------------------------------

/** The inside of an enterable building: the outline of its lowest level. null when the piece cannot be entered. */
export function interiorShape(piece: TerrainPiece): Shape | null {
    if (!piece.interior || !piece.surfaces.length) return null;
    return [...piece.surfaces].sort((a, b) => a.top - b.top)[0].shape;
}

/** The enterable building a model is inside: it is under the roof, and any part of its base is within the interior (p.58). */
export function insideOf(token: Pick<Token, 'x' | 'y' | 'baseMm' | 'under'>, pieces: TerrainPiece[]): TerrainPiece | null {
    if (!token.under) return null;
    for (const piece of pieces) {
        const region = interiorShape(piece);
        if (region && supportFraction(token, region) > 0) return piece;
    }
    return null;
}

/** The edges of a shape's outline, as pairs of points. */
function outlineEdges(shape: Shape): [Pt, Pt][] {
    const pts = shapeOutline(shape);
    return pts.map((a, i) => {
        const b = pts[(i + 1) % pts.length];
        return [
            { x: a[0], y: a[1] },
            { x: b[0], y: b[1] },
        ];
    });
}

/** Where a segment crosses the outline of a shape. */
export function segmentCrossings(p: Pt, q: Pt, shape: Shape): Pt[] {
    const out: Pt[] = [];
    for (const [a, b] of outlineEdges(shape)) {
        const d = (q.x - p.x) * (b.y - a.y) - (q.y - p.y) * (b.x - a.x);
        if (Math.abs(d) < 1e-12) continue;
        const t = ((a.x - p.x) * (b.y - a.y) - (a.y - p.y) * (b.x - a.x)) / d;
        const u = ((a.x - p.x) * (q.y - p.y) - (a.y - p.y) * (q.x - p.x)) / d;
        if (t >= -1e-9 && t <= 1 + 1e-9 && u >= -1e-9 && u <= 1 + 1e-9) out.push({ x: p.x + t * (q.x - p.x), y: p.y + t * (q.y - p.y) });
    }
    return out;
}

/** The nearest point on a shape's outline to a point, and which edge it is on. */
export function nearestOnOutline(shape: Shape, x: number, y: number): { x: number; y: number; edge: number } {
    let best = { x, y, edge: 0, d: Infinity };
    outlineEdges(shape).forEach(([a, b], i) => {
        const len2 = (b.x - a.x) ** 2 + (b.y - a.y) ** 2;
        const t = len2 === 0 ? 0 : Math.max(0, Math.min(1, ((x - a.x) * (b.x - a.x) + (y - a.y) * (b.y - a.y)) / len2));
        const px = a.x + t * (b.x - a.x);
        const py = a.y + t * (b.y - a.y);
        const d = Math.hypot(x - px, y - py);
        if (d < best.d) best = { x: px, y: py, edge: i, d };
    });
    return { x: best.x, y: best.y, edge: best.edge };
}

/** The two ends of an opening's gap, along the wall it is in. null when the building has no inside. */
export function openingSpan(piece: TerrainPiece, opening: Opening): [Pt, Pt] | null {
    const region = interiorShape(piece);
    if (!region) return null;
    const near = nearestOnOutline(region, opening.x, opening.y);
    const [a, b] = outlineEdges(region)[near.edge];
    const len = Math.hypot(b.x - a.x, b.y - a.y) || 1;
    const dx = (b.x - a.x) / len;
    const dy = (b.y - a.y) / len;
    const h = opening.w / 2;
    return [
        { x: near.x - dx * h, y: near.y - dy * h },
        { x: near.x + dx * h, y: near.y + dy * h },
    ];
}

/** Is a point on the wall inside one of the building's openings of an allowed kind, wide enough for `minWidth`? */
export function passesOpening(piece: TerrainPiece, point: Pt, kinds: Opening['kind'][] = ['door', 'window'], minWidth = 0): boolean {
    return (piece.interior?.openings ?? []).some((o) => {
        if (!kinds.includes(o.kind) || o.w + 1e-9 < minWidth) return false;
        const span = openingSpan(piece, o);
        return !!span && distanceToSegment(point, span[0], span[1]) <= 0.08;
    });
}

/**
 * Does the building's wall stop a sight line when one of the models is inside it? Models inside the same building see each other. Otherwise the
 * line must cross the wall through an open door or window; the wall above and below one does not block a line into or out of the building.
 */
export function wallBlocks(piece: TerrainPiece, p: Pt, q: Pt, bothInside: boolean): boolean {
    const region = interiorShape(piece);
    if (!region || bothInside) return false;
    const hits = segmentCrossings(p, q, region);
    if (!hits.length) return false;
    return !hits.every((h) => passesOpening(piece, h));
}

/**
 * What stops a model walking from one spot to another into or out of a building: it has to go through a door (or hole) at least as wide as its
 * base (p.58); a window does not let it through. null when the walk is fine.
 */
export function wallProblem(piece: TerrainPiece, from: Pt, to: Pt, baseMm: number): string | null {
    const region = interiorShape(piece);
    if (!region) return null;
    const need = baseRadius(baseMm) * 2;
    const hits = segmentCrossings(from, to, region);
    if (!hits.length) return null;
    const ok = hits.every((h) => passesOpening(piece, h, ['door'], need - 1e-9));
    if (ok) return null;
    const door = (piece.interior?.openings ?? []).some((o) => o.kind === 'door' && o.w + 1e-9 >= need);
    return door
        ? `goes through the wall of ${piece.name}: a model enters by a door`
        : `goes through the wall of ${piece.name}, which has no door as wide as its ${Math.round(need * 10) / 10}" base`;
}

/** Make a building enterable: a floor at ground level and a door in the middle of its longest wall. */
export function makeEnterable(piece: TerrainPiece): TerrainPiece {
    const next: TerrainPiece = { ...piece, interior: { floor: 0, openings: [] } };
    return addOpening(next, 'door') ?? next;
}

export const OPENINGS_MAX = 8;

/** A new door or window in the middle of the longest wall that has the fewest openings so far; null when the building is full or has no inside. */
export function addOpening(piece: TerrainPiece, kind: Opening['kind']): TerrainPiece | null {
    const region = interiorShape(piece);
    if (!region || !piece.interior || piece.interior.openings.length >= OPENINGS_MAX) return null;
    const edges = outlineEdges(region);
    const used = (i: number) => piece.interior!.openings.filter((o) => nearestOnOutline(region, o.x, o.y).edge === i).length;
    const edge = edges
        .map((e, i) => ({ e, i, len: Math.hypot(e[1].x - e[0].x, e[1].y - e[0].y) }))
        .sort((a, b) => used(a.i) - used(b.i) || b.len - a.len)[0];
    const w = Math.min(kind === 'door' ? 2 : 1.5, edge.len);
    const opening: Opening = {
        id: `o${Date.now().toString(36)}${piece.interior.openings.length}`,
        kind,
        x: (edge.e[0].x + edge.e[1].x) / 2,
        y: (edge.e[0].y + edge.e[1].y) / 2,
        w,
    };
    return { ...piece, interior: { ...piece.interior, openings: [...piece.interior.openings, opening] } };
}

/** Slide an opening along the walls to the point nearest where it was dragged (snapped to half inches along the wall). */
export function moveOpening(piece: TerrainPiece, id: string, to: Pt): TerrainPiece {
    const region = interiorShape(piece);
    if (!region || !piece.interior) return piece;
    const near = nearestOnOutline(region, to.x, to.y);
    const snap = (n: number) => Math.round(n * 2) / 2;
    const snapped = nearestOnOutline(region, snap(near.x), snap(near.y));
    return {
        ...piece,
        interior: { ...piece.interior, openings: piece.interior.openings.map((o) => (o.id === id ? { ...o, x: snapped.x, y: snapped.y } : o)) },
    };
}

/** Take the interior (and so its openings) away again. */
export function makeSolid(piece: TerrainPiece): TerrainPiece {
    const next: TerrainPiece = { ...piece };
    delete next.interior;
    return next;
}

/** The stretches of an enterable building's outline that are solid wall: every edge, minus the gaps of its doors and windows. */
export function wallSegments(piece: TerrainPiece): [Pt, Pt][] {
    const region = interiorShape(piece);
    if (!region) return [];
    const edges = outlineEdges(region);
    const out: [Pt, Pt][] = [];
    edges.forEach(([a, b], i) => {
        const len = Math.hypot(b.x - a.x, b.y - a.y);
        if (len < 1e-9) return;
        const gaps = (piece.interior?.openings ?? [])
            .filter((o) => nearestOnOutline(region, o.x, o.y).edge === i)
            .map((o) => {
                const near = nearestOnOutline(region, o.x, o.y);
                const t = Math.hypot(near.x - a.x, near.y - a.y) / len;
                const half = o.w / 2 / len;
                return [Math.max(0, t - half), Math.min(1, t + half)] as [number, number];
            })
            .sort((x, y) => x[0] - y[0]);
        let from = 0;
        for (const [g0, g1] of gaps) {
            if (g0 > from + 1e-6) out.push([lerpPt(a, b, from), lerpPt(a, b, g0)]);
            from = Math.max(from, g1);
        }
        if (from < 1 - 1e-6) out.push([lerpPt(a, b, from), lerpPt(a, b, 1)]);
    });
    return out;
}

const lerpPt = (a: Pt, b: Pt, t: number): Pt => ({ x: a.x + (b.x - a.x) * t, y: a.y + (b.y - a.y) * t });

// ---- what a walk crosses on the way (Terrain traits, p.56) ---------------------------------------------------------------------------

export interface LegTerrain {
    /** Inches of the leg travelled with any part of the base in severe terrain: each costs twice as much movement, so this is the extra cost. */
    severe: number;
    /** Hazardous terrain the base touched on the way: moving through it, or into base contact with it, triggers it. */
    hazardous: string[];
    /** Impassable terrain the base would have to pass through. */
    impassable: string[];
}

const LEG_STEP = 0.1;

/**
 * Walk a base from one spot to another in a straight line and say which terrain it meets: how far it travels in severe terrain, which
 * hazardous terrain it touches and which impassable terrain is in the way. Only the ground is looked at (a model on or above a piece is
 * not "in" it for movement purposes only when it climbs, which is counted as height elsewhere), and the checks are for a Walk: a
 * model that ignores terrain, or is placed, is not asked.
 */
export function terrainAlongLeg(from: Pt, to: Pt, baseMm: number, pieces: TerrainPiece[], ignores: string[] = []): LegTerrain {
    const r = baseRadius(baseMm);
    const steps = Math.max(1, Math.ceil(Math.hypot(to.x - from.x, to.y - from.y) / LEG_STEP));
    const hazardous = new Set<string>();
    const impassable = new Set<string>();
    let severeSteps = 0;
    const meets = (piece: TerrainPiece, x: number, y: number) =>
        piece.surfaces.some((s) => {
            const b = cachedBounds(s.shape);
            // too far from the shape's bounds to be within a base's reach of it
            if (x < b.minX - r || x > b.maxX + r || y < b.minY - r || y > b.maxY + r) return false;
            return distanceToShape(s.shape, x, y) < r;
        });
    const traited = (trait: string) => pieces.filter((p) => p.traits.includes(trait));
    const severe = ignores.includes('severe') ? [] : traited('severe');
    const hazards = ignores.includes('hazardous') ? [] : traited('hazardous');
    const walls = ignores.includes('impassable') ? [] : traited('impassable');
    for (let i = 0; i <= steps; i++) {
        const x = from.x + ((to.x - from.x) * i) / steps;
        const y = from.y + ((to.y - from.y) * i) / steps;
        if (i > 0 && severe.some((p) => meets(p, x, y))) severeSteps++;
        for (const p of hazards) if (!hazardous.has(p.name) && meets(p, x, y)) hazardous.add(p.name);
        for (const p of walls) if (!impassable.has(p.name) && meets(p, x, y)) impassable.add(p.name);
    }
    return { severe: (severeSteps * Math.hypot(to.x - from.x, to.y - from.y)) / steps, hazardous: [...hazardous], impassable: [...impassable] };
}

/** A whole path, a leg at a time. */
export function terrainAlongPath(points: Pt[], baseMm: number, pieces: TerrainPiece[], ignores: string[] = []): LegTerrain {
    const out: LegTerrain = { severe: 0, hazardous: [], impassable: [] };
    for (let i = 1; i < points.length; i++) {
        const leg = terrainAlongLeg(points[i - 1], points[i], baseMm, pieces, ignores);
        out.severe += leg.severe;
        out.hazardous = [...new Set([...out.hazardous, ...leg.hazardous])];
        out.impassable = [...new Set([...out.impassable, ...leg.impassable])];
    }
    return out;
}

// ---- the nearest legal spot (p.20) --------------------------------------------------------------------------------------------------

/**
 * Where a model that ended somewhere it may not be (over another base, or on impassable terrain) goes instead: the closest spot that is legal,
 * found by looking outward in rings a quarter inch apart. Hazardous and severe terrain are legal to stand in. null when the model is already
 * somewhere legal, or when nothing within `maxRadius` is.
 */
export function nearestLegalSpot(
    token: Token,
    tokens: Token[],
    pieces: TerrainPiece[],
    maxRadius = 14,
    step = 0.25,
): { x: number; y: number } | null {
    const legal = (x: number, y: number) =>
        !placementProblems({ ...token, x, y }, tokens, pieces).some((p) => p.kind === 'overlap' || p.kind === 'impassable');
    if (legal(token.x, token.y)) return null;
    for (let r = step; r <= maxRadius + 1e-9; r += step) {
        const around = Math.max(8, Math.ceil((2 * Math.PI * r) / step));
        for (let i = 0; i < around; i++) {
            const a = (i / around) * 2 * Math.PI;
            const spot = clampToTable({ ...token, x: token.x + r * Math.cos(a), y: token.y + r * Math.sin(a) });
            if (legal(spot.x, spot.y)) return spot;
        }
    }
    return null;
}

// ---- where a base is on the table (Table Edge, Half, Corner and Quarter, p.60) -----------------------------------------------------

export interface Whereabouts {
    /** The 18" quarters any part of the base is in, such as "top-left". */
    quarters: string[];
    /** The nearest table edge and the gap from the base to it (0 when touching). */
    edge: { side: 'top' | 'bottom' | 'left' | 'right'; gap: number };
    /** The nearest table corner and the gap from the base to it. */
    corner: { name: string; gap: number };
    /** With a deployment chosen: whose half of the table the middle of the base is in, or 'line' when the base is on the centerline. */
    half?: 'a' | 'b' | 'line';
    /** With a deployment chosen: whose deployment zone the middle of the base is in. */
    zone?: 'a' | 'b' | null;
}

/** Where a base sits: quarters, nearest edge and corner, and (given the deployment) half and zone. */
export function whereabouts(token: Pick<Token, 'x' | 'y' | 'baseMm'>, zones?: DeploymentZones | null): Whereabouts {
    const r = baseRadius(token.baseMm);
    const gaps = {
        left: Math.max(0, token.x - r),
        right: Math.max(0, TABLE_SIZE - token.x - r),
        top: Math.max(0, token.y - r),
        bottom: Math.max(0, TABLE_SIZE - token.y - r),
    };
    const side = (Object.keys(gaps) as (keyof typeof gaps)[]).reduce((a, b) => (gaps[b] < gaps[a] ? b : a));
    const corners = [
        { name: 'top-left', x: 0, y: 0 },
        { name: 'top-right', x: TABLE_SIZE, y: 0 },
        { name: 'bottom-left', x: 0, y: TABLE_SIZE },
        { name: 'bottom-right', x: TABLE_SIZE, y: TABLE_SIZE },
    ].map((c) => ({ name: c.name, gap: Math.max(0, Math.hypot(token.x - c.x, token.y - c.y) - r) }));
    const corner = corners.reduce((a, b) => (b.gap < a.gap ? b : a));
    const mid = TABLE_SIZE / 2;
    const rows = [token.y - r < mid ? 'top' : null, token.y + r > mid ? 'bottom' : null].filter(Boolean);
    const cols = [token.x - r < mid ? 'left' : null, token.x + r > mid ? 'right' : null].filter(Boolean);
    const quarters = rows.flatMap((row) => cols.map((col) => `${row}-${col}`));
    const out: Whereabouts = { quarters, edge: { side, gap: gaps[side] }, corner: { name: corner.name, gap: corner.gap } };
    if (zones) {
        const [[x1, y1], [x2, y2]] = zones.centerline;
        const len = Math.hypot(x2 - x1, y2 - y1) || 1;
        const signed = (px: number, py: number) => ((x2 - x1) * (py - y1) - (y2 - y1) * (px - x1)) / len;
        const centre = shapeCentre(zones.a);
        const aSign = Math.sign(signed(centre[0], centre[1])) || 1;
        const d = signed(token.x, token.y) * aSign;
        out.half = Math.abs(d) < r ? 'line' : d > 0 ? 'a' : 'b';
        out.zone = pointInShape(zones.a, token.x, token.y) ? 'a' : pointInShape(zones.b, token.x, token.y) ? 'b' : null;
    }
    return out;
}

// ---- jumping (p.22) -----------------------------------------------------------------------------------------------------------------

export interface JumpCheck {
    /** Horizontal distance, centre to centre: a jump is movement, so it counts against the Walk. */
    distance: number;
    /** Why it is not a legal jump; empty when it is. */
    reasons: string[];
    endElevation: number;
}

/**
 * Whether a model could have jumped from one spot to another: it may not end higher than it began, may land at most 1" below, and cannot
 * go over a terrain piece taller than the elevation it jumped from (an intervening piece whose height is greater than the jumper's elevation
 * stops the jump). The landing is held to the usual rule that the base is at least half supported, which is how its elevation is found.
 */
export function jumpCheck(start: Pt & { baseMm: number; under?: boolean }, startElevation: number, end: Token, pieces: TerrainPiece[]): JumpCheck {
    const endSupport = restingSupport(end, pieces);
    const startSupport = restingSupport(start, pieces);
    const reasons: string[] = [];
    if (endSupport.elevation > startElevation + 1e-9) reasons.push('a jump cannot end higher than it began');
    else if (startElevation - endSupport.elevation > 1 + 1e-9)
        reasons.push(`a jump lands at most 1" below where it began, and this is a ${fmtNum(startElevation - endSupport.elevation)}" drop`);
    const r = baseRadius(end.baseMm);
    const steps = Math.max(1, Math.ceil(Math.hypot(end.x - start.x, end.y - start.y) / LEG_STEP));
    const stoppers = new Map<string, number>();
    for (const piece of pieces) {
        for (const s of piece.surfaces) {
            if (s.top <= startElevation + 1e-9) continue;
            if (startSupport.surface?.id === s.id || endSupport.surface?.id === s.id) continue;
            for (let i = 1; i < steps; i++) {
                const x = start.x + ((end.x - start.x) * i) / steps;
                const y = start.y + ((end.y - start.y) * i) / steps;
                if (distanceToShape(s.shape, x, y) < r) {
                    stoppers.set(piece.name, Math.max(stoppers.get(piece.name) ?? 0, s.top));
                    break;
                }
            }
        }
    }
    for (const [name, top] of stoppers)
        reasons.push(`${name} (Ht ${fmtNum(top)}) is in the way: nothing taller than where it jumped from may be jumped over`);
    return { distance: Math.hypot(end.x - start.x, end.y - start.y), reasons, endElevation: endSupport.elevation };
}

// ---- the table check (Gaining Grounds One, Table Setup, and Adjusted Rules) ----------------------------------------------------------

/** The area of a shape in square inches. */
export function shapeArea(shape: Shape): number {
    if (shape.kind === 'rect') return shape.w * shape.h;
    if (shape.kind === 'circle') return Math.PI * shape.r ** 2;
    if (shape.kind === 'ellipse') return Math.PI * shape.rx * shape.ry;
    const p = shape.points;
    return Math.abs(p.reduce((sum, a, i) => sum + (a[0] * p[(i + 1) % p.length][1] - p[(i + 1) % p.length][0] * a[1]), 0)) / 2;
}

export interface TableCheckItem {
    id: string;
    /** 'ok' is within what the guide suggests, 'note' is worth a look, 'info' is a count with no target. */
    status: 'ok' | 'note' | 'info';
    label: string;
    text: string;
}

/** A 50 mm lane (Gaining Grounds: paths and chokepoints should be at least this wide), in inches. */
export const LANE_MIN = 50 / MM_PER_INCH;

const disrupts = (p: TerrainPiece) => p.traits.some((t) => t === 'blocking' || t === 'dense' || t === 'concealing');

/** Points along a shape's outline, no further apart than `spacing`. */
function outlinePoints(shape: Shape, spacing = 0.5): Pt[] {
    const out: Pt[] = [];
    for (const [a, b] of outlineEdges(shape)) {
        const n = Math.max(1, Math.ceil(Math.hypot(b.x - a.x, b.y - a.y) / spacing));
        for (let i = 0; i < n; i++) out.push({ x: a.x + ((b.x - a.x) * i) / n, y: a.y + ((b.y - a.y) * i) / n });
    }
    return out;
}

/** The smallest gap between two pieces (0 if they touch or overlap), from the outlines of every level. */
function pieceGap(a: TerrainPiece, b: TerrainPiece): number {
    let best = Infinity;
    for (const sa of a.surfaces)
        for (const sb of b.surfaces) {
            for (const p of outlinePoints(sa.shape)) best = Math.min(best, distanceToShape(sb.shape, p.x, p.y));
            for (const p of outlinePoints(sb.shape)) best = Math.min(best, distanceToShape(sa.shape, p.x, p.y));
        }
    return best;
}

/** The fraction of sight lines from one deployment zone to the other that no sight-disrupting terrain interrupts (an estimate: a grid of points in each). */
function openLanes(pieces: TerrainPiece[], zones: DeploymentZones): number {
    const grid = (shape: Shape) => {
        const b = shapeBounds(shape);
        const pts: Pt[] = [];
        for (let x = Math.max(0.5, b.minX); x <= Math.min(TABLE_SIZE - 0.5, b.maxX); x += 4.5)
            for (let y = Math.max(0.5, b.minY); y <= Math.min(TABLE_SIZE - 0.5, b.maxY); y += 4.5) if (pointInShape(shape, x, y)) pts.push({ x, y });
        return pts;
    };
    const a = grid(zones.a);
    const b = grid(zones.b);
    // Terrain that gets in the way of a model of Sz 2: anything that blocks at that height, anything dense, anything concealing.
    const stops = pieces.filter((p) =>
        p.traits.some((t) => t === 'dense' || t === 'concealing' || (t === 'blocking' && p.surfaces.some((s) => s.top >= 2))),
    );
    if (!a.length || !b.length) return 0;
    // every level of every stopping piece with its bounds, so a line that passes nowhere near one never walks across it
    const levels = stops.flatMap((piece) => piece.surfaces.map((s) => ({ shape: s.shape, bounds: cachedBounds(s.shape) })));
    const misses = (p: Pt, q: Pt, l: (typeof levels)[number]) =>
        Math.max(p.x, q.x) < l.bounds.minX ||
        Math.min(p.x, q.x) > l.bounds.maxX ||
        Math.max(p.y, q.y) < l.bounds.minY ||
        Math.min(p.y, q.y) > l.bounds.maxY;
    let open = 0;
    for (const p of a) for (const q of b) if (!levels.some((l) => !misses(p, q, l) && crossesShape(p, q, l.shape))) open++;
    return open / (a.length * b.length);
}

/**
 * The table-building guidelines from Gaining Grounds One, measured on the pieces: how much of the terrain disrupts sight and how it is split,
 * lanes between the deployment zones, gaps narrower than 50mm, and areas nobody can walk to (which should not take up more than a fifth of a
 * deployment zone). Estimates to help a table's maker; the TO has the last word.
 */
export function tableChecks(pieces: TerrainPiece[], zones?: DeploymentZones | null): TableCheckItem[] {
    const out: TableCheckItem[] = [];
    if (!pieces.length) return out;
    const total = pieces.length;
    const sight = pieces.filter(disrupts);
    const share = sight.length / total;
    out.push({
        id: 'disrupt',
        status: total < 3 || (share >= 0.65 && share <= 0.95) ? 'ok' : 'note',
        label: 'Sight-blocking terrain',
        text: `${sight.length} of ${total} pieces block, thicken or hide sight (${Math.round(share * 100)}%). The guide asks for roughly 80%.`,
    });
    const concealing = pieces.filter((p) => p.traits.includes('concealing')).length;
    const blocking = pieces.filter((p) => p.traits.includes('blocking')).length;
    const dense = pieces.filter((p) => p.traits.includes('dense'));
    const denseAlone = dense.filter((p) => !p.traits.includes('concealing') && !p.traits.includes('blocking')).length;
    const split = concealing + blocking ? concealing / (concealing + blocking) : 0.5;
    const notes: string[] = [];
    if (concealing + blocking >= 4 && (split < 0.3 || split > 0.7))
        notes.push(`${concealing} concealing and ${blocking} blocking is not an even split`);
    if (dense.length >= 3 && denseAlone / dense.length > 0.5) notes.push('dense terrain is best paired with another trait');
    const huge = pieces.filter(
        (p) => p.traits.includes('concealing') && Math.max(...p.surfaces.map((s) => shapeArea(s.shape))) > 0.12 * TABLE_SIZE ** 2,
    );
    if (huge.length) notes.push(`${huge.map((p) => p.name).join(', ')} covers a large swath of the table with concealing terrain`);
    out.push({
        id: 'mix',
        status: notes.length ? 'note' : 'ok',
        label: 'Mix of sight terrain',
        text: notes.length ? `${notes.join('; ')}.` : `${concealing} concealing and ${blocking} blocking, split reasonably evenly.`,
    });
    if (zones) {
        const open = openLanes(pieces, zones);
        out.push({
            id: 'lanes',
            status: open <= 0.2 ? 'ok' : 'note',
            label: 'Open sight lines between the zones',
            text: `About ${Math.round(open * 100)}% of lines from one deployment zone to the other are unhindered. The guide asks for very few.`,
        });
    }
    // gaps narrower than 50mm between solid pieces, or between a piece and the table edge
    const solid = pieces.filter((p) => p.traits.includes('impassable') || (p.traits.includes('blocking') && p.surfaces.some((s) => s.top >= 1)));
    const narrow: string[] = [];
    for (let i = 0; i < solid.length; i++) {
        for (let j = i + 1; j < solid.length; j++) {
            const gap = pieceGap(solid[i], solid[j]);
            if (gap > 0.05 && gap < LANE_MIN) narrow.push(`${solid[i].name} and ${solid[j].name}`);
        }
        const b = pieceBounds(solid[i]);
        const edge = Math.min(b.minX, b.minY, TABLE_SIZE - b.maxX, TABLE_SIZE - b.maxY);
        if (edge > 0.05 && edge < LANE_MIN) narrow.push(`${solid[i].name} and the table edge`);
    }
    out.push({
        id: 'lanes-width',
        status: narrow.length ? 'note' : 'ok',
        label: 'Lanes and chokepoints',
        text: narrow.length
            ? `Gaps under 50mm (about ${Math.round(LANE_MIN * 10) / 10}") between ${[...new Set(narrow)].slice(0, 4).join('; ')}. Models must be able to pass.`
            : 'No gap between solid pieces is narrower than 50mm.',
    });
    // areas no one can walk to: a 0.5" grid, blocked within a 30mm base's reach of impassable terrain, flood-filled from the largest open area
    const impassable = pieces.filter((p) => p.traits.includes('impassable'));
    const N = Math.round(TABLE_SIZE / 0.5);
    const r = baseRadius(30);
    const blockedCell = (i: number, j: number) => {
        const x = (i + 0.5) * 0.5;
        const y = (j + 0.5) * 0.5;
        return impassable.some((p) =>
            p.surfaces.some((s) => {
                const b = cachedBounds(s.shape);
                return x >= b.minX - r && x <= b.maxX + r && y >= b.minY - r && y <= b.maxY + r && distanceToShape(s.shape, x, y) < r;
            }),
        );
    };
    const blocked = Array.from({ length: N }, (_, i) => Array.from({ length: N }, (_, j) => blockedCell(i, j)));
    const label = Array.from({ length: N }, () => new Array<number>(N).fill(0));
    const sizes: number[] = [0];
    for (let i = 0; i < N; i++)
        for (let j = 0; j < N; j++) {
            if (blocked[i][j] || label[i][j]) continue;
            const id = sizes.length;
            sizes.push(0);
            const stack: [number, number][] = [[i, j]];
            label[i][j] = id;
            while (stack.length) {
                const [ci, cj] = stack.pop()!;
                sizes[id]++;
                for (const [di, dj] of [
                    [1, 0],
                    [-1, 0],
                    [0, 1],
                    [0, -1],
                ]) {
                    const ni = ci + di;
                    const nj = cj + dj;
                    if (ni < 0 || nj < 0 || ni >= N || nj >= N || blocked[ni][nj] || label[ni][nj]) continue;
                    label[ni][nj] = id;
                    stack.push([ni, nj]);
                }
            }
        }
    const main = sizes.indexOf(Math.max(...sizes.slice(1)));
    const unreachableAt = (x: number, y: number) => {
        const i = Math.min(N - 1, Math.floor(x / 0.5));
        const j = Math.min(N - 1, Math.floor(y / 0.5));
        return label[i][j] !== main;
    };
    let lost = 0;
    for (let i = 0; i < N; i++) for (let j = 0; j < N; j++) if (label[i][j] !== main) lost++;
    const lostShare = lost / (N * N);
    const parts: string[] = [`${Math.round(lostShare * 100)}% of the table cannot be walked to by a 30mm model`];
    let zoneBad = false;
    if (zones) {
        for (const [name, zone] of [
            ['A', zones.a],
            ['B', zones.b],
        ] as const) {
            const b = shapeBounds(zone);
            let cells = 0;
            let bad = 0;
            for (let x = Math.max(0.25, b.minX); x < Math.min(TABLE_SIZE, b.maxX); x += 0.5)
                for (let y = Math.max(0.25, b.minY); y < Math.min(TABLE_SIZE, b.maxY); y += 0.5)
                    if (pointInShape(zone, x, y)) {
                        cells++;
                        if (unreachableAt(x, y)) bad++;
                    }
            const frac = cells ? bad / cells : 0;
            parts.push(`${Math.round(frac * 100)}% of zone ${name}`);
            if (frac > 0.2) zoneBad = true;
        }
    }
    out.push({
        id: 'unreachable',
        status: zoneBad ? 'note' : 'ok',
        label: 'Unreachable areas',
        text: `${parts.join('; ')}. No more than 20% of a deployment zone should be unreachable, and nothing can be placed there.`,
    });
    // special terrain: more of one than its rules card allows
    const special = pieces.filter((p) => p.rules);
    if (special.length) {
        const byName = new Map<string, TerrainPiece[]>();
        for (const p of special) byName.set(p.name, [...(byName.get(p.name) ?? []), p]);
        const over = [...byName.entries()].filter(([, list]) => list[0].limit !== undefined && list.length > list[0].limit);
        out.push({
            id: 'special',
            status: over.length ? 'note' : 'ok',
            label: 'Special terrain',
            text: over.length
                ? `${over.map(([n, l]) => `${l.length} ${n} (the card allows ${l[0].limit})`).join('; ')}.`
                : `${special.length} piece${special.length === 1 ? '' : 's'} with a rules card, within their limits.`,
        });
    }
    // movement terrain: counts only
    const count = (trait: string) => pieces.filter((p) => p.traits.includes(trait)).length;
    const scatter = pieces.filter((p) => p.traits.includes('destructible') && Math.max(...p.surfaces.map((s) => shapeArea(s.shape))) < 12).length;
    out.push({
        id: 'movement',
        status: 'info',
        label: 'Movement terrain',
        text: `${count('impassable')} impassable, ${count('hazardous')} hazardous, ${count('severe')} severe, and ${scatter} small destructible piece${scatter === 1 ? '' : 's'} (scatter). The guide wants mostly impassable with hazardous and severe terrain sparingly.`,
    });
    return out;
}

/**
 * Auras that count as terrain (aura token terrain, p.57) as flat circular pieces of terrain, so sight lines, walks and placement treat them
 * like any other: a concealing aura hides what is in it, a hazardous one is triggered, a severe one costs double. The circle runs from the edge
 * of the owner's base out to the aura's range, and follows the owner unless the aura is locked.
 */
export function auraTerrain(auras: { aura: Aura; owner: Token }[]): TerrainPiece[] {
    return auras
        .filter(({ aura }) => aura.traits?.length)
        .map(({ aura, owner }) => {
            const origin = auraOrigin(aura, owner);
            return {
                id: `aura-${aura.id}`,
                name: `${owner.name}'s aura`,
                traits: [...(aura.traits ?? [])],
                surfaces: [
                    {
                        id: `aura-${aura.id}-1`,
                        top: 0.5,
                        shape: { kind: 'circle' as const, cx: origin.x, cy: origin.y, r: auraRadius(owner, aura.range) },
                    },
                ],
            };
        });
}
