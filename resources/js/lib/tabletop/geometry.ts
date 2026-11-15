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

export interface Token {
    id: string;
    name: string;
    /** 'a' and 'b' are the two players; 'n' is neutral, for markers. */
    side: 'a' | 'b' | 'n';
    /** A marker from the markers table rather than a model in a crew. */
    marker?: boolean;
    markerId?: number;
    baseMm: number;
    x: number;
    y: number;
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
