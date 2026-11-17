import type { TerrainPiece } from './geometry';

/**
 * A demo table for the spike. `top` on a surface is the standing elevation in inches (the summed height of what supports a model),
 * so a stack of terrain is authored as one surface per level, each with its own footprint.
 */
export const sampleTerrain: TerrainPiece[] = [
    {
        id: 'tower',
        kind: 'tower',
        name: 'Tower (flat roof, Ht 3, upper level Ht 5)',
        traits: ['blocking', 'climbable'],
        surfaces: [
            { id: 'tower-roof', shape: { kind: 'rect', x: 4, y: 4, w: 8, h: 6 }, top: 3 },
            { id: 'tower-upper', shape: { kind: 'rect', x: 4, y: 4, w: 3, h: 3 }, top: 5 },
        ],
    },
    {
        id: 'hill',
        kind: 'hill',
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
        kind: 'wall',
        name: 'Wall (Ht 2)',
        traits: ['blocking', 'climbable'],
        surfaces: [{ id: 'wall-top', shape: { kind: 'rect', x: 14, y: 17, w: 8, h: 1.5 }, top: 2 }],
    },
    {
        id: 'crates',
        kind: 'crates',
        name: 'Crates (Ht 1, stacked Ht 2)',
        traits: ['blocking', 'climbable', 'destructible'],
        surfaces: [
            { id: 'crates-low', shape: { kind: 'rect', x: 26, y: 24, w: 4, h: 3 }, top: 1 },
            { id: 'crates-high', shape: { kind: 'rect', x: 27, y: 24.5, w: 1.5, h: 1.5 }, top: 2 },
        ],
    },
    {
        id: 'bridge',
        kind: 'bridge',
        name: 'Bridge (walkway Ht 3, archway Ht 2 beneath)',
        traits: ['blocking', 'climbable'],
        surfaces: [{ id: 'bridge-walk', shape: { kind: 'rect', x: 4, y: 27, w: 12, h: 2.5 }, top: 3, under: 2 }],
    },
];
