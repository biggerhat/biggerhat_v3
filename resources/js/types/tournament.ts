/**
 * Shared types for the tournament tracker. Keeping these in one place stops
 * the various Manage / View / per-tab components from drifting when the
 * server-side payload changes.
 */

export interface TournamentMeta {
    id: number;
    name: string;
    slug?: string;
}

export interface TournamentPlayer {
    id: number;
    display_name: string;
    faction: string | null;
    user: { id: number; name: string; meta?: TournamentMeta | null } | null;
    meta?: TournamentMeta | null;
    is_ringer: boolean;
    is_disqualified: boolean;
    dropped_after_round: number | null;
}

export interface TournamentGame {
    id: number;
    player_one_id: number;
    player_two_id: number | null;
    player_one?: TournamentPlayer;
    player_two?: TournamentPlayer | null;
    player_one_faction: string | null;
    player_one_master: string | null;
    player_one_title: string | null;
    player_one_vp: number | null;
    player_two_faction: string | null;
    player_two_master: string | null;
    player_two_title: string | null;
    player_two_vp: number | null;
    /** Per-side score components keyed by the ruleset's score-field keys. */
    score_breakdown: { player_one?: Record<string, number>; player_two?: Record<string, number> } | null;
    is_bye: boolean;
    is_forfeit: boolean;
    forfeit_player_id: number | null;
    result: 'pending' | 'completed' | 'agreed' | 'forfeited';
    table_number: number | null;
    is_manual?: boolean;
    tracker_game?: {
        id: number;
        uuid: string;
        status: 'setup' | 'faction_select' | 'master_select' | 'crew_select' | 'scheme_select' | 'in_progress' | 'completed' | 'abandoned';
        is_solo: boolean;
        current_turn: number | null;
        max_turns: number | null;
        winner_id: number | null;
        is_tie: boolean;
    } | null;
}

export interface Deployment {
    value: string;
    label: string;
    description: string;
    image_url: string | null;
}

export interface TournamentRound {
    id: number;
    round_number: number;
    /**
     * Raw enum value on Manage; hydrated object on the public View endpoint.
     * Consumers should narrow at point-of-use.
     */
    deployment: string | Deployment | null;
    strategy: { id: number; name: string } | null;
    scheme_pool: number[] | null;
    status: 'setup' | 'in_progress' | 'completed';
    games: TournamentGame[];
}

export interface StandingEntry {
    rank: number | null;
    player_id: number;
    display_name: string;
    faction: string | null;
    is_ringer: boolean;
    is_dropped: boolean;
    total_tp: number;
    total_diff: number;
    total_vp: number;
    total_sos: number;
    rounds_played: number;
    has_bye?: boolean;
    wins?: number;
    losses?: number;
    ties?: number;
}

export interface MasterOption {
    name: string;
    faction: string;
    second_faction: string | null;
    titles: { id: number; display_name: string; title: string | null }[];
}

export type TiebreakerMode = 'diff_vp' | 'sos';

/** Scoring parameters for a ruleset (mirrors App\Tournaments\ScoringConfig::toArray()). */
export interface RulesetScoring {
    base_tp: number;
    win_tp: number;
    tie_tp: number;
    loss_tp: number;
    diff_adds_to_tp: boolean;
    diff_cap: number | null;
    bye_tp: number;
    bye_diff: number;
    bye_vp: number;
    forfeit_vp: number;
    forfeit_loser_tp: number;
}

/** Ruleset descriptor (mirrors App\Tournaments\Rulesets\Ruleset::toArray()). */
export interface RulesetInfo {
    key: string;
    label: string;
    game_system: string;
    scoring: RulesetScoring;
    /** Ordered standings keys applied after TP. */
    tiebreakers: ('sos' | 'diff' | 'vp')[];
    tiebreaker_toggle: boolean;
    suggested_rounds: [number, number | null, number][];
    default_round_time_limit: number | null;
    game_size: { label: string; default: number; min: number; max: number; step: number };
    uses_season: boolean;
    uses_encounter_type: boolean;
    /** Components a player's score is entered as; their sum is the player's VP. */
    score_fields: { key: string; label: string; min: number; max: number }[];
    /** Scenario components required on a round before pairing ('strategy' | 'deployment' | 'scheme_pool'). */
    scenario_fields: string[];
    requires_faction: boolean;
}

/** A tournament's ruleset: `scoring`/`tiebreakers` are effective values, `default_scoring` the ruleset's own. */
export interface TournamentRuleset extends RulesetInfo {
    default_scoring: RulesetScoring;
}

export const TIEBREAKER_LABELS: Record<string, string> = { sos: 'Strength of Schedule', diff: 'Differential', vp: 'Victory Points' };
