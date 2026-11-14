import type { TerrainPiece, Token } from './geometry';

/**
 * A demo table for the spike. `top` on a surface is the standing elevation in inches (the summed height of what supports a model),
 * so a stack of terrain is authored as one surface per level, each with its own footprint.
 */
export const sampleTerrain: TerrainPiece[] = [
    {
        id: 'tower',
        name: 'Tower (flat roof, Ht 3, upper level Ht 5)',
        traits: ['blocking', 'climbable'],
        surfaces: [
            { id: 'tower-roof', shape: { kind: 'rect', x: 4, y: 4, w: 8, h: 6 }, top: 3 },
            { id: 'tower-upper', shape: { kind: 'rect', x: 4, y: 4, w: 3, h: 3 }, top: 5 },
        ],
    },
    {
        id: 'hill',
        name: 'Hill (Ht 1, crest Ht 2)',
        traits: ['blocking', 'climbable'],
        surfaces: [
            {
                id: 'hill-slope',
                shape: {
                    kind: 'poly',
                    points: [
                        [22, 5],
                        [32, 6],
                        [31, 12],
                        [24, 13],
                    ],
                },
                top: 1,
            },
            { id: 'hill-crest', shape: { kind: 'circle', cx: 27.5, cy: 9, r: 2.2 }, top: 2 },
        ],
    },
    {
        id: 'wall',
        name: 'Wall (Ht 2)',
        traits: ['blocking', 'climbable'],
        surfaces: [{ id: 'wall-top', shape: { kind: 'rect', x: 14, y: 17, w: 8, h: 1.5 }, top: 2 }],
    },
    {
        id: 'crates',
        name: 'Crates (Ht 1, stacked Ht 2)',
        traits: ['blocking', 'climbable', 'destructible'],
        surfaces: [
            { id: 'crates-low', shape: { kind: 'rect', x: 26, y: 24, w: 4, h: 3 }, top: 1 },
            { id: 'crates-high', shape: { kind: 'rect', x: 27, y: 24.5, w: 1.5, h: 1.5 }, top: 2 },
        ],
    },
    {
        id: 'bridge',
        name: 'Bridge walkway (Ht 3, clear archway beneath)',
        traits: ['climbable'],
        surfaces: [{ id: 'bridge-walk', shape: { kind: 'rect', x: 6, y: 27, w: 12, h: 2.5 }, top: 3 }],
    },
];

export const sampleTokens: Token[] = [
    { id: 'a1', name: 'Master', side: 'a', baseMm: 50, x: 18, y: 4 },
    { id: 'a2', name: 'Henchman', side: 'a', baseMm: 40, x: 12, y: 5 },
    { id: 'a3', name: 'Minion', side: 'a', baseMm: 30, x: 22, y: 4 },
    { id: 'b1', name: 'Master', side: 'b', baseMm: 50, x: 18, y: 32 },
    { id: 'b2', name: 'Henchman', side: 'b', baseMm: 40, x: 24, y: 31 },
    { id: 'b3', name: 'Minion', side: 'b', baseMm: 30, x: 12, y: 32 },
];
