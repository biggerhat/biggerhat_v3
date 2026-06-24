/**
 * Shared types for the live game tracker. Mirrors the server-side
 * App\Enums\GameStatusEnum / GameFormatEnum string values so the (large)
 * Games/Show page and its child components compare against named constants
 * instead of free-floating magic strings — a typo now fails the type-check.
 */

export const GameStatus = {
    Setup: 'setup',
    FactionSelect: 'faction_select',
    MasterSelect: 'master_select',
    CrewSelect: 'crew_select',
    SchemeSelect: 'scheme_select',
    InProgress: 'in_progress',
    Completed: 'completed',
    Abandoned: 'abandoned',
} as const;

export type GameStatus = (typeof GameStatus)[keyof typeof GameStatus];

export const GameFormat = {
    Standard: 'standard',
    BonanzaBrawl: 'bonanza_brawl',
    Campaign: 'campaign',
} as const;

export type GameFormat = (typeof GameFormat)[keyof typeof GameFormat];

/** Pre-gameplay statuses where the creator may still edit the scenario. */
export const GAME_SETUP_STATUSES: readonly GameStatus[] = [
    GameStatus.Setup,
    GameStatus.FactionSelect,
    GameStatus.MasterSelect,
    GameStatus.CrewSelect,
    GameStatus.SchemeSelect,
];

/** Terminal statuses for a finished game (completed outright or abandoned). */
export const GAME_FINISHED_STATUSES: readonly GameStatus[] = [GameStatus.Completed, GameStatus.Abandoned];
