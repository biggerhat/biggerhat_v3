import { TABLE_SIZE, baseRadius, type Token } from './geometry.ts';

export type Point = { x: number; y: number };

/**
 * The pure parts of moving a model and aiming its sight guidelines, kept out of the board component so they can be tested on their own:
 * what is left of a Walk, where a leg stops, the ring, the leg labels, taking a leg back, and where the guidelines first point.
 */

export type MoveKind = 'walk' | 'charge' | 'jump' | 'fly' | 'place';

/** A point a move has passed through: the start, then where each earlier leg ended. `spent` is what the move had cost on reaching it. */
export interface TrailPoint {
    x: number;
    y: number;
    elev: number;
    spent?: number;
}

/** Where the model being moved started, and where it is now. */
export interface Trail {
    id: string;
    from: Point;
    to: Point;
    baseMm: number;
    startElevation: number;
    sp?: number;
    /** The Sp was halved because the model started engaged (CRG, Engagement). */
    halved?: boolean;
    /** A Charge cannot be declared while engaged. */
    chargeBlocked?: boolean;
    kind: MoveKind;
    /** The building it started inside, if any (a place cannot cross a wall). */
    inside: string | null;
    range: number;
    whole: boolean;
    path: TrailPoint[];
    /** Whether the model began the move beneath a bridge (so Cancel can put it back as it was). */
    startUnder?: boolean;
}

/** Where the current leg began: the last point passed, or the start. */
export const legStart = (trail: Pick<Trail, 'from' | 'path'>): TrailPoint => trail.path.at(-1) ?? { ...trail.from, elev: 0, spent: 0 };

/**
 * In a step move a Walk (or Flight) cannot go past what is left of it: the leg stops at the edge of the ring. Anything else, or a move that is
 * not a step move, is left alone.
 */
export function capLeg(trail: Trail | null, id: string, step: boolean, to: Point): Point {
    if (!step || !trail || trail.id !== id || trail.sp === undefined || (trail.kind !== 'walk' && trail.kind !== 'fly')) return to;
    const anchor = legStart(trail);
    const left = Math.max(0, trail.sp - (anchor.spent ?? 0));
    const d = Math.hypot(to.x - anchor.x, to.y - anchor.y);
    if (d > left && d > 0) return { x: anchor.x + ((to.x - anchor.x) * left) / d, y: anchor.y + ((to.y - anchor.y) * left) / d };
    return to;
}

/**
 * The ring of what a Walk can still reach: out from the edge of the base, around where the model stands (or, while a leg is being dragged,
 * where the leg began), and in a step move smaller by what is spent.
 */
export function walkRing(trail: Trail | null, step: boolean, legActive: boolean, spentNow: number): { x: number; y: number; r: number } | null {
    if (!trail || trail.sp === undefined) return null;
    const rim = baseRadius(trail.baseMm);
    if (!step) return { x: trail.from.x, y: trail.from.y, r: trail.sp + rim };
    const at = legActive ? legStart(trail) : { x: trail.to.x, y: trail.to.y, spent: spentNow };
    return { x: at.x, y: at.y, r: Math.max(0, trail.sp - (at.spent ?? 0)) + rim };
}

/** Every point of the move, in order, ending where the model is now. */
export const trailPoints = (trail: Trail | null): Point[] => (trail ? [...trail.path.map((q) => ({ x: q.x, y: q.y })), { ...trail.to }] : []);

/** The straight-line length of the points, leg by leg. */
export const pathLength = (pts: Point[]): number =>
    pts.reduce((sum, q, i, all) => (i ? sum + Math.hypot(q.x - all[i - 1].x, q.y - all[i - 1].y) : 0), 0);

/**
 * A small length on each leg once there is more than one. A leg under an inch is too short to label without the text piling up on the
 * model; the total still counts it.
 */
export function legLabels(pts: Point[]): { x: number; y: number; length: number }[] {
    if (pts.length < 3) return [];
    return pts
        .slice(1)
        .map((q, i) => ({
            x: (q.x + pts[i].x) / 2,
            y: (q.y + pts[i].y) / 2 - 0.35,
            length: Math.hypot(q.x - pts[i].x, q.y - pts[i].y),
        }))
        .filter((l) => l.length >= 1);
}

/** Taking back the last leg: the trail without it, and the point the model goes back to. A move of one leg has nothing to take back (null). */
export function withoutLastLeg(trail: Trail): { trail: Trail; back: TrailPoint } | null {
    if (trail.path.length < 2) return null;
    const back = trail.path[trail.path.length - 1];
    return { trail: { ...trail, path: trail.path.slice(0, -1), to: { x: back.x, y: back.y } }, back };
}

/** Where the sight guidelines first point: the nearest model on the other side, or else across the table. */
export function defaultAim(t: Token, tokens: Token[]): Point {
    const foes = tokens.filter((o) => !o.marker && !o.killed && o.id !== t.id && o.side !== t.side && o.side !== 'n');
    const near = [...foes].sort((a, b) => Math.hypot(a.x - t.x, a.y - t.y) - Math.hypot(b.x - t.x, b.y - t.y))[0];
    if (near) return { x: near.x, y: near.y };
    return { x: TABLE_SIZE / 2, y: t.y < TABLE_SIZE / 2 ? t.y + 8 : t.y - 8 };
}
