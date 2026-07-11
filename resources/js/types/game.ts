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

// ─── Game tracker payload shapes ───
// These mirror the GameController Games/Show payload. Shared by Show.vue and its
// per-phase panels so the decomposition reads from one definition rather than
// re-declaring the shapes per component.

export interface CrewMember {
    id: number;
    character_id: number | null;
    display_name: string;
    faction: string | null;
    current_health: number;
    max_health: number;
    defense: number | null;
    willpower: number | null;
    speed: number | null;
    size: number | null;
    cost: number;
    station: string | null;
    hiring_category: string;
    front_image: string | null;
    back_image: string | null;
    is_killed: boolean;
    is_summoned: boolean;
    is_activated: boolean;
    is_custom: boolean;
    attached_tokens: { id: number; name: string }[];
    attached_upgrades: { id: number; name: string; current_power_bar?: number | null }[];
    attached_markers: { id: number; name: string }[];
    notes: string | null;
    sort_order: number;
    game_player_id: number;
}

export interface CrewReferences {
    version?: number;
    markers?: Array<{ id: number; name: string; slug?: string; description?: string | null; base?: string | null }>;
    tokens?: Array<{ id: number; name: string; slug?: string; description?: string | null }>;
    upgrades?: Array<{ id: number; name: string; slug?: string; front_image?: string | null; back_image?: string | null; type?: string | null }>;
    characters?: unknown[];
}

export interface GamePlayer {
    id: number;
    slot: number;
    faction: string | null;
    master_name: string | null;
    master_id: number | null;
    crew_build_id: number | null;
    crew_skipped: boolean;
    current_scheme_id: number | null;
    scheme_notes: { note?: string; selected_model?: string; selected_marker?: string; terrain_note?: string } | null;
    role: string | null;
    total_points: number;
    soulstone_pool: number;
    opponent_name: string | null;
    is_turn_complete: boolean;
    is_game_complete: boolean;
    /** Recorded scoring turns (loosely typed — turn rows carry varying snapshot shapes). */
    turns?: any[];
    crew_members: CrewMember[];
    master: { id: number; crew_upgrades: any[]; crew_upgrade_mode: string | null } | null;
    crew_build: { id: number; crew_upgrade_id: number | null; references?: CrewReferences } | null;
    // Bonanza/crew-skipped players carry server-derived references here instead.
    references?: CrewReferences;
    active_crew_upgrade_id: number | null;
    crew_upgrade_power_bars: Record<string, number> | null;
    user: { id: number; name: string } | null;
}

export interface SchemeData {
    id: number;
    name: string;
    slug: string;
    image_url: string | null;
    prerequisite: string | null;
    reveal: string | null;
    scoring: string | null;
    requirements: any[];
    next_scheme_one_id: number | null;
    next_scheme_two_id: number | null;
    next_scheme_three_id: number | null;
}

export interface DeploymentData {
    value: string;
    label: string;
    description: string;
    image_url: string | null;
}

export interface LootMarker {
    id: string;
    card_id: number;
    side: 'a' | 'b';
    dropped_by_player_id: number | null;
}

// Full detail (stats, ranges, damage, descriptions, nested triggers) — not
// name-only stubs — so the in-game side-picker can render the same
// ActionCard/AbilityCard/LootTriggerDisplay the public reference page does.
// Shape matches LootEffectText.vue's LootActionRef/LootAbilityRef/LootTriggerRef.
export interface LootCardActionSummary {
    id: number;
    name: string;
    type?: string;
    is_signature?: boolean;
    stone_cost?: number;
    range?: number | null;
    range_type?: string | null;
    stat?: number | null;
    stat_suits?: string | null;
    stat_modifier?: string | null;
    resisted_by?: string | null;
    target_number?: number | null;
    target_suits?: string | null;
    damage?: number | string | null;
    description?: string | null;
    triggers?: Array<{ id?: number; name: string; suits?: string | null; stone_cost?: number; description?: string | null }>;
    pivot?: { is_signature_action?: boolean | number };
}
export interface LootCardAbilitySummary {
    id: number;
    name: string;
    suits?: string | null;
    defensive_ability_type?: string | null;
    costs_stone?: boolean;
    description?: string | null;
}
export interface LootCardTriggerSummary {
    id: number;
    name: string;
    suits?: string | null;
    stone_cost?: number;
    description?: string | null;
}

export interface LootCardSummary {
    id: number;
    name: string;
    title_a: string | null;
    title_b: string | null;
    effect_a: string | null;
    effect_b: string | null;
    image: string | null;
    suit: string | null;
    value: number | null;
    value_label: string | null;
    side_a_actions: LootCardActionSummary[];
    side_b_actions: LootCardActionSummary[];
    side_a_abilities: LootCardAbilitySummary[];
    side_b_abilities: LootCardAbilitySummary[];
    side_a_triggers: LootCardTriggerSummary[];
    side_b_triggers: LootCardTriggerSummary[];
}

export interface GameData {
    id: number;
    uuid: string;
    name: string | null;
    status: GameStatus;
    creator_id: number;
    encounter_size: number;
    season: string;
    season_label?: string;
    /** Drives whether the scenario panel renders, whether SchemeSelect fires,
     *  and whether the manual-VP widget shows. See {@link GameFormat}. */
    format: GameFormat;
    /** Bonanza per-game loot deck state. Null on standard-format games. */
    loot_state: {
        deck: number[];
        discard: number[];
        dropped_markers: LootMarker[];
    } | null;
    current_turn: number;
    max_turns: number;
    is_tie: boolean;
    is_solo: boolean;
    is_observable: boolean;
    settings: { auto_soulstone_on_kill?: boolean } | null;
    winner_slot: number | null;
    strategy: { id: number; name: string; slug: string } | null;
    players: GamePlayer[];
    winner: { id: number; name: string } | null;
    created_at: string;
    started_at: string | null;
    completed_at: string | null;
}
