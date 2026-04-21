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
    player_one_strategy_vp: number | null;
    player_one_scheme_vp: number | null;
    player_two_faction: string | null;
    player_two_master: string | null;
    player_two_title: string | null;
    player_two_vp: number | null;
    player_two_strategy_vp: number | null;
    player_two_scheme_vp: number | null;
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
}

export interface MasterOption {
    name: string;
    faction: string;
    second_faction: string | null;
    titles: { id: number; display_name: string; title: string | null }[];
}

export type TiebreakerMode = 'diff_vp' | 'sos';
