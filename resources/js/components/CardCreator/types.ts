export interface EnumOption {
    name: string;
    value: string;
}

export interface TriggerData {
    name: string;
    suits: string | null;
    stone_cost: number;
    description: string | null;
    source_id: number | null;
}

export interface ActionData {
    name: string;
    type: string;
    is_signature: boolean;
    stone_cost: number | string;
    range: number | string | null;
    range_type: string | null;
    stat: number | string | null;
    stat_suits: string | null;
    stat_modifier: string | null;
    resisted_by: string | null;
    target_number: number | string | null;
    target_suits: string | null;
    damage: string | null;
    description: string | null;
    source_id: number | null;
    source_character_id?: number | null;
    triggers: TriggerData[];
}

export interface AbilityData {
    name: string;
    suits: string | null;
    defensive_ability_type: string | null;
    costs_stone: boolean;
    description: string | null;
    source_id: number | null;
    source_character_id?: number | null;
}

export interface ContentBlock {
    type: 'text' | 'ability' | 'action' | 'trigger';
    text?: string;
    data?: Record<string, any>;
}

export interface TokenData {
    name: string;
    description: string | null;
    source_id?: number | null;
}

export interface MarkerData {
    name: string;
    description: string | null;
    source_id?: number | null;
}
