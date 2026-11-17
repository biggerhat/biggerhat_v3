import type { Shape, TerrainPiece } from './geometry';

/**
 * Ready-made tables. Each is laid out for half the table and turned 180 degrees about the centre for the other half, so neither
 * player gets the better ground. They aim for the guide's advice (p.60): roughly 45-65% covered with three to eight standable pieces
 * of Ht 2 or more. Pieces use the traits from p.56-58; only climbable pieces can be stood on.
 */
export interface TerrainLayout {
    key: string;
    label: string;
    description: string;
    pieces: () => TerrainPiece[];
}

const T = 36;
const turn = (s: Shape): Shape =>
    s.kind === 'rect'
        ? { kind: 'rect', x: T - s.x - s.w, y: T - s.y - s.h, w: s.w, h: s.h }
        : s.kind === 'circle'
          ? { kind: 'circle', cx: T - s.cx, cy: T - s.cy, r: s.r }
          : { kind: 'poly', points: s.points.map(([x, y]) => [T - x, T - y] as [number, number]) };

const rect = (x: number, y: number, w: number, h: number): Shape => ({ kind: 'rect', x, y, w, h });
const circle = (cx: number, cy: number, r: number): Shape => ({ kind: 'circle', cx, cy, r });

/** Which kind of terrain each piece is, so it is drawn with that kind's texture. */
const KIND_OF: Record<string, string> = {
    bldg: 'building',
    hall: 'building',
    house: 'building',
    shed: 'building',
    block: 'block',
    tower: 'tower',
    mill: 'tower',
    wall: 'wall',
    steep: 'steep',
    crates: 'crates',
    fog: 'fog',
    hill: 'hill',
    rocks: 'round',
    woods: 'woods',
    thicket: 'woods',
};

const piece = (id: string, name: string, traits: string[], levels: [Shape, number][]): TerrainPiece => ({
    id,
    name,
    kind: KIND_OF[id],
    traits,
    surfaces: levels.map(([shape, top], i) => ({ id: `${id}-${i + 1}`, shape, top })),
});

/** The piece, and its twin turned half a turn across the table. */
const pair = (p: TerrainPiece): TerrainPiece[] => [
    p,
    { ...p, id: `${p.id}t`, surfaces: p.surfaces.map((s) => ({ id: `${s.id}t`, top: s.top, shape: turn(s.shape) })) },
];

const BUILD = ['blocking', 'climbable'];

/** A piece that sits on the centre of the table and so is its own twin. */
const centre = (p: TerrainPiece): TerrainPiece[] => [p];

export const TERRAIN_LAYOUTS: TerrainLayout[] = [
    {
        key: 'city',
        label: 'City streets',
        description: 'Buildings and a tower with lanes between them, a wall, crates and a fog bank in the middle.',
        pieces: () => [
            ...pair(piece('bldg', 'Building', BUILD, [[rect(2, 2, 10, 8), 3]])),
            ...pair(piece('hall', 'Hall', BUILD, [[rect(14, 2, 8, 9), 3]])),
            ...pair(
                piece('tower', 'Tower', BUILD, [
                    [rect(25, 2, 8, 8), 3],
                    [rect(25, 2, 4, 4), 5],
                ]),
            ),
            ...pair(piece('wall', 'Wall', BUILD, [[rect(2, 13, 10, 1.5), 2]])),
            ...pair(piece('steep', 'Steep building', ['blocking', 'impassable'], [[rect(25, 11.5, 7, 5), 4]])),
            ...pair(piece('crates', 'Crates', ['blocking', 'climbable', 'destructible'], [[rect(6, 15, 4, 2.5), 1]])),
            ...centre(piece('fog', 'Fog bank', ['concealing', 'dense'], [[circle(18, 18, 3.5), 0.5]])),
        ],
    },
    {
        key: 'hills',
        label: 'Hills and ruins',
        description: 'Two hills and ruined houses, with rocks and woods in the middle.',
        pieces: () => [
            ...pair(
                piece('hill', 'Hill', BUILD, [
                    [circle(9, 9, 7), 1],
                    [circle(9, 9, 3.5), 2],
                ]),
            ),
            ...pair(piece('house', 'Ruined house', BUILD, [[rect(20, 2, 12, 9), 3]])),
            ...pair(piece('rocks', 'Rocks', ['blocking', 'climbable'], [[circle(26, 15, 2.5), 2]])),
            ...centre(piece('woods', 'Woods', ['concealing', 'dense'], [[circle(18, 18, 4.5), 0.5]])),
        ],
    },
    {
        key: 'crossroads',
        label: 'Crossroads',
        description: 'Large blocks and a mill, with a wall and sheds either side of the crossing and a thicket in the middle.',
        pieces: () => [
            ...pair(piece('block', 'Block', BUILD, [[rect(2, 2, 13, 12), 3]])),
            ...pair(
                piece('mill', 'Mill', BUILD, [
                    [rect(20, 2, 11, 10), 3],
                    [rect(20, 2, 5, 5), 5],
                ]),
            ),
            ...pair(piece('shed', 'Sheds', BUILD, [[rect(2, 15, 10, 2.5), 2]])),
            ...pair(piece('wall', 'Wall', BUILD, [[rect(16, 13.5, 7, 1.5), 2]])),
            ...centre(piece('thicket', 'Thicket', ['concealing', 'dense'], [[circle(18, 18, 2.5), 0.5]])),
        ],
    },
];
