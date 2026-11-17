import type { TerrainPiece, TerrainTrait } from './geometry';

export interface TerrainPreset {
    key: string;
    label: string;
    /** The texture kind when it is not the same as the key (an L-shaped building is drawn like any building). */
    kind?: string;
    make: (id: string, x: number, y: number) => TerrainPiece;
}

const piece = (id: string, name: string, traits: TerrainTrait[], surfaces: TerrainPiece['surfaces']): TerrainPiece => ({
    id,
    name,
    traits,
    surfaces,
});
const rect = (x: number, y: number, w: number, h: number) => ({ kind: 'rect' as const, x, y, w, h });
const circle = (cx: number, cy: number, r: number) => ({ kind: 'circle' as const, cx, cy, r });
const ellipse = (cx: number, cy: number, rx: number, ry: number, angle?: number) => ({
    kind: 'ellipse' as const,
    cx,
    cy,
    rx,
    ry,
    ...(angle ? { angle } : {}),
});
const poly = (points: [number, number][]) => ({ kind: 'poly' as const, points });

/** A river that wanders: a ribbon of the given width along an S-shaped line, as a polygon (14 corners). */
const windingRibbon = (x: number, y: number, length = 18, width = 3, sway = 2): [number, number][] => {
    const steps = 6;
    const centre = Array.from({ length: steps + 1 }, (_, i) => {
        const t = i / steps;
        return [x - length / 2 + t * length, y + sway * Math.sin(t * Math.PI * 2)] as [number, number];
    });
    const r = (n: number) => Math.round(n * 10) / 10;
    const top = centre.map(([px, py]) => [r(px), r(py - width / 2)] as [number, number]);
    const bottom = centre.map(([px, py]) => [r(px), r(py + width / 2)] as [number, number]).reverse();
    return [...top, ...bottom];
};

