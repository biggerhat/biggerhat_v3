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
    side: 'a' | 'b';
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

/** Standard Deployment (8" from the chosen edge) and Corner Deployment (12" from the chosen corner), p.61. Returned as shapes per side. */
export function deploymentZones(kind: 'standard' | 'corner'): { a: Shape; b: Shape } {
    if (kind === 'standard') {
        return { a: { kind: 'rect', x: 0, y: 0, w: TABLE_SIZE, h: 8 }, b: { kind: 'rect', x: 0, y: TABLE_SIZE - 8, w: TABLE_SIZE, h: 8 } };
    }
    return { a: { kind: 'rect', x: 0, y: 0, w: 12, h: 12 }, b: { kind: 'rect', x: TABLE_SIZE - 12, y: TABLE_SIZE - 12, w: 12, h: 12 } };
}
