export type CoopRole = 'obstructor' | 'brawler' | 'firepower' | 'support' | 'boss';

export interface CoopAction {
    kind: 'attack' | 'tactical';
    name: string;
    rg: string | null;
    skl: string | null;
    rst: string | null;
    tn: string | null;
    dmg: string | null;
    text: string | null;
    triggers: { name: string; text: string }[] | null;
}

export interface CoopBehaviorRow {
    flip_min: number | null;
    flip_max: number | null;
    ideal_range: string | null;
    effects: string[];
}

export interface CoopClassData {
    id: number;
    name: string;
    slug: string;
    role: CoopRole;
    is_unique: boolean;
    resistance: number;
    speed: number | null;
    max_health: number;
    elite_max_health: number | null;
    abilities: { name: string; text: string }[] | null;
    actions: CoopAction[] | null;
    behavior: CoopBehaviorRow[] | null;
    target_preference: string[] | null;
    elite_modifications: string | null;
}

export interface CoopRoleOrderEntry {
    role: CoopRole;
    class_slug: string | null;
}

export interface CoopPhase {
    title: string;
    narrative: string | null;
    max_hand_size: number | null;
    role_order: CoopRoleOrderEntry[] | null;
    setup: string[] | null;
    special_rules: string[] | null;
    victory: string[] | null;
    defeat: string[] | null;
    progression: string[] | null;
}

export interface CoopCampaign {
    victory: string | null;
    defeat: string | null;
    aftermath: string[] | null;
    next_encounter: string | null;
}

export const ROLE_LABELS: Record<CoopRole, string> = {
    obstructor: 'Obstructor',
    brawler: 'Brawler',
    firepower: 'Firepower',
    support: 'Support',
    boss: 'Boss',
};

/** "13+" / "4-7" / "5" label for a behavior row's flip range. */
export function flipLabel(row: CoopBehaviorRow): string {
    if (row.flip_min !== null && row.flip_max === null) return `${row.flip_min}+`;
    if (row.flip_min !== null && row.flip_max !== null && row.flip_min !== row.flip_max) return `${row.flip_min}-${row.flip_max}`;
    return String(row.flip_min ?? row.flip_max ?? '—');
}
