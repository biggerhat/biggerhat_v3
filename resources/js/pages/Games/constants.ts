/**
 * Shared tuning constants for the real-time Game Tracker.
 *
 * Anything that was previously a magic number scattered across Show.vue /
 * useGameChannel lives here so tuning the game feel doesn't require hunting
 * through a 5000-line file.
 */

/** How long the "Turn X" banner stays visible after a broadcast turn advance. */
export const TURN_BANNER_VISIBLE_MS = 4000;

/**
 * Window in which multiple rapid broadcasts (e.g., turn advance + crew
 * updates firing back-to-back) coalesce into a single `router.reload()`.
 * Lives on the client side inside useGameChannel.
 */
export const BROADCAST_RELOAD_DEBOUNCE_MS = 150;

/**
 * A player cannot score more than 2 scheme VP in a single turn per the
 * Malifaux 4e rules. Used to cap the turn scoring input.
 */
export const MAX_SCHEME_PER_TURN = 2;

/** Overall scheme VP cap across all turns for a player. */
export const MAX_SCHEME_POOL = 6;