/** Standard pieces from the guide (p.57-58), centred near (x, y). Heights are the elevation a model stands at. */
const RAW_PRESETS: TerrainPreset[] = [
    {
        key: 'building',
        label: 'Building (flat roof, Ht 3)',
        make: (id, x, y) => piece(id, 'Building', ['blocking', 'climbable'], [{ id: `${id}-1`, shape: rect(x - 3, y - 2.5, 6, 5), top: 3 }]),
    },
    {
        key: 'tower',
        label: 'Tower (Ht 3, upper level Ht 5)',
        make: (id, x, y) =>
            piece(
                id,
                'Tower',
                ['blocking', 'climbable'],
                [
                    { id: `${id}-1`, shape: rect(x - 3, y - 3, 6, 6), top: 3 },
                    { id: `${id}-2`, shape: rect(x - 3, y - 3, 3, 3), top: 5 },
                ],
            ),
    },
    {
        key: 'steep',
        label: 'Steep building (Ht 4, cannot stand on)',
        make: (id, x, y) => piece(id, 'Steep building', ['blocking', 'impassable'], [{ id: `${id}-1`, shape: rect(x - 2.5, y - 2.5, 5, 5), top: 4 }]),
    },
    {
        key: 'wall',
        label: 'Wall (Ht 2)',
        make: (id, x, y) => piece(id, 'Wall', ['blocking', 'climbable'], [{ id: `${id}-1`, shape: rect(x - 4, y - 0.75, 8, 1.5), top: 2 }]),
    },
    {
        key: 'fence',
        label: 'Fence (Ht 1, destructible)',
        make: (id, x, y) => piece(id, 'Fence', ['climbable', 'destructible'], [{ id: `${id}-1`, shape: rect(x - 3, y - 0.5, 6, 1), top: 1 }]),
    },
    {
        key: 'crates',
        label: 'Crates (Ht 1, stacked Ht 2)',
        make: (id, x, y) =>
            piece(
                id,
                'Crates',
                ['blocking', 'climbable', 'destructible'],
                [
                    { id: `${id}-1`, shape: rect(x - 2, y - 1.5, 4, 3), top: 1 },
                    { id: `${id}-2`, shape: rect(x - 1, y - 1, 1.5, 1.5), top: 2 },
                ],
            ),
    },
    {
        key: 'hill',
        label: 'Hill (Ht 1, crest Ht 2)',
        make: (id, x, y) =>
            piece(
                id,
                'Hill',
                ['blocking', 'climbable'],
                [
                    { id: `${id}-1`, shape: circle(x, y, 4.5), top: 1 },
                    { id: `${id}-2`, shape: circle(x, y, 2.5), top: 2 },
                ],
            ),
    },
    {
        key: 'bridge',
        label: 'Bridge (walkway Ht 3, archway Ht 2 beneath)',
        make: (id, x, y) =>
            piece(id, 'Bridge', ['blocking', 'climbable'], [{ id: `${id}-1`, shape: rect(x - 6, y - 1.25, 12, 2.5), top: 3, under: 2 }]),
    },
    {
        key: 'fog',
        label: 'Fog bank (concealing, dense)',
        make: (id, x, y) => piece(id, 'Fog bank', ['concealing', 'dense'], [{ id: `${id}-1`, shape: circle(x, y, 3), top: 0 }]),
    },
    {
        key: 'lbuilding',
        kind: 'building',
        label: 'L-shaped building (Ht 3)',
        make: (id, x, y) =>
            piece(
                id,
                'L-shaped building',
                ['blocking', 'climbable'],
                [
                    {
                        id: `${id}-1`,
                        shape: poly([
                            [x - 3, y - 3],
                            [x + 1, y - 3],
                            [x + 1, y],
                            [x + 3, y],
                            [x + 3, y + 3],
                            [x - 3, y + 3],
                        ]),
                        top: 3,
                    },
                ],
            ),
    },
    {
        key: 'angledwall',
        kind: 'wall',
        label: 'Angled wall (Ht 2, turned 30°)',
        make: (id, x, y) =>
            piece(id, 'Angled wall', ['blocking', 'climbable'], [{ id: `${id}-1`, shape: { ...rect(x - 4, y - 0.75, 8, 1.5), angle: 30 }, top: 2 }]),
    },
    {
        key: 'ovalhill',
        kind: 'hill',
        label: 'Oval hill (Ht 1, crest Ht 2)',
        make: (id, x, y) =>
            piece(
                id,
                'Oval hill',
                ['blocking', 'climbable'],
                [
                    { id: `${id}-1`, shape: ellipse(x, y, 5.5, 3.5, -15), top: 1 },
                    { id: `${id}-2`, shape: ellipse(x, y, 3, 1.8, -15), top: 2 },
                ],
            ),
    },
    {
        key: 'pond',
        kind: 'river',
        label: 'Pond (oval, severe)',
        make: (id, x, y) => piece(id, 'Pond', ['severe'], [{ id: `${id}-1`, shape: ellipse(x, y, 4, 2.5, 20), top: 0 }]),
    },
    {
        key: 'windingriver',
        kind: 'river',
        label: 'Winding river (severe)',
        make: (id, x, y) => piece(id, 'Winding river', ['severe'], [{ id: `${id}-1`, shape: poly(windingRibbon(x, y)), top: 0 }]),
    },
    {
        key: 'woods',
        label: 'Woods (dense, concealing, severe)',
        make: (id, x, y) => piece(id, 'Woods', ['concealing', 'dense', 'severe'], [{ id: `${id}-1`, shape: circle(x, y, 4), top: 0 }]),
    },
    {
        key: 'tree',
        label: 'Tree (Ht 4, cannot stand on)',
        make: (id, x, y) => piece(id, 'Tree', ['blocking', 'impassable'], [{ id: `${id}-1`, shape: circle(x, y, 1), top: 4 }]),
    },
    {
        key: 'brambles',
        label: 'Brambles (hazardous, severe)',
        make: (id, x, y) => piece(id, 'Brambles', ['hazardous', 'severe'], [{ id: `${id}-1`, shape: circle(x, y, 2.5), top: 0 }]),
    },
    {
        key: 'river',
        label: 'River (ford, severe)',
        make: (id, x, y) => piece(id, 'River', ['severe'], [{ id: `${id}-1`, shape: rect(x - 8, y - 1.5, 16, 3), top: 0 }]),
    },
    {
        key: 'deepwater',
        label: 'Deep water (impassable)',
        make: (id, x, y) => piece(id, 'Deep water', ['impassable'], [{ id: `${id}-1`, shape: rect(x - 6, y - 2, 12, 4), top: 0 }]),
    },
    {
        key: 'marsh',
        label: 'Marsh (severe)',
        make: (id, x, y) => piece(id, 'Marsh', ['severe'], [{ id: `${id}-1`, shape: circle(x, y, 3.5), top: 0 }]),
    },
    {
        key: 'tallgrass',
        label: 'Tall grass (concealing)',
        make: (id, x, y) => piece(id, 'Tall grass', ['concealing'], [{ id: `${id}-1`, shape: circle(x, y, 3), top: 0 }]),
    },
    {
        key: 'rubble',
        label: 'Rubble (severe)',
        make: (id, x, y) => piece(id, 'Rubble', ['severe'], [{ id: `${id}-1`, shape: rect(x - 2.5, y - 2, 5, 4), top: 0 }]),
    },
    {
        key: 'acid',
        label: 'Acid pool (hazardous)',
        make: (id, x, y) => piece(id, 'Acid pool', ['hazardous'], [{ id: `${id}-1`, shape: circle(x, y, 2.5), top: 0 }]),
    },
    {
        key: 'fire',
        label: 'Burning ground (hazardous, concealing)',
        make: (id, x, y) => piece(id, 'Burning ground', ['hazardous', 'concealing'], [{ id: `${id}-1`, shape: circle(x, y, 2.5), top: 0 }]),
    },
    {
        key: 'block',
        label: 'Block (rectangle, Ht 2)',
        make: (id, x, y) => piece(id, 'Block', ['blocking', 'climbable'], [{ id: `${id}-1`, shape: rect(x - 2, y - 2, 4, 4), top: 2 }]),
    },
    {
        key: 'round',
        label: 'Round piece (Ht 2)',
        make: (id, x, y) => piece(id, 'Round piece', ['blocking', 'climbable'], [{ id: `${id}-1`, shape: circle(x, y, 2), top: 2 }]),
    },
];

