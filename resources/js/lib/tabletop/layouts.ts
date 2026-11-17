import type { TerrainPiece } from './geometry';

/** A saved terrain setup, as the server describes it. */
export interface SavedLayout {
    uuid: string;
    name: string;
    description: string | null;
    terrain: TerrainPiece[];
    pieces: number;
    table_image: string | null;
    is_shared: boolean;
    is_public: boolean;
    /** Private, anyone with the link, or listed in the public gallery. */
    visibility: 'private' | 'link' | 'public';
    /** True when it belongs to the viewer. */
    mine: boolean;
    /** Who made it (given for a layout opened by link). */
    owner: string | null;
    /** The layout or game it was copied from. */
    copied_from: string | null;
    updated_at: string | null;
}

/** Where "save this table" sends the table: the sandbox keeps what is on screen, a game board saves the game's own table. */
export type LayoutSaveTarget = { kind: 'sandbox' } | { kind: 'game'; uuid: string };
