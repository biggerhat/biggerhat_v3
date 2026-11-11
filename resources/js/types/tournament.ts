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
    /** TOS rulesets: the player's declared Allegiance. */
    allegiance?: { slug: string; name: string } | null;
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

/** An optional rule variant a TO can enable (mirrors Ruleset::$variants). */
export interface RulesetVariant {
    key: string;
    label: string;
    description: string;
    param: { label: string; min: number; max: number; default: number } | null;
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
    game_size: { label: string; unit: string; default: number; min: number; max: number; step: number };
    uses_season: boolean;
    uses_encounter_type: boolean;
    /** Components a player's score is entered as; their sum is the player's VP. */
    score_fields: { key: string; label: string; min: number; max: number }[];
    /** Scenario components required on a round before pairing ('strategy' | 'deployment' | 'scheme_pool'). */
    scenario_fields: string[];
    /** 'faction' (Malifaux), 'allegiance' (TOS, optional Garrison) or 'none'. */
    declaration: 'faction' | 'allegiance' | 'none';
    requires_declaration: boolean;
    /** Whether score entry records each side's leader (Malifaux master + title). */
    tracks_leaders: boolean;
    supports_tracker: boolean;
    variants: RulesetVariant[];
    /** Round-clock wording shown to players; null = the format has no such rule. */
    time_rules: { last_turn_minutes: number; last_turn: string | null; time_called: string | null };
}

/** A tournament's ruleset: `scoring`/`tiebreakers` are effective values, `default_scoring` the ruleset's own. */
export interface TournamentRuleset extends RulesetInfo {
    default_scoring: RulesetScoring;
    /** The ruleset's own tiebreaker order (`tiebreakers` is the event's effective order). */
    default_tiebreakers: ('sos' | 'diff' | 'vp')[];
    /** Enabled variants: `true`, or the number for parameterised ones. */
    active_variants: Record<string, boolean | number>;
    /** True when scoring or tiebreakers differ from the ruleset's own. */
    scoring_customized: boolean;
}

export const TIEBREAKER_LABELS: Record<string, string> = { sos: 'Strength of Schedule', diff: 'Differential', vp: 'Victory Points' };

/** TOS Operation / Deployment reference rows shown on a round (mirrors TournamentController payloads). */
export interface TosOperationInfo {
    id: number;
    name: string;
    source: string | null;
    flavor: string | null;
    additional_setup: string | null;
    special_rules: string | null;
    victory_conditions: string | null;
    image_url: string | null;
}

export interface TosDeploymentInfo {
    id: number;
    name: string;
    suit: string | null;
    description: string | null;
    image_url: string | null;
}

/** Plain-text body for the reference drawer: each non-empty section under a heading. */
export function tosOperationText(o: TosOperationInfo): string {
    return [
        o.flavor,
        o.additional_setup ? `Additional Setup\n${o.additional_setup}` : '',
        o.special_rules ? `Special Rules\n${o.special_rules}` : '',
        o.victory_conditions ? `Victory Conditions\n${o.victory_conditions}` : '',
    ]
        .filter(Boolean)
        .join('\n\n');
}

/** "50ss" for Malifaux, "40 Scrip" for TOS: short units attach, word units get a space. */
export function formatGameSize(ruleset: Pick<RulesetInfo, 'game_size'>, size: number): string {
    const unit = ruleset.game_size.unit;
    return unit.length <= 2 ? `${size}${unit}` : `${size} ${unit}`;
}

/** Tiebreaker orders a TO can choose (after TP). Values are comma-joined keys. */
export const TIEBREAKER_PRESETS: { value: string; label: string }[] = [
    { value: 'diff,vp', label: 'Differential → VP' },
    { value: 'vp,diff', label: 'VP → Differential' },
    { value: 'sos,diff,vp', label: 'Strength of Schedule → Differential → VP' },
    { value: 'sos,vp,diff', label: 'Strength of Schedule → VP → Differential' },
    { value: 'sos,diff', label: 'Strength of Schedule → Differential' },
    { value: 'sos', label: 'Strength of Schedule only' },
    { value: 'diff', label: 'Differential only' },
    { value: 'vp', label: 'VP only' },
];