/** The names of the textures a piece can be drawn with (the terrain kinds; the same keys as the server's list). */
export const TERRAIN_KIND_LABELS: Record<string, string> = {
    building: 'Roof tiles',
    tower: 'Stone tower',
    steep: 'Rock face',
    wall: 'Stone wall',
    fence: 'Wooden fence',
    crates: 'Crates',
    hill: 'Grassy hill',
    bridge: 'Planks',
    fog: 'Fog',
    block: 'Concrete',
    round: 'Boulders',
    woods: 'Woods',
    tree: 'Tree crowns',
    brambles: 'Brambles',
    river: 'Water',
    deepwater: 'Deep water',
    marsh: 'Marsh',
    tallgrass: 'Tall grass',
    rubble: 'Rubble',
    acid: 'Acid',
    fire: 'Fire',
};

/** An id that is unique enough for a table of up to 40 pieces, and safe for the server's id rule. */
export const newPieceId = (): string => `p${Date.now().toString(36)}${Math.floor(Math.random() * 1296).toString(36)}`;

/** Every piece a preset makes carries the preset's key, which picks its texture. */
export const TERRAIN_PRESETS: TerrainPreset[] = RAW_PRESETS.map((p) => ({
    ...p,
    make: (id, x, y) => ({ ...p.make(id, x, y), kind: p.kind ?? p.key }),
}));
