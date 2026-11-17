import type { TerrainPiece, TerrainTrait } from './geometry';

export interface TerrainPreset {
    key: string;
    label: string;
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
        label: 'Bridge walkway (Ht 3)',
        make: (id, x, y) => piece(id, 'Bridge walkway', ['climbable'], [{ id: `${id}-1`, shape: rect(x - 6, y - 1.25, 12, 2.5), top: 3 }]),
    },
    {
        key: 'fog',
        label: 'Fog bank (concealing, dense)',
        make: (id, x, y) => piece(id, 'Fog bank', ['concealing', 'dense'], [{ id: `${id}-1`, shape: circle(x, y, 3), top: 0.5 }]),
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

/** An id that is unique enough for a table of up to 40 pieces, and safe for the server's id rule. */
export const newPieceId = (): string => `p${Date.now().toString(36)}${Math.floor(Math.random() * 1296).toString(36)}`;

/** Every piece a preset makes carries the preset's key, which picks its texture. */
export const TERRAIN_PRESETS: TerrainPreset[] = RAW_PRESETS.map((p) => ({ ...p, make: (id, x, y) => ({ ...p.make(id, x, y), kind: p.key }) }));
