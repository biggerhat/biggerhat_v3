<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import CrewBuilderReferences from '@/components/CrewBuilderReferences.vue';
import GameIcon from '@/components/GameIcon.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { NumberField, NumberFieldContent, NumberFieldDecrement, NumberFieldIncrement, NumberFieldInput } from '@/components/ui/number-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import UpgradeFlipCard from '@/components/UpgradeFlipCard.vue';
import { factionBackground } from '@/composables/useFactionColor';
import { csrfToken } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useVirtualizer } from '@tanstack/vue-virtual';
import { refDebounced, useMediaQuery } from '@vueuse/core';
import {
    Archive,
    ArchiveRestore,
    ArrowLeft,
    Check,
    CircleX,
    Copy,
    Download,
    FileText,
    Globe,
    Loader2,
    Lock,
    Pencil,
    Plus,
    Printer,
    Save,
    Search,
    Shield,
    ShieldAlert,
    Star,
    Swords,
    Trash2,
    Upload,
    UserMinus,
    UserPlus,
} from 'lucide-vue-next';
import { computed, defineAsyncComponent, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const TipTapEditor = defineAsyncComponent(() => import('@/components/blog/TipTapEditor.vue'));

interface Keyword {
    id: number;
    name: string;
    slug: string;
}

interface HiringRules {
    alternate_leader_id?: number;
    any_faction?: boolean;
    fixed_crew_keyword?: string;
    fixed_cache?: number;
    required_characteristic?: string;
    required_count?: number;
}

interface CrewUpgrade {
    id: number;
    name: string;
    slug: string;
    front_image: string | null;
    back_image: string | null;
    keywords: Keyword[];
    hiring_rules: HiringRules | null;
}

interface MiniatureData {
    id: number;
    name: string;
    title: string | null;
    display_name: string;
    slug: string;
    version: number;
    front_image: string | null;
    back_image: string | null;
}

interface CharacterData {
    id: number;
    name: string;
    title: string | null;
    display_name: string;
    slug: string;
    faction: string;
    second_faction: string | null;
    station: string;
    cost: number;
    health: number;
    speed: number;
    defense: number;
    willpower: number;
    count: number;
    has_totem_id: number | null;
    keywords: Keyword[];
    characteristics: string[];
    crew_upgrades: CrewUpgrade[];
    totem_slug: string | null;
    miniatures: MiniatureData[];
}

interface Faction {
    slug: string;
    name: string;
    color: string;
    logo: string;
}

interface CrewMember {
    character: CharacterData;
    miniature: MiniatureData | null;
    isTotem: boolean;
    effectiveCost: number;
    hiringCategory: 'leader' | 'totem' | 'in-keyword' | 'versatile' | 'ook' | 'fixed-crew' | 'required';
    isCustom?: boolean;
    customCharacterId?: number;
}

interface CustomCharacterData {
    id: number;
    display_name: string;
    name: string;
    title: string | null;
    slug: string;
    faction: string;
    station: string;
    cost: number | null;
    health: number;
    speed: number;
    defense: number;
    willpower: number;
    count: number;
    keywords: { id: number | null; name: string }[];
    characteristics: string[];
    front_image: string | null;
    back_image: string | null;
    is_custom: true;
}

interface SavedBuild {
    id: number;
    name: string;
    description: Record<string, unknown> | null;
    share_code: string;
    faction: string;
    master_id: number;
    encounter_size: number;
    crew_data: number[];
    custom_crew_data: any[] | null;
    miniature_selections: Record<string, number> | null;
    crew_upgrade_id: number | null;
    is_archived: boolean;
    is_public: boolean;
    updated_at: string;
    copied_from: { name: string; share_code: string; is_public: boolean } | null;
    custom_references: Record<string, any[]> | null;
}

const props = defineProps<{
    characters: CharacterData[];
    factions: Record<string, Faction>;
    keywords: Keyword[];
    savedBuilds: SavedBuild[];
    customCharacters: CustomCharacterData[];
    ownedCharacterIds: number[];
}>();

const page = usePage<SharedData>();
const isAuthenticated = computed(() => !!page.props.auth.user);

// ─── Core State ───
const encounterSize = ref(50);
const allowOverhire = ref(false);
const selectedFaction = ref<string | null>(null);
const selectedMasterName = ref<string | null>(null);
const selectedMasterTitle = ref<CharacterData | null>(null);
const crew = ref<CrewMember[]>([]);
const filterText = ref('');
const debouncedFilterText = refDebounced(filterText, 150);

// ─── Card Preview (hiring pool) ───
const previewDrawerOpen = ref(false);
const previewCharacter = ref<CharacterData | null>(null);
const previewMiniature = ref<MiniatureData | null>(null);

const openCardPreview = (character: CharacterData, miniature?: MiniatureData | null) => {
    const mini = miniature ?? character.miniatures?.[0] ?? null;
    if (!mini?.front_image) return;
    previewCharacter.value = character;
    previewMiniature.value = mini;
    previewDrawerOpen.value = true;
};

// ─── Upgrade Preview ───
const upgradePreviewOpen = ref(false);
const upgradePreviewUpgrade = ref<CrewUpgrade | null>(null);

const openUpgradePreview = (upgrade: CrewUpgrade) => {
    if (!upgrade.front_image) return;
    upgradePreviewUpgrade.value = upgrade;
    upgradePreviewOpen.value = true;
};

// ─── Crew Member Preview ───
const crewPreviewDrawerOpen = ref(false);
const crewPreviewIndex = ref<number | null>(null);
const crewPreviewMember = computed(() => (crewPreviewIndex.value !== null ? (crew.value[crewPreviewIndex.value] ?? null) : null));

const openCrewMemberPreview = (index: number) => {
    const member = crew.value[index];
    if (!member) return;
    const mini = member.miniature ?? member.character.miniatures?.[0] ?? null;
    if (!mini?.front_image) return;
    crewPreviewIndex.value = index;
    crewPreviewDrawerOpen.value = true;
};

// ─── Save State ───
const isMobile = useMediaQuery('(max-width: 767px)');
const mobileBuilderTab = ref<'crew' | 'hiring'>('crew');
const crewName = ref('Untitled Crew');
const crewDescription = ref<Record<string, unknown> | null>(null);
const showDescriptionEditor = ref(false);
const currentBuildId = ref<number | null>(null);
const currentShareCode = ref<string | null>(null);
const isSaving = ref(false);
let currentSavePromise: Promise<void> | null = null;
const saveDebounceTimer = ref<ReturnType<typeof setTimeout> | null>(null);
const lastSavedAt = ref<string | null>(null);
const shareTooltip = ref(false);
const saveError = ref<string | null>(null);
const savedBuilds = ref<SavedBuild[]>([...(props.savedBuilds as SavedBuild[])]);
const buildsTab = ref('active');

// Keep local builds in sync when props refresh (e.g. after Inertia reload)
watch(
    () => props.savedBuilds,
    (newBuilds) => {
        savedBuilds.value = [...(newBuilds as SavedBuild[])];
    },
);

// ─── View mode: 'builds' (list) or 'editor' (crew builder) ───
const urlParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : new URLSearchParams();
const startInEditor = urlParams.has('new') || urlParams.has('build') || urlParams.has('crew') || urlParams.has('step');
const fromGameUuid = urlParams.get('from_game');
const viewMode = ref<'builds' | 'editor'>(isAuthenticated.value && !startInEditor ? 'builds' : 'editor');

// Refresh builds from server when entering builds list (cross-device/tab sync)
watch(viewMode, (mode) => {
    if (mode === 'builds' && isAuthenticated.value) {
        router.reload({ only: ['savedBuilds'] });
    }
});

const isOwner = computed(() => {
    if (!currentBuildId.value || !isAuthenticated.value) return false;
    return true;
});

// ─── Step tracking (within editor) ───
const editorStep = computed(() => {
    if (!selectedFaction.value) return 'faction';
    if (!selectedMasterName.value) return 'master-name';
    if (!selectedMasterTitle.value) return 'master-title';
    return 'hiring';
});

// ─── Character lookup map ───
const characterById = computed(() => {
    const map = new Map<number, CharacterData>();
    (props.characters as CharacterData[]).forEach((c) => map.set(c.id, c));
    return map;
});

// ─── Builds list helpers ───
const activeBuilds = computed(() => savedBuilds.value.filter((b) => !b.is_archived));
const archivedBuilds = computed(() => savedBuilds.value.filter((b) => b.is_archived));


// ─── Faction selection ───
const selectFaction = (factionSlug: string) => {
    selectedFaction.value = factionSlug;
    selectedMasterName.value = null;
    selectedMasterTitle.value = null;
    crew.value = [];
    syncUrlToState(true);
};

const lastPushedUrl = ref<string | null>(null);

/** Build a clean URL with only the given params */
const buildUrl = (params: Record<string, string>): string => {
    const url = new URL(window.location.href);
    // Clear all crew builder params
    for (const key of ['build', 'crew', 'new', 'step', 'faction', 'master']) {
        url.searchParams.delete(key);
    }
    for (const [k, v] of Object.entries(params)) {
        url.searchParams.set(k, v);
    }
    return url.toString();
};

/** Push or replace the URL to reflect current editor state */
const syncUrlToState = (push = false) => {
    let url: string;

    if (viewMode.value === 'builds') {
        url = buildUrl({});
    } else if (currentShareCode.value) {
        url = buildUrl({ build: currentShareCode.value });
    } else if (editorStep.value === 'hiring' && selectedMasterTitle.value) {
        url = buildUrl({ step: 'hiring', faction: selectedFaction.value!, master: String(selectedMasterTitle.value.id) });
    } else if (editorStep.value === 'master-title' && selectedFaction.value && selectedMasterName.value) {
        url = buildUrl({ step: 'title', faction: selectedFaction.value, master: selectedMasterName.value });
    } else if (editorStep.value === 'master-name' && selectedFaction.value) {
        url = buildUrl({ step: 'master', faction: selectedFaction.value });
    } else {
        url = buildUrl({ step: 'faction' });
    }

    if (url === lastPushedUrl.value) return;

    if (push) {
        window.history.pushState({ crewBuilder: true }, '', url);
    } else {
        window.history.replaceState({ crewBuilder: true }, '', url);
    }
    lastPushedUrl.value = url;
};

const pushBuildToUrl = () => {
    if (!currentShareCode.value) return;
    syncUrlToState(true);
};

const clearBuildFromUrl = () => {
    syncUrlToState(false);
};

const restoreFromUrl = () => {
    const params = new URLSearchParams(window.location.search);
    lastPushedUrl.value = window.location.href;

    const buildParam = params.get('build');
    if (buildParam) {
        const build = savedBuilds.value.find((b) => b.share_code === buildParam);
        if (build) {
            loadBuild(build);
            return;
        }
    }

    const crewParam = params.get('crew');
    if (crewParam) {
        try {
            const state = JSON.parse(atob(crewParam));
            crewName.value = state.n || 'Untitled Crew';
            encounterSize.value = state.e || 50;
            rebuildCrew(state.f, state.m, state.c ?? [], state.u ?? null);
            currentBuildId.value = null;
            currentShareCode.value = null;
            lastSavedAt.value = null;
            viewMode.value = 'editor';
        } catch {
            // Invalid param
        }
        return;
    }

    const step = params.get('step');
    if (step) {
        viewMode.value = 'editor';
        const faction = params.get('faction');
        const masterParam = params.get('master');

        if (step === 'faction') {
            selectedFaction.value = null;
            selectedMasterName.value = null;
            selectedMasterTitle.value = null;
            crew.value = [];
        } else if (step === 'master' && faction) {
            selectedFaction.value = faction;
            selectedMasterName.value = null;
            selectedMasterTitle.value = null;
            crew.value = [];
        } else if (step === 'title' && faction && masterParam) {
            selectedFaction.value = faction;
            selectedMasterName.value = masterParam;
            selectedMasterTitle.value = null;
            crew.value = [];
        } else if (step === 'hiring' && faction && masterParam) {
            const masterId = Number(masterParam);
            const master = characterById.value.get(masterId);
            if (master) {
                rebuildCrew(faction, masterId, [], null);
            }
        }
        return;
    }

    // No relevant params — go to builds list
    currentBuildId.value = null;
    currentShareCode.value = null;
    lastSavedAt.value = null;
    crewName.value = 'Untitled Crew';
    crewDescription.value = null;
    showDescriptionEditor.value = false;
    encounterSize.value = 50;
    selectedFaction.value = null;
    selectedMasterName.value = null;
    selectedMasterTitle.value = null;
    activeCrewUpgradeId.value = null;
    crew.value = [];
    viewMode.value = isAuthenticated.value ? 'builds' : 'editor';
};

const onPopState = () => {
    restoreFromUrl();
};

const resetBuildState = () => {
    currentBuildId.value = null;
    currentShareCode.value = null;
    lastSavedAt.value = null;
    crewName.value = 'Untitled Crew';
    crewDescription.value = null;
    showDescriptionEditor.value = false;
    encounterSize.value = 50;
    selectedFaction.value = null;
    selectedMasterName.value = null;
    selectedMasterTitle.value = null;
    activeCrewUpgradeId.value = null;
    customReferences.value = { characters: [], upgrades: [], markers: [], tokens: [] };
    crew.value = [];
    clearBuildFromUrl();
};

// ─── Masters for selected faction ───
const mastersForFaction = computed(() => {
    if (!selectedFaction.value) return [];
    const masters = (props.characters as CharacterData[]).filter(
        (c) => c.station === 'master' && (c.faction === selectedFaction.value || c.second_faction === selectedFaction.value),
    );

    // Include alternate leaders (e.g., Wrath from On Tour)
    for (const alt of alternateLeaders.value) {
        if (
            alt.upgrade.hiring_rules?.any_faction ||
            alt.character.faction === selectedFaction.value ||
            alt.character.second_faction === selectedFaction.value
        ) {
            if (!masters.some((m) => m.id === alt.character.id)) {
                masters.push(alt.character);
            }
        }
    }

    return masters;
});

const uniqueMasterNames = computed(() => {
    const names = new Set<string>();
    mastersForFaction.value.forEach((m) => names.add(m.name));
    return [...names].sort();
});

interface MasterNameInfo {
    name: string;
    titles: CharacterData[];
    keywords: Keyword[];
    miniature: MiniatureData | null;
    isAlternateLeader: boolean;
}

const masterNameDetails = computed((): MasterNameInfo[] => {
    return uniqueMasterNames.value.map((name) => {
        const titles = mastersForFaction.value.filter((m) => m.name === name);
        // Collect unique keywords across all titles
        const keywordMap = new Map<string, Keyword>();
        for (const title of titles) {
            for (const kw of title.keywords) {
                if (!keywordMap.has(kw.slug)) keywordMap.set(kw.slug, kw);
            }
        }
        // Use the first title's first miniature as the representative image
        const miniature = titles[0]?.miniatures?.[0] ?? null;
        const isAlt = alternateLeaders.value.some((a) => a.character.name === name);
        return {
            name,
            titles,
            keywords: [...keywordMap.values()],
            miniature,
            isAlternateLeader: isAlt,
        };
    });
});

// ─── Master title variants ───
const masterTitleVariants = computed(() => {
    if (!selectedMasterName.value) return [];
    return mastersForFaction.value.filter((m) => m.name === selectedMasterName.value);
});

const selectMasterName = (name: string) => {
    selectedMasterName.value = name;
    const variants = mastersForFaction.value.filter((m) => m.name === name);
    if (variants.length === 1) {
        selectMasterTitle(variants[0]);
    } else {
        syncUrlToState(true);
    }
};

const selectMasterTitle = (master: CharacterData) => {
    selectedMasterTitle.value = master;
    crew.value = [];
    poolFilter.value = 'in-keyword';

    // Check if this is an alternate leader — find the upgrade that references them
    const altLeaderEntry = alternateLeaders.value.find((a) => a.character.id === master.id);
    if (altLeaderEntry) {
        activeCrewUpgradeId.value = altLeaderEntry.upgrade.id;
    } else {
        activeCrewUpgradeId.value = master.crew_upgrades?.[0]?.id ?? null;
    }

    if (crewName.value === 'Untitled Crew' || crewName.value.startsWith('Untitled ')) {
        crewName.value = `Untitled ${master.display_name} Crew`;
    }

    for (let i = 0; i < (master.count || 1); i++) {
        crew.value.push({
            character: master,
            miniature: getNextMiniature(master),
            isTotem: false,
            effectiveCost: 0,
            hiringCategory: 'leader',
        });
    }

    if (master.has_totem_id) {
        const totem = characterById.value.get(master.has_totem_id);
        if (totem) {
            for (let i = 0; i < (totem.count || 1); i++) {
                crew.value.push({ character: totem, miniature: getNextMiniature(totem), isTotem: true, effectiveCost: 0, hiringCategory: 'totem' });
            }
        }
    }

    // Apply hiring rules if the active upgrade has them
    const rules = activeUpgradeHiringRules.value;
    if (rules) {
        applyHiringRules(rules);
    }

    // Push URL for the hiring step
    syncUrlToState(true);

    // Save immediately for new builds, debounce for existing
    if (isAuthenticated.value && !currentBuildId.value) {
        saveBuild();
    } else {
        triggerAutosave();
    }
};

// ─── Swap master title (keep crew) ───
const swapMasterTitle = (master: CharacterData) => {
    selectedMasterTitle.value = master;
    activeCrewUpgradeId.value = master.crew_upgrades?.[0]?.id ?? null;

    if (crewName.value === 'Untitled Crew' || crewName.value.startsWith('Untitled ')) {
        crewName.value = `Untitled ${master.display_name} Crew`;
    }

    // Remove old leaders, totems, and special category members
    crew.value = crew.value.filter(
        (m) => m.hiringCategory !== 'leader' && m.hiringCategory !== 'totem' && m.hiringCategory !== 'fixed-crew' && m.hiringCategory !== 'required',
    );

    // Add new leader(s) at the start
    const leaders: CrewMember[] = [];
    for (let i = 0; i < (master.count || 1); i++) {
        leaders.push({
            character: master,
            miniature: getNextMiniature(master),
            isTotem: false,
            effectiveCost: 0,
            hiringCategory: 'leader',
        });
    }
    crew.value.unshift(...leaders);
    if (master.has_totem_id) {
        const totem = characterById.value.get(master.has_totem_id);
        if (totem) {
            for (let i = 0; i < (totem.count || 1); i++) {
                crew.value.splice(leaders.length, 0, {
                    character: totem,
                    miniature: getNextMiniature(totem),
                    isTotem: true,
                    effectiveCost: 0,
                    hiringCategory: 'totem',
                });
            }
        }
    }

    recalculateHiringCategories();

    // Apply hiring rules if active upgrade has them
    const rules = activeUpgradeHiringRules.value;
    if (rules) {
        applyHiringRules(rules);
    }

    triggerAutosave();
};

// ─── Keyword helpers ───
const leaderKeywordSlugs = computed(() => {
    if (!selectedMasterTitle.value) return new Set<string>();
    const slugs = new Set<string>();
    selectedMasterTitle.value.keywords.forEach((k) => slugs.add(k.slug));
    selectedMasterTitle.value.crew_upgrades?.forEach((u) => {
        u.keywords?.forEach((k) => slugs.add(k.slug));
    });
    return slugs;
});

const characterSharesKeyword = (character: CharacterData): boolean => {
    return character.keywords.some((k) => leaderKeywordSlugs.value.has(k.slug));
};

const isVersatile = (character: CharacterData): boolean => character.characteristics.includes('versatile');
const isLoyal = (character: CharacterData): boolean => character.characteristics.includes('loyal');
const characterInFaction = (character: CharacterData): boolean =>
    character.faction === selectedFaction.value || character.second_faction === selectedFaction.value;

const getHiringCategory = (character: CharacterData): 'in-keyword' | 'versatile' | 'ook' => {
    if (characterSharesKeyword(character)) return 'in-keyword';
    if (isVersatile(character)) return 'versatile';
    return 'ook';
};

const getEffectiveCost = (character: CharacterData): number => {
    return getHiringCategory(character) === 'ook' ? character.cost + 1 : character.cost;
};

// ─── OOK / Cost tracking ───
const ookCount = computed(() => crew.value.filter((m) => m.hiringCategory === 'ook').length);
const totalSpent = computed(() => crew.value.reduce((sum, m) => sum + m.effectiveCost, 0));
const remaining = computed(() => encounterSize.value - totalSpent.value);
const soulstonePool = computed(() => {
    if (isFixedCrew.value && activeUpgradeHiringRules.value?.fixed_cache != null) {
        return activeUpgradeHiringRules.value.fixed_cache;
    }
    const r = remaining.value;
    return r > 6 ? 6 : Math.max(0, r);
});
const isOverBudget = computed(() => totalSpent.value > encounterSize.value);

// ─── Crew Stats ───
const crewStats = computed(() => {
    if (crew.value.length === 0) return null;
    const hirable = crew.value.filter((m) => m.hiringCategory !== 'leader' && m.hiringCategory !== 'totem');
    const nums = (arr: (number | null | undefined)[]) => arr.filter((v): v is number => typeof v === 'number' && v > 0);
    const avg = (vals: number[]) => (vals.length ? Math.round((vals.reduce((a, b) => a + b, 0) / vals.length) * 10) / 10 : null);
    const suitCounts: Record<string, number> = {};
    for (const member of crew.value) {
        for (const suit of member.character.trigger_suits ?? []) {
            suitCounts[suit] = (suitCounts[suit] ?? 0) + 1;
        }
    }

    return {
        models: crew.value.length,
        avgCost: avg(nums(hirable.map((m) => m.effectiveCost))),
        avgHealth: avg(nums(crew.value.map((m) => m.character.health))),
        avgSpeed: avg(nums(crew.value.map((m) => m.character.speed))),
        avgDefense: avg(nums(crew.value.map((m) => m.character.defense))),
        avgWillpower: avg(nums(crew.value.map((m) => m.character.willpower))),
        suitCounts,
    };
});

const hiredCountOf = (characterId: number): number => crew.value.filter((m) => m.character.id === characterId && !m.isTotem).length;

// ─── Totem check ───
const isTotemOfAnotherMaster = (character: CharacterData): boolean => {
    if (!selectedMasterTitle.value) return false;
    // If this character is the selected master's totem, it's already auto-added
    if (selectedMasterTitle.value.has_totem_id === character.id) return true;
    // If any other master has this as their totem, it's not hireable
    return (props.characters as CharacterData[]).some((c) => c.station === 'master' && c.has_totem_id === character.id);
};

// ─── Can hire check ───
const canHire = (character: CharacterData): { allowed: boolean; reason?: string } => {
    if (character.cost == null) return { allowed: false, reason: 'No cost' };
    if (character.station === 'master') return { allowed: false, reason: 'Cannot hire masters' };
    if (isTotemOfAnotherMaster(character)) return { allowed: false, reason: 'Totem' };
    if (hiredCountOf(character.id) >= character.count) return { allowed: false, reason: `Max ${character.count}` };
    // Title restriction: two versions of the same character (same name, different title) cannot coexist.
    // Models with no title are treated as a different title than models with a title.
    const conflicting = crew.value.find(
        (m) => m.character.name === character.name && m.character.id !== character.id && (character.title || m.character.title), // At least one must have a title for the rule to apply
    );
    if (conflicting) {
        return { allowed: false, reason: `Title conflict (${conflicting.character.title || conflicting.character.name} hired)` };
    }
    const sharesKeyword = characterSharesKeyword(character);
    if (!sharesKeyword && !characterInFaction(character)) return { allowed: false, reason: 'Not in faction' };
    if (isLoyal(character) && !sharesKeyword) return { allowed: false, reason: 'Loyal' };
    const category = getHiringCategory(character);
    if (category === 'ook' && ookCount.value >= 2) return { allowed: false, reason: 'OOK limit (2)' };
    if (!allowOverhire.value && getEffectiveCost(character) > remaining.value) return { allowed: false, reason: 'Over budget' };
    return { allowed: true };
};

// ─── Miniature assignment ───
const getNextMiniature = (character: CharacterData): MiniatureData | null => {
    const miniatures = character.miniatures ?? [];
    if (miniatures.length === 0) return null;
    const usedMiniatureIds = new Set(crew.value.filter((m) => m.character.id === character.id && m.miniature).map((m) => m.miniature!.id));
    return miniatures.find((m) => !usedMiniatureIds.has(m.id)) ?? miniatures[0];
};

// ─── Add / Remove ───
const addToCrewById = (character: CharacterData) => {
    crew.value.push({
        character,
        miniature: getNextMiniature(character),
        isTotem: false,
        effectiveCost: getEffectiveCost(character),
        hiringCategory: getHiringCategory(character),
    });
    triggerAutosave();
};

const addToCrewWithMiniature = (character: CharacterData, miniature: MiniatureData | null) => {
    crew.value.push({
        character,
        miniature: miniature ?? getNextMiniature(character),
        isTotem: false,
        effectiveCost: getEffectiveCost(character),
        hiringCategory: getHiringCategory(character),
    });
    triggerAutosave();
};

const addCustomToCrew = (custom: CustomCharacterData) => {
    const keywordSlugs = (custom.keywords ?? []).map((k) => k.name.toLowerCase().replace(/\s+/g, '-'));
    const leaderKws = leaderKeywordSlugs.value;
    const shares = keywordSlugs.some((s: string) => leaderKws.has(s));
    const versatile = (custom.characteristics ?? []).some((c) => c.toLowerCase() === 'versatile');
    const category = shares ? 'in-keyword' : versatile ? 'versatile' : 'ook';
    const baseCost = custom.cost ?? 0;
    const effectiveCost = category === 'ook' ? baseCost + 1 : baseCost;

    // Adapt CustomCharacterData to CharacterData shape for the CrewMember
    const asCharData: CharacterData = {
        id: -custom.id, // Negative ID to distinguish from official
        name: custom.name,
        title: custom.title,
        display_name: custom.display_name,
        slug: custom.slug,
        faction: custom.faction,
        second_faction: null,
        station: custom.station,
        cost: baseCost,
        health: custom.health,
        speed: custom.speed,
        defense: custom.defense,
        willpower: custom.willpower,
        count: custom.count,
        has_totem_id: null,
        keywords: custom.keywords.map((k) => ({ id: k.id ?? 0, name: k.name, slug: k.name.toLowerCase().replace(/\s+/g, '-') })),
        characteristics: custom.characteristics,
        crew_upgrades: [],
        totem_slug: null,
        miniatures: [],
        ...(custom.front_image ? { front_image: custom.front_image } : {}),
        ...(custom.back_image ? { back_image: custom.back_image } : {}),
    } as any;

    crew.value.push({
        character: asCharData,
        miniature: null,
        isTotem: false,
        effectiveCost,
        hiringCategory: category as any,
        isCustom: true,
        customCharacterId: custom.id,
    });
    triggerAutosave();
};

const removeFromCrew = (index: number) => {
    const cat = crew.value[index].hiringCategory;
    if (cat === 'leader' || cat === 'totem' || cat === 'fixed-crew' || cat === 'required') return;
    crew.value.splice(index, 1);
    triggerAutosave();
};

const clearHiredModels = () => {
    crew.value = crew.value.filter(
        (m) => m.hiringCategory === 'leader' || m.hiringCategory === 'totem' || m.hiringCategory === 'fixed-crew' || m.hiringCategory === 'required',
    );
    triggerAutosave();
};

const recalculateHiringCategories = () => {
    crew.value.forEach((member) => {
        if (
            member.hiringCategory === 'leader' ||
            member.hiringCategory === 'totem' ||
            member.hiringCategory === 'fixed-crew' ||
            member.hiringCategory === 'required'
        )
            return;
        const cat = getHiringCategory(member.character);
        member.hiringCategory = cat;
        member.effectiveCost = cat === 'ook' ? member.character.cost + 1 : member.character.cost;
    });
    let ookSeen = 0;
    const toRemove: number[] = [];
    crew.value.forEach((m, idx) => {
        if (m.hiringCategory === 'ook' && ++ookSeen > 2) toRemove.push(idx);
    });
    toRemove.reverse().forEach((idx) => crew.value.splice(idx, 1));
};

// ─── Hiring pool ───
type PoolFilter = 'in-keyword' | 'versatile' | 'ook' | 'all';
const poolFilter = ref<PoolFilter>('in-keyword');

type PoolSort = 'station' | 'name' | 'cost';
const poolSort = ref<PoolSort>('station');
const ownedOnly = ref(false);
const ownedSet = computed(() => new Set(props.ownedCharacterIds));

const isUnique = (character: CharacterData): boolean => character.characteristics.includes('unique');
const isHenchman = (character: CharacterData): boolean => character.characteristics.includes('henchman');

const stationSortOrder = (character: CharacterData): number => {
    const henchman = isHenchman(character);
    const unique = isUnique(character);
    // Henchman & Unique first, then just Unique, then Minion, then Peon
    if (henchman && unique) return 0;
    if (henchman) return 1;
    if (unique) return 2;
    if (character.station === 'minion') return 3;
    if (character.station === 'peon') return 4;
    return 5;
};

const hiringPool = computed(() => {
    if (!selectedMasterTitle.value || !selectedFaction.value) return [];
    if (isFixedCrew.value) return []; // No hiring pool for fixed crews

    const requiredIds = new Set(requiredHires.value.map((c) => c.id));

    return (props.characters as CharacterData[]).filter((c) => {
        if (c.station === 'master') return false;
        if (c.cost == null) return false;
        if (isTotemOfAnotherMaster(c)) return false;
        if (requiredIds.has(c.id)) return false; // Exclude required hires from normal pool
        // Keyword models can be hired regardless of faction
        if (characterSharesKeyword(c)) {
            // Loyal models must share a keyword (already true if we're here) — allow
            return true;
        }
        // Non-keyword models must be in faction
        if (!characterInFaction(c)) return false;
        if (isLoyal(c)) return false; // Loyal + no shared keyword = not hireable
        return true;
    });
});

const filteredHiringPool = computed(() => {
    let filtered = hiringPool.value;

    if (poolFilter.value !== 'all') {
        filtered = filtered.filter((c) => getHiringCategory(c) === poolFilter.value);
    }

    if (ownedOnly.value && ownedSet.value.size > 0) {
        filtered = filtered.filter((c) => ownedSet.value.has(c.id));
    }

    const filter = debouncedFilterText.value.toLowerCase();
    if (filter) {
        filtered = filtered.filter(
            (c) => c.display_name.toLowerCase().includes(filter) || c.keywords.some((k) => k.name.toLowerCase().includes(filter)),
        );
    }

    // Sort
    filtered = [...filtered].sort((a, b) => {
        if (poolSort.value === 'name') {
            return a.display_name.localeCompare(b.display_name);
        }
        if (poolSort.value === 'cost') {
            const costDiff = getEffectiveCost(b) - getEffectiveCost(a);
            return costDiff !== 0 ? costDiff : a.display_name.localeCompare(b.display_name);
        }
        // Default: station sort
        const stationDiff = stationSortOrder(a) - stationSortOrder(b);
        return stationDiff !== 0 ? stationDiff : a.display_name.localeCompare(b.display_name);
    });

    return filtered;
});

interface PoolEntry {
    character: CharacterData;
    category: 'in-keyword' | 'versatile' | 'ook';
    effectiveCost: number;
    hireCheck: { allowed: boolean; reason?: string };
    henchman: boolean;
    unique: boolean;
}

const augmentedPool = computed((): PoolEntry[] =>
    filteredHiringPool.value.map((c) => {
        const category = getHiringCategory(c);
        return {
            character: c,
            category,
            effectiveCost: category === 'ook' ? c.cost + 1 : c.cost,
            hireCheck: canHire(c),
            henchman: isHenchman(c),
            unique: isUnique(c),
        };
    }),
);

const poolFilterCounts = computed(() => {
    const pool = ownedOnly.value && ownedSet.value.size > 0 ? hiringPool.value.filter((c) => ownedSet.value.has(c.id)) : hiringPool.value;
    return {
        'in-keyword': pool.filter((c) => getHiringCategory(c) === 'in-keyword').length,
        versatile: pool.filter((c) => getHiringCategory(c) === 'versatile').length,
        ook: pool.filter((c) => getHiringCategory(c) === 'ook').length,
        all: pool.length,
    };
});

// ─── References ───
interface ReferenceData {
    markers: { id: number; name: string; slug: string }[];
    tokens: { id: number; name: string; slug: string }[];
    upgrades: { id: number; name: string; slug: string; front_image: string | null; back_image: string | null }[];
    characters: { id: number; display_name: string; slug: string; faction: string; type: string }[];
}

const references = ref<ReferenceData | null>(null);
const referencesLoading = ref(false);

const crewCharacterIds = computed(() => crew.value.map((m) => m.character.id));

const debouncedCrewIds = refDebounced(crewCharacterIds, 500);

const mergeCustomIntoReferences = (base: ReferenceData): ReferenceData => {
    const custom = customReferences.value;
    const result = { ...base };
    for (const type of ['characters', 'upgrades', 'markers', 'tokens'] as const) {
        const customItems = custom[type] ?? [];
        if (!customItems.length) continue;
        const existingIds = new Set((result[type] as any[]).map((item: any) => item.id));
        result[type] = [...result[type], ...customItems.filter((item: any) => !existingIds.has(item.id))] as any;
    }
    return result;
};

watch(
    debouncedCrewIds,
    async (ids) => {
        if (ids.length === 0) {
            references.value = null;
            return;
        }
        referencesLoading.value = true;
        try {
            const params = new URLSearchParams();
            ids.forEach((id) => params.append('ids[]', String(id)));
            const res = await fetch(route('tools.crew_builder.references') + '?' + params.toString());
            const data = await res.json();
            references.value = mergeCustomIntoReferences(data);
        } catch {
            references.value = null;
        } finally {
            referencesLoading.value = false;
        }
    },
    { immediate: true },
);

const customReferences = ref<Record<string, any[]>>({ characters: [], upgrades: [], markers: [], tokens: [] });

const addCustomReference = (type: 'characters' | 'upgrades' | 'markers' | 'tokens', item: any) => {
    if (!references.value) {
        references.value = { markers: [], tokens: [], upgrades: [], characters: [] };
    }
    const list = references.value[type] as any[];
    if (list.some((existing: any) => existing.id === item.id)) return;

    let formatted: any;
    if (type === 'characters') {
        const firstMini = item.miniatures?.[0];
        formatted = {
            id: item.id,
            display_name: item.display_name ?? item.name,
            slug: item.slug,
            faction: item.faction,
            type: 'Custom',
            front_image: firstMini?.front_image ?? null,
            back_image: firstMini?.back_image ?? null,
        };
    } else if (type === 'upgrades') {
        formatted = {
            id: item.id,
            name: item.name,
            slug: item.slug,
            front_image: item.front_image ?? null,
            back_image: item.back_image ?? null,
            type: item.type ?? null,
        };
    } else if (type === 'markers') {
        formatted = {
            id: item.id,
            name: item.name,
            slug: item.slug,
            description: item.description ?? null,
            base: item.base ?? null,
        };
    } else {
        formatted = {
            id: item.id,
            name: item.name,
            slug: item.slug,
            description: item.description ?? null,
        };
    }

    list.push(formatted);

    // Track as custom and trigger autosave
    if (!customReferences.value[type].some((c: any) => c.id === formatted.id)) {
        customReferences.value[type].push(formatted);
    }
    triggerAutosave();
};

// ─── Virtual scroller ───
const poolScrollRef = ref<HTMLElement | null>(null);
const poolVirtualizer = useVirtualizer(
    computed(() => ({
        count: augmentedPool.value.length,
        getScrollElement: () => poolScrollRef.value,
        estimateSize: () => 56,
        overscan: 10,
    })),
);

watch([debouncedFilterText, poolFilter], () => poolScrollRef.value?.scrollTo(0, 0));

// ─── Back navigation ───
const goBack = () => {
    if (editorStep.value === 'faction' || editorStep.value === 'hiring') {
        // Exit editor entirely
        if (isAuthenticated.value) {
            viewMode.value = 'builds';
            resetBuildState();
        } else {
            resetBuildState();
        }
    } else if (editorStep.value === 'master-title') {
        selectedMasterName.value = null;
        syncUrlToState(true);
    } else if (editorStep.value === 'master-name') {
        selectedFaction.value = null;
        syncUrlToState(true);
    }
};

// ─── Crew upgrade display ───
const activeCrewUpgradeId = ref<number | null>(null);
const hasSingleCrewUpgrade = computed(() => (selectedMasterTitle.value?.crew_upgrades?.length ?? 0) === 1);

const toggleCrewUpgradeActive = (upgrade: CrewUpgrade) => {
    if (hasSingleCrewUpgrade.value) return;
    const wasActive = activeCrewUpgradeId.value === upgrade.id;
    const hadRules = activeUpgradeHiringRules.value;

    // Always clear special members from previous upgrade before switching
    if (hadRules) {
        crew.value = crew.value.filter((m) => m.hiringCategory !== 'fixed-crew' && m.hiringCategory !== 'required');
    }

    activeCrewUpgradeId.value = wasActive ? null : upgrade.id;

    // If activating a new rules-based upgrade, apply it
    if (!wasActive && upgrade.hiring_rules) {
        applyHiringRules(upgrade.hiring_rules);
    }

    triggerAutosave();
};

// ─── Hiring rules computeds ───
const activeUpgradeHiringRules = computed((): HiringRules | null => {
    if (!activeCrewUpgradeId.value || !selectedMasterTitle.value) return null;
    const upgrade = selectedMasterTitle.value.crew_upgrades?.find((u) => u.id === activeCrewUpgradeId.value);
    return upgrade?.hiring_rules ?? null;
});

const isFixedCrew = computed(() => !!activeUpgradeHiringRules.value?.fixed_crew_keyword);

const requiredHires = computed((): CharacterData[] => {
    const rules = activeUpgradeHiringRules.value;
    if (!rules?.required_characteristic) return [];
    return (props.characters as CharacterData[]).filter((c) => c.characteristics.includes(rules.required_characteristic!));
});

// ─── Alternate leaders ───
const alternateLeaders = computed((): { character: CharacterData; upgrade: CrewUpgrade }[] => {
    const results: { character: CharacterData; upgrade: CrewUpgrade }[] = [];
    for (const char of props.characters as CharacterData[]) {
        for (const upgrade of char.crew_upgrades ?? []) {
            if (upgrade.hiring_rules?.alternate_leader_id) {
                const altChar = characterById.value.get(upgrade.hiring_rules.alternate_leader_id);
                if (altChar) {
                    results.push({ character: altChar, upgrade });
                }
            }
        }
    }
    return results;
});

const applyHiringRules = (rules: HiringRules) => {
    if (rules.fixed_crew_keyword) {
        // Fixed crew: clear hired models, add keyword members
        crew.value = crew.value.filter((m) => m.hiringCategory === 'leader' || m.hiringCategory === 'totem');
        const keywordMembers = (props.characters as CharacterData[]).filter(
            (c) => c.keywords.some((k) => k.slug === rules.fixed_crew_keyword) && c.id !== selectedMasterTitle.value?.id,
        );
        for (const char of keywordMembers) {
            for (let i = 0; i < (char.count || 1); i++) {
                crew.value.push({
                    character: char,
                    miniature: getNextMiniature(char),
                    isTotem: false,
                    effectiveCost: char.cost ?? 0,
                    hiringCategory: 'fixed-crew',
                });
            }
        }
    } else if (rules.required_characteristic) {
        // Required hires: add required models (don't clear existing)
        crew.value = crew.value.filter((m) => m.hiringCategory !== 'required');
        for (const char of requiredHires.value) {
            crew.value.push({
                character: char,
                miniature: getNextMiniature(char),
                isTotem: false,
                effectiveCost: char.cost ?? 0,
                hiringCategory: 'required',
            });
        }
    }
};

// ─── Category helpers ───
const categoryLabel = (cat: string): string =>
    ({
        leader: 'Leader',
        totem: 'Totem',
        'in-keyword': 'In Keyword',
        versatile: 'Versatile',
        ook: 'Out of Keyword',
        'fixed-crew': 'Preset',
        required: 'Required',
    })[cat] ?? cat;

// Colors for faction-colored bars (white text context)
const categoryColor = (cat: string): string =>
    ({
        leader: 'bg-amber-400/20 text-amber-200',
        totem: 'bg-purple-400/20 text-purple-200',
        'in-keyword': 'bg-green-400/20 text-green-200',
        versatile: 'bg-blue-400/20 text-blue-200',
        ook: 'bg-red-400/20 text-red-200',
        'fixed-crew': 'bg-cyan-400/20 text-cyan-200',
        required: 'bg-orange-400/20 text-orange-200',
    })[cat] ?? '';

// Colors for normal theme backgrounds (drawers, cards)
const categoryColorTheme = (cat: string): string =>
    ({
        leader: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        totem: 'bg-purple-500/10 text-purple-700 dark:text-purple-400',
        'in-keyword': 'bg-green-500/10 text-green-700 dark:text-green-400',
        versatile: 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
        ook: 'bg-red-500/10 text-red-700 dark:text-red-400',
        'fixed-crew': 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-400',
        required: 'bg-orange-500/10 text-orange-700 dark:text-orange-400',
    })[cat] ?? '';

// ═══════════════════════════════════════
// Save / Load / Share / Archive
// ═══════════════════════════════════════

const buildCrewData = (): number[] =>
    crew.value.filter((m) => m.hiringCategory !== 'leader' && m.hiringCategory !== 'totem' && !m.isCustom).map((m) => m.character.id);

const buildCustomCrewData = (): any[] | null => {
    const customs = crew.value.filter((m) => m.isCustom);
    if (customs.length === 0) return null;
    return customs.map((m) => ({
        custom_character_id: m.customCharacterId,
        display_name: m.character.display_name,
        cost: m.character.cost,
        health: m.character.health,
        faction: m.character.faction,
        station: m.character.station,
        keywords: m.character.keywords,
        characteristics: m.character.characteristics,
        front_image: (m.character as any).front_image ?? null,
        back_image: (m.character as any).back_image ?? null,
    }));
};

const buildMiniatureSelections = (): Record<string, number | number[]> | null => {
    const selections: Record<string, number[]> = {};
    for (const m of crew.value) {
        if (m.miniature?.id) {
            const key = String(m.character.id);
            if (!selections[key]) selections[key] = [];
            selections[key].push(m.miniature.id);
        }
    }
    // Flatten single-entry arrays to bare numbers for backward compat
    const result: Record<string, number | number[]> = {};
    for (const [k, v] of Object.entries(selections)) {
        result[k] = v.length === 1 ? v[0] : v;
    }
    return Object.keys(result).length > 0 ? result : null;
};

const buildPayload = () => ({
    name: crewName.value,
    description: crewDescription.value,
    faction: selectedFaction.value,
    master_id: selectedMasterTitle.value?.id,
    encounter_size: encounterSize.value,
    crew_data: buildCrewData(),
    custom_crew_data: buildCustomCrewData(),
    miniature_selections: buildMiniatureSelections(),
    crew_upgrade_id: activeCrewUpgradeId.value,
    custom_references: Object.values(customReferences.value).some((arr) => arr.length > 0) ? customReferences.value : null,
});

const saveBuild = () => {
    if (!isAuthenticated.value || !selectedMasterTitle.value || isSaving.value) return;

    const doSave = async () => {
        isSaving.value = true;
        saveError.value = null;
        try {
            if (currentBuildId.value && isOwner.value) {
                const response = await fetch(route('tools.crew_builder.update', { crewBuild: currentBuildId.value }), {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
                    body: JSON.stringify(buildPayload()),
                });
                if (!response.ok) {
                    saveError.value = 'Failed to save crew';
                    return;
                }
                const data = await response.json();
                currentShareCode.value = data.share_code;
                const idx = savedBuilds.value.findIndex((b) => b.id === currentBuildId.value);
                if (idx >= 0) {
                    savedBuilds.value[idx] = {
                        ...savedBuilds.value[idx],
                        ...buildPayload(),
                        share_code: data.share_code,
                        is_public: data.is_public,
                        updated_at: new Date().toISOString(),
                    } as SavedBuild;
                }
            } else {
                const response = await fetch(route('tools.crew_builder.store'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
                    body: JSON.stringify(buildPayload()),
                });
                if (!response.ok) {
                    saveError.value = 'Failed to create crew';
                    return;
                }
                const data = await response.json();
                currentBuildId.value = data.id;
                currentShareCode.value = data.share_code;
                savedBuilds.value.unshift({
                    id: data.id,
                    share_code: data.share_code,
                    is_archived: false,
                    is_public: data.is_public,
                    updated_at: new Date().toISOString(),
                    ...buildPayload(),
                } as SavedBuild);
            }
            lastSavedAt.value = new Date().toLocaleTimeString();
            pushBuildToUrl();
        } catch {
            saveError.value = 'Network error saving crew';
        } finally {
            isSaving.value = false;
        }
    };
    currentSavePromise = doSave();
    return currentSavePromise;
};

const triggerAutosave = () => {
    if (!isAuthenticated.value) return;
    if (saveDebounceTimer.value) clearTimeout(saveDebounceTimer.value);
    saveDebounceTimer.value = setTimeout(() => {
        if (selectedMasterTitle.value) saveBuild();
    }, 2000);
};

watch(encounterSize, () => {
    if (editorStep.value === 'hiring') triggerAutosave();
});
watch(crewName, () => {
    if (editorStep.value === 'hiring') triggerAutosave();
});
watch(crewDescription, () => {
    if (editorStep.value === 'hiring') triggerAutosave();
});

// ─── Rebuild crew from a saved/shared build data ───
const rebuildCrew = (faction: string, masterId: number, crewData: number[], crewUpgradeId?: number | null) => {
    selectedFaction.value = faction;
    const master = characterById.value.get(masterId);
    if (!master) return;

    selectedMasterName.value = master.name;
    selectedMasterTitle.value = master;
    activeCrewUpgradeId.value = crewUpgradeId ?? master.crew_upgrades?.[0]?.id ?? null;
    crew.value = [];

    for (let i = 0; i < (master.count || 1); i++) {
        crew.value.push({ character: master, miniature: getNextMiniature(master), isTotem: false, effectiveCost: 0, hiringCategory: 'leader' });
    }

    if (master.has_totem_id) {
        const totem = characterById.value.get(master.has_totem_id);
        if (totem) {
            for (let i = 0; i < (totem.count || 1); i++) {
                crew.value.push({ character: totem, miniature: getNextMiniature(totem), isTotem: true, effectiveCost: 0, hiringCategory: 'totem' });
            }
        }
    }

    // Resolve hiring rules for the active upgrade
    const activeUpgrade = master.crew_upgrades?.find((u) => u.id === activeCrewUpgradeId.value);
    const rules = activeUpgrade?.hiring_rules;

    if (rules?.fixed_crew_keyword) {
        // Fixed crew mode — apply rules instead of crewData
        applyHiringRules(rules);
    } else {
        // Determine required character IDs for required_characteristic mode
        const requiredCharacteristic = rules?.required_characteristic;
        const requiredIds = new Set(
            requiredCharacteristic
                ? (props.characters as CharacterData[]).filter((c) => c.characteristics.includes(requiredCharacteristic)).map((c) => c.id)
                : [],
        );

        crewData?.forEach((charId: number) => {
            const character = characterById.value.get(charId);
            if (character) {
                if (requiredIds.has(character.id)) {
                    crew.value.push({
                        character,
                        miniature: getNextMiniature(character),
                        isTotem: false,
                        effectiveCost: character.cost,
                        hiringCategory: 'required',
                    });
                } else {
                    const cat = getHiringCategory(character);
                    crew.value.push({
                        character,
                        miniature: getNextMiniature(character),
                        isTotem: false,
                        effectiveCost: cat === 'ook' ? character.cost + 1 : character.cost,
                        hiringCategory: cat,
                    });
                }
            }
        });
    }
};

const loadBuild = (build: SavedBuild) => {
    currentBuildId.value = build.id;
    currentShareCode.value = build.share_code;
    crewName.value = build.name;
    crewDescription.value = build.description ?? null;
    showDescriptionEditor.value = !!build.description;
    encounterSize.value = build.encounter_size;
    customReferences.value = build.custom_references ?? { characters: [], upgrades: [], markers: [], tokens: [] };
    rebuildCrew(build.faction, build.master_id, build.crew_data, build.crew_upgrade_id);

    // Restore custom crew members from saved build
    if (build.custom_crew_data?.length) {
        for (const entry of build.custom_crew_data) {
            const match = props.customCharacters.find((c) => c.id === entry.custom_character_id);
            if (match) {
                addCustomToCrew(match);
            }
        }
    }

    // Restore miniature selections from saved build
    if (build.miniature_selections) {
        const indexCounters: Record<string, number> = {};
        for (const member of crew.value) {
            const key = String(member.character.id);
            const selection = build.miniature_selections[key];
            let miniId: number | null = null;

            if (Array.isArray(selection)) {
                const idx = indexCounters[key] ?? 0;
                miniId = selection[idx] ?? null;
                indexCounters[key] = idx + 1;
            } else if (selection) {
                miniId = selection;
            }

            if (miniId) {
                const mini = member.character.miniatures?.find((m: any) => m.id === miniId);
                if (mini) member.miniature = mini;
            }
        }
    }

    lastSavedAt.value = new Date(build.updated_at).toLocaleTimeString();
    viewMode.value = 'editor';
    pushBuildToUrl();
};

const deleteTarget = ref<SavedBuild | null>(null);
const deleting = ref(false);

const confirmDeleteBuild = async () => {
    if (!deleteTarget.value) return;
    deleting.value = true;
    try {
        const response = await fetch(route('tools.crew_builder.destroy', { crewBuild: deleteTarget.value.id }), {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        });
        if (response.ok) {
            savedBuilds.value = savedBuilds.value.filter((b) => b.id !== deleteTarget.value!.id);
            if (currentBuildId.value === deleteTarget.value.id) resetBuildState();
        }
    } catch {
        // Silent fail
    } finally {
        deleting.value = false;
        deleteTarget.value = null;
    }
};

const toggleArchive = async (build: SavedBuild) => {
    const newArchived = !build.is_archived;
    try {
        await fetch(route('tools.crew_builder.update', { crewBuild: build.id }), {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ is_archived: newArchived }),
        });
        const idx = savedBuilds.value.findIndex((b) => b.id === build.id);
        if (idx >= 0) savedBuilds.value[idx].is_archived = newArchived;
    } catch {
        // Silent fail
    }
};

const togglePublic = async (build: SavedBuild) => {
    const newPublic = !build.is_public;
    try {
        const response = await fetch(route('tools.crew_builder.update', { crewBuild: build.id }), {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
            body: JSON.stringify({ is_public: newPublic }),
        });
        if (!response.ok) return;
        const idx = savedBuilds.value.findIndex((b) => b.id === build.id);
        if (idx >= 0) savedBuilds.value[idx].is_public = newPublic;
    } catch {
        // Silent fail
    }
};

const currentBuildIsPublic = computed(() => {
    if (!currentBuildId.value) return false;
    const build = savedBuilds.value.find((b) => b.id === currentBuildId.value);
    return build?.is_public ?? false;
});

const toggleCurrentBuildPublic = async () => {
    if (!currentBuildId.value) return;
    const build = savedBuilds.value.find((b) => b.id === currentBuildId.value);
    if (build) await togglePublic(build);
};

const copyShareLink = () => {
    const code = currentShareCode.value;
    if (!code) return;
    navigator.clipboard.writeText(route('tools.crew_builder.share', { shareCode: code }));
    shareTooltip.value = true;
    setTimeout(() => (shareTooltip.value = false), 2000);
};

const copyShareLinkForBuild = (build: SavedBuild) => {
    if (!build.is_public) return;
    navigator.clipboard.writeText(route('tools.crew_builder.share', { shareCode: build.share_code }));
    shareTooltip.value = true;
    setTimeout(() => (shareTooltip.value = false), 2000);
};

const generateShareLink = async () => {
    if (!selectedMasterTitle.value) return;

    if (isAuthenticated.value) {
        if (!currentBuildIsPublic.value) return;

        // Cancel any pending autosave and save immediately
        if (saveDebounceTimer.value) {
            clearTimeout(saveDebounceTimer.value);
            saveDebounceTimer.value = null;
        }
        if (currentSavePromise) {
            await currentSavePromise;
        }

        await saveBuild();
        if (currentShareCode.value) {
            copyShareLink();
        }
        return;
    }

    const state = {
        f: selectedFaction.value,
        m: selectedMasterTitle.value.id,
        e: encounterSize.value,
        c: buildCrewData(),
        n: crewName.value,
        u: activeCrewUpgradeId.value,
    };
    const encoded = btoa(JSON.stringify(state));
    navigator.clipboard.writeText(`${route('tools.crew_builder.editor')}?crew=${encoded}`);
    shareTooltip.value = true;
    setTimeout(() => (shareTooltip.value = false), 2000);
};

// ─── Export: Text ───
const exportAsText = () => {
    if (!selectedMasterTitle.value || crew.value.length === 0) return;
    const leader = crew.value.find((m) => m.hiringCategory === 'leader');
    const activeUpgrade = leader?.character.crew_upgrades?.find((u: any) => u.id === activeCrewUpgradeId.value);
    const lines: string[] = [];
    lines.push(
        `${selectedMasterTitle.value.display_name} (${Object.values(props.factions).find((f) => f.slug === selectedFaction.value)?.name ?? selectedFaction.value})`,
    );
    if (activeUpgrade) lines.push(`Crew Card: ${activeUpgrade.name}`);
    lines.push('');
    const categories = [
        { key: 'leader', label: 'Leader' },
        { key: 'totem', label: 'Totem(s)' },
        { key: 'in-keyword', label: 'Hires' },
        { key: 'versatile', label: 'Versatile' },
        { key: 'ook', label: 'Out of Keyword' },
    ];
    for (const cat of categories) {
        const members = crew.value.filter((m) => m.hiringCategory === cat.key);
        if (!members.length) continue;
        lines.push(`${cat.label}:`);
        for (const m of members) lines.push(`  ${m.character.display_name}`);
    }
    navigator.clipboard.writeText(lines.join('\n'));
    shareTooltip.value = true;
    setTimeout(() => (shareTooltip.value = false), 2000);
};

// ─── Export: Markdown (Discord-friendly) ───
const exportAsMarkdown = () => {
    if (!selectedMasterTitle.value || crew.value.length === 0) return;
    const leader = crew.value.find((m) => m.hiringCategory === 'leader');
    const activeUpgrade = leader?.character.crew_upgrades?.find((u: any) => u.id === activeCrewUpgradeId.value);
    const baseUrl = window.location.origin;
    const lines: string[] = [];
    lines.push(
        `**${selectedMasterTitle.value.display_name}** (${Object.values(props.factions).find((f) => f.slug === selectedFaction.value)?.name ?? selectedFaction.value})`,
    );
    if (activeUpgrade) lines.push(`*Crew Card: ${activeUpgrade.name}*`);
    lines.push('');
    const categories = [
        { key: 'leader', label: 'Leader' },
        { key: 'totem', label: 'Totem(s)' },
        { key: 'in-keyword', label: 'Hires' },
        { key: 'versatile', label: 'Versatile' },
        { key: 'ook', label: 'Out of Keyword' },
    ];
    for (const cat of categories) {
        const members = crew.value.filter((m) => m.hiringCategory === cat.key);
        if (!members.length) continue;
        lines.push(`**${cat.label}:**`);
        for (const m of members) {
            const mini = m.miniature ?? m.character.miniatures?.[0];
            const url = mini?.slug ? `${baseUrl}/miniatures/${mini.slug}` : `${baseUrl}/characters/${m.character.slug}`;
            lines.push(`- [${m.character.display_name}](${url})`);
        }
    }
    navigator.clipboard.writeText(lines.join('\n'));
    shareTooltip.value = true;
    setTimeout(() => (shareTooltip.value = false), 2000);
};

// ─── Import from text ───
const importDialogOpen = ref(false);
const importText = ref('');
const importError = ref('');

const parseAndImportCrew = () => {
    importError.value = '';
    const text = importText.value.trim();
    if (!text) {
        importError.value = 'Paste a crew list to import.';
        return;
    }

    const lines = text
        .split('\n')
        .map((l) => l.trim())
        .filter(Boolean);
    if (lines.length < 2) {
        importError.value = 'Not enough lines to parse.';
        return;
    }

    // First line: "Master Name, Title (Faction)"
    const headerMatch = lines[0].match(/^(.+?)\s*\((.+?)\)\s*$/);
    if (!headerMatch) {
        importError.value = 'First line should be: Master Name, Title (Faction)';
        return;
    }
    const masterTitle = headerMatch[1].trim();
    const factionName = headerMatch[2].trim();

    // Find the faction (normalize by stripping apostrophes/special chars for comparison)
    const normalizeName = (s: string) => s.toLowerCase().replace(/['']/g, '').replace(/\s+/g, ' ').trim();
    const faction = Object.values(props.factions).find((f) => normalizeName(f.name) === normalizeName(factionName));
    if (!faction) {
        importError.value = `Unknown faction: ${factionName}`;
        return;
    }
    const factionSlug = faction.slug;

    // Find the master by display_name match
    const allChars = props.characters;
    const masterChar = allChars.find((c) => c.display_name?.toLowerCase() === masterTitle.toLowerCase() && c.station === 'master');
    if (!masterChar) {
        importError.value = `Master not found: ${masterTitle}`;
        return;
    }

    // Get totem name(s) to skip (auto-added with master)
    const totemId = masterChar.has_totem_id;
    const totemName = totemId ? allChars.find((c) => c.id === totemId)?.display_name?.toLowerCase() : null;

    // Parse crew members (skip header, labels, master, and totems)
    const crewNames: string[] = [];
    let inTotemSection = false;
    for (let i = 1; i < lines.length; i++) {
        const line = lines[i];
        if (line.match(/^(Leader|Hires|Versatile|Out of Keyword):?\s*$/i)) {
            inTotemSection = false;
            continue;
        }
        if (line.match(/^Totem\(?s?\)?:?\s*$/i)) {
            inTotemSection = true;
            continue;
        }
        if (line.match(/^Crew Card:/i)) continue;
        // Skip the master itself (leader, added automatically)
        if (line.toLowerCase() === masterTitle.toLowerCase()) continue;
        // Skip totems (added automatically with master)
        if (inTotemSection) continue;
        if (totemName && line.toLowerCase() === totemName) continue;
        crewNames.push(line);
    }

    // Find characters by display_name
    const crewChars: CharacterData[] = [];
    const notFound: string[] = [];
    for (const name of crewNames) {
        const char = allChars.find((c) => c.display_name?.toLowerCase() === name.toLowerCase());
        if (char) {
            crewChars.push(char);
        } else {
            notFound.push(name);
        }
    }

    // Build the crew — reset state and rebuild
    resetBuildState();
    selectedFaction.value = factionSlug;
    nextTick(() => {
        selectedMasterName.value = masterChar.name;
        nextTick(() => {
            selectedMasterTitle.value = masterChar;
            nextTick(() => {
                for (const char of crewChars) {
                    const poolChar = hiringPool.value.find((c) => c.id === char.id);
                    if (poolChar) {
                        addToCrewById(poolChar);
                    }
                }
                crewName.value = `Imported ${masterChar.display_name} Crew`;
                viewMode.value = 'editor';
                if (notFound.length) {
                    importError.value = `Imported! Could not find: ${notFound.join(', ')}`;
                } else {
                    importDialogOpen.value = false;
                    importText.value = '';
                }
            });
        });
    });
};

const openQuickRef = () => {
    if (currentShareCode.value) {
        window.open(route('tools.crew_builder.quick_ref', currentShareCode.value), '_blank');
    }
};

const printCrewPDF = () => {
    if (crew.value.length === 0) return;

    const cards: Array<{ card_type: string; id: number }> = [];
    const addedMiniIds = new Set<number>();
    const addedUpgradeIds = new Set<number>();

    // Crew member cards + crew upgrades
    for (const member of crew.value) {
        const mini = member.miniature ?? member.character.miniatures?.[0];
        if (mini && !addedMiniIds.has(mini.id)) {
            cards.push({ card_type: 'miniature', id: mini.id });
            addedMiniIds.add(mini.id);
        }

        if (member.hiringCategory === 'leader') {
            for (const upgrade of member.character.crew_upgrades ?? []) {
                if (!addedUpgradeIds.has(upgrade.id)) {
                    cards.push({ card_type: 'upgrade', id: upgrade.id });
                    addedUpgradeIds.add(upgrade.id);
                }
            }
        }
    }

    // Reference character cards (summons, replaces into, etc.)
    if (references.value?.characters?.length) {
        for (const char of references.value.characters) {
            const refMini = char.miniatures?.[0];
            if (refMini && !addedMiniIds.has(refMini.id)) {
                // Reference miniatures use raw paths (no /storage/ prefix), need the DB miniature ID
                // Use the character's first miniature ID from the references data
                cards.push({ card_type: 'miniature', id: refMini.id });
                addedMiniIds.add(refMini.id);
            }
        }
    }

    // Reference upgrade cards (character upgrades from references)
    if (references.value?.upgrades?.length) {
        for (const upgrade of references.value.upgrades) {
            if (!addedUpgradeIds.has(upgrade.id)) {
                cards.push({ card_type: 'upgrade', id: upgrade.id });
                addedUpgradeIds.add(upgrade.id);
            }
        }
    }

    if (cards.length === 0) return;

    const options = { separate_images: false };
    window.open(route('tools.pdf.download', { cards: btoa(JSON.stringify(cards)), options: btoa(JSON.stringify(options)) }), '_blank');
};

const startNewBuild = () => {
    resetBuildState();
    viewMode.value = 'editor';
    syncUrlToState(true);
};

// ─── Init ───

// ─── Init ───
onMounted(() => {
    window.addEventListener('popstate', onPopState);

    const params = new URLSearchParams(window.location.search);
    const hasParams = params.has('build') || params.has('crew') || params.has('step');

    if (hasParams) {
        restoreFromUrl();
    }
});

onUnmounted(() => {
    window.removeEventListener('popstate', onPopState);
    if (saveDebounceTimer.value) {
        clearTimeout(saveDebounceTimer.value);
        saveDebounceTimer.value = null;
    }
});
</script>

<template>
    <Head title="Crew Builder" />

    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner v-if="viewMode === 'builds' || editorStep !== 'hiring'" title="Crew Builder">
            <template #subtitle>
                <div class="flex items-center gap-3 px-2">
                    <span class="text-sm text-muted-foreground">Build your crew for Malifaux encounters.</span>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto sm:px-4 lg:px-6" :class="viewMode === 'builds' || editorStep !== 'hiring' ? 'mt-6' : 'mt-4'">
            <!-- ═══════════════════════════════════════════ -->
            <!-- BUILDS LIST (authenticated landing page)   -->
            <!-- ═══════════════════════════════════════════ -->
            <div v-if="viewMode === 'builds'">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">My Crews</h2>
                    <div class="flex items-center gap-2">
                        <Button variant="outline" size="sm" class="gap-1.5" @click="importDialogOpen = true">
                            <Upload class="size-3.5" />
                            Import
                        </Button>
                        <Button size="sm" class="gap-1.5" @click="startNewBuild">
                            <Plus class="size-4" />
                            New Crew
                        </Button>
                    </div>
                </div>

                <Tabs v-model="buildsTab" default-value="active">
                    <TabsList>
                        <TabsTrigger value="active">
                            Active
                            <Badge v-if="activeBuilds.length" variant="secondary" class="ml-1.5 px-1.5 py-0 text-[10px]">
                                {{ activeBuilds.length }}
                            </Badge>
                        </TabsTrigger>
                        <TabsTrigger value="archived">
                            Archived
                            <Badge v-if="archivedBuilds.length" variant="secondary" class="ml-1.5 px-1.5 py-0 text-[10px]">
                                {{ archivedBuilds.length }}
                            </Badge>
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="active" class="mt-4">
                        <div v-if="activeBuilds.length === 0" class="py-12 text-center text-sm text-muted-foreground">
                            No active crews yet. Create your first one!
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <Card v-for="build in activeBuilds" :key="build.id" class="group transition-colors hover:bg-accent/30">
                                <CardContent class="p-4">
                                    <div class="flex items-start gap-3">
                                        <img
                                            v-if="factions[build.faction]"
                                            :src="factions[build.faction].logo"
                                            :alt="factions[build.faction].name + ' logo'"
                                            class="mt-0.5 size-8 shrink-0"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <div class="truncate font-semibold">{{ build.name }}</div>
                                            <div class="text-xs text-muted-foreground">
                                                {{ characterById.get(build.master_id)?.display_name ?? 'Unknown Master' }}
                                            </div>
                                            <div class="mt-1 flex items-center gap-2 text-xs text-muted-foreground">
                                                <span class="flex items-center gap-0.5">
                                                    {{ build.encounter_size }}
                                                    <GameIcon type="soulstone" class-name="h-3 inline-block" />
                                                </span>
                                                <span>{{ (build.crew_data?.length ?? 0) + 1 }} models</span>
                                            </div>
                                            <div v-if="build.copied_from" class="mt-1 text-xs text-muted-foreground">
                                                Copied from
                                                <a
                                                    v-if="build.copied_from.is_public"
                                                    :href="route('tools.crew_builder.share', build.copied_from.share_code)"
                                                    class="font-medium text-primary hover:underline"
                                                    >{{ build.copied_from.name }}</a
                                                >
                                                <span v-else class="font-medium">{{ build.copied_from.name }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <Separator class="my-3" />
                                    <div class="flex items-center gap-1">
                                        <Button variant="ghost" size="sm" class="h-7 gap-1 text-xs" @click="loadBuild(build)">
                                            <Pencil class="size-3" />
                                            Edit
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 gap-1 text-xs"
                                            :disabled="!build.is_public"
                                            :title="build.is_public ? 'Copy share link' : 'Make public to share'"
                                            @click="copyShareLinkForBuild(build)"
                                        >
                                            <Copy class="size-3" />
                                            Share
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 gap-1 text-xs"
                                            :title="build.is_public ? 'Public — click to make private' : 'Private — click to make public'"
                                            @click="togglePublic(build)"
                                        >
                                            <Globe v-if="build.is_public" class="size-3" />
                                            <Lock v-else class="size-3" />
                                            {{ build.is_public ? 'Public' : 'Private' }}
                                        </Button>
                                        <Button variant="ghost" size="sm" class="h-7 gap-1 text-xs" @click="toggleArchive(build)">
                                            <Archive class="size-3" />
                                            Archive
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="ml-auto h-7 gap-1 text-xs text-destructive hover:text-destructive"
                                            @click="deleteTarget = build"
                                        >
                                            <Trash2 class="size-3" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    <TabsContent value="archived" class="mt-4">
                        <div v-if="archivedBuilds.length === 0" class="py-12 text-center text-sm text-muted-foreground">No archived crews.</div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <Card
                                v-for="build in archivedBuilds"
                                :key="build.id"
                                class="opacity-75 transition-colors hover:bg-accent/30 hover:opacity-100"
                            >
                                <CardContent class="p-4">
                                    <div class="flex items-start gap-3">
                                        <img
                                            v-if="factions[build.faction]"
                                            :src="factions[build.faction].logo"
                                            class="mt-0.5 size-8 shrink-0 grayscale"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <div class="truncate font-semibold">{{ build.name }}</div>
                                            <div class="text-xs text-muted-foreground">
                                                {{ characterById.get(build.master_id)?.display_name ?? 'Unknown Master' }}
                                            </div>
                                            <div class="mt-1 flex items-center gap-2 text-xs text-muted-foreground">
                                                <span class="flex items-center gap-0.5">
                                                    {{ build.encounter_size }}
                                                    <GameIcon type="soulstone" class-name="h-3 inline-block" />
                                                </span>
                                                <span>{{ (build.crew_data?.length ?? 0) + 1 }} models</span>
                                            </div>
                                            <div v-if="build.copied_from" class="mt-1 text-xs text-muted-foreground">
                                                Copied from
                                                <a
                                                    v-if="build.copied_from.is_public"
                                                    :href="route('tools.crew_builder.share', build.copied_from.share_code)"
                                                    class="font-medium text-primary hover:underline"
                                                    >{{ build.copied_from.name }}</a
                                                >
                                                <span v-else class="font-medium">{{ build.copied_from.name }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <Separator class="my-3" />
                                    <div class="flex items-center gap-1">
                                        <Button variant="ghost" size="sm" class="h-7 gap-1 text-xs" @click="loadBuild(build)">
                                            <Pencil class="size-3" />
                                            Edit
                                        </Button>
                                        <Button variant="ghost" size="sm" class="h-7 gap-1 text-xs" @click="toggleArchive(build)">
                                            <ArchiveRestore class="size-3" />
                                            Restore
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="ml-auto h-7 gap-1 text-xs text-destructive hover:text-destructive"
                                            @click="deleteTarget = build"
                                        >
                                            <Trash2 class="size-3" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>
                </Tabs>
            </div>

            <!-- ═══════════════════════════════════════════ -->
            <!-- EDITOR (crew building)                     -->
            <!-- ═══════════════════════════════════════════ -->
            <!-- Return to Game banner -->
            <div v-if="fromGameUuid" class="mb-3 flex items-center justify-between rounded-lg border border-primary/30 bg-primary/5 px-3 py-2">
                <span class="text-xs text-muted-foreground">Building crew for Game Tracker</span>
                <Link :href="route('games.show', fromGameUuid)" class="flex items-center gap-1 text-xs font-medium text-primary hover:underline">
                    <ArrowLeft class="size-3" /> Return to Game
                </Link>
            </div>

            <div v-if="viewMode === 'editor'">
                <!-- ═══════ Steps 1-3: Selection Flow ═══════ -->
                <div v-if="editorStep !== 'hiring'">
                    <!-- Navigation + encounter size -->
                    <div class="mb-6 flex items-center justify-between">
                        <Button v-if="editorStep !== 'faction' || isAuthenticated" variant="ghost" size="sm" class="gap-1.5" @click="goBack">
                            <ArrowLeft class="size-4" />
                            {{ editorStep === 'faction' ? 'My Builds' : 'Back' }}
                        </Button>
                        <div v-else />

                        <div class="flex items-center gap-1.5">
                            <GameIcon type="soulstone" class-name="h-4 inline-block" />
                            <NumberField id="encounter_size" v-model="encounterSize" :min="1" class="w-24">
                                <NumberFieldContent>
                                    <NumberFieldDecrement />
                                    <NumberFieldInput />
                                    <NumberFieldIncrement />
                                </NumberFieldContent>
                            </NumberField>
                        </div>
                    </div>

                    <!-- Step 1: Faction -->
                    <div v-if="editorStep === 'faction'">
                        <h2 class="mb-4 text-center text-lg font-semibold">Choose Your Faction</h2>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <button
                                v-for="faction in factions"
                                :key="faction.slug"
                                @click="selectFaction(faction.slug)"
                                class="flex flex-col items-center gap-2 rounded-lg border-2 border-transparent p-4 transition-all hover:border-primary/50 hover:bg-accent"
                            >
                                <img :src="faction.logo" :alt="faction.name" class="size-16" />
                                <span class="text-sm font-medium">{{ faction.name }}</span>
                            </button>
                        </div>
                        <div class="mt-6 flex justify-center">
                            <Button variant="outline" size="sm" class="gap-1.5 text-xs" @click="importDialogOpen = true">
                                <Upload class="size-3.5" />
                                Import Crew from Text
                            </Button>
                        </div>
                    </div>

                    <!-- Step 2: Master Name -->
                    <div v-if="editorStep === 'master-name'">
                        <div class="mb-4 flex items-center justify-center gap-3">
                            <img :src="factions[selectedFaction!].logo" :alt="factions[selectedFaction!].name" class="size-8" />
                            <h2 class="text-lg font-semibold">Choose Your Master</h2>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <Card
                                v-for="info in masterNameDetails"
                                :key="info.name"
                                class="cursor-pointer transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md hover:ring-1 hover:ring-primary/50"
                                @click="selectMasterName(info.name)"
                            >
                                <CardContent class="flex items-start gap-3 p-4">
                                    <div
                                        v-if="info.miniature?.front_image"
                                        class="shrink-0 overflow-hidden rounded-md"
                                        @click.stop="openCardPreview(info.titles[0], info.miniature)"
                                    >
                                        <img
                                            :src="'/storage/' + info.miniature.front_image"
                                            :alt="info.name"
                                            class="size-20 object-cover object-top transition-transform hover:scale-105"
                                        />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-semibold">{{ info.name }}</span>
                                            <Badge
                                                v-if="info.isAlternateLeader"
                                                variant="outline"
                                                class="border-cyan-500/50 px-1 py-0 text-[9px] text-cyan-600 dark:text-cyan-400"
                                            >
                                                Alt. Leader
                                            </Badge>
                                        </div>
                                        <div class="mt-1.5 flex flex-wrap gap-1">
                                            <Badge v-for="kw in info.keywords" :key="kw.slug" variant="secondary" class="px-1.5 py-0.5 text-xs">
                                                {{ kw.name }}
                                            </Badge>
                                        </div>
                                        <div class="mt-2 flex flex-wrap gap-1">
                                            <Badge
                                                v-for="title in info.titles"
                                                :key="title.id"
                                                variant="outline"
                                                class="cursor-pointer px-1.5 py-0.5 text-xs hover:bg-accent"
                                                @click.stop="openCardPreview(title, title.miniatures?.[0])"
                                            >
                                                {{ title.title || 'Base' }}
                                            </Badge>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    <!-- Step 3: Master Title -->
                    <div v-if="editorStep === 'master-title'">
                        <div class="mb-4 flex items-center justify-center gap-3">
                            <img :src="factions[selectedFaction!].logo" :alt="factions[selectedFaction!].name" class="size-8" />
                            <h2 class="text-lg font-semibold">Choose Title for {{ selectedMasterName }}</h2>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <Card
                                v-for="master in masterTitleVariants"
                                :key="master.id"
                                class="cursor-pointer transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md hover:ring-1 hover:ring-primary/50"
                                @click="selectMasterTitle(master)"
                            >
                                <CardContent class="flex items-start gap-3 p-4">
                                    <div
                                        v-if="master.miniatures?.[0]?.front_image"
                                        class="shrink-0 overflow-hidden rounded-md"
                                        @click.stop="openCardPreview(master, master.miniatures[0])"
                                    >
                                        <img
                                            :src="'/storage/' + master.miniatures[0].front_image"
                                            :alt="master.display_name"
                                            class="size-20 object-cover object-top transition-transform hover:scale-105"
                                        />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold">{{ master.display_name }}</div>
                                        <div class="mt-1.5 flex flex-wrap gap-1">
                                            <Badge v-for="kw in master.keywords" :key="kw.slug" variant="secondary" class="px-1.5 py-0.5 text-xs">
                                                {{ kw.name }}
                                            </Badge>
                                        </div>
                                        <div v-if="master.crew_upgrades?.length" class="mt-2 flex flex-wrap gap-1">
                                            <Badge
                                                v-for="upgrade in master.crew_upgrades"
                                                :key="upgrade.id"
                                                variant="outline"
                                                class="cursor-pointer px-1.5 py-0.5 text-xs hover:bg-accent"
                                                @click.stop="openUpgradePreview(upgrade)"
                                            >
                                                <Star class="mr-0.5 size-2.5 text-amber-500" />
                                                {{ upgrade.name }}
                                            </Badge>
                                        </div>
                                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                            <span>HP {{ master.health }}</span>
                                            <span>Spd {{ master.speed }}</span>
                                            <span>Def {{ master.defense }}</span>
                                            <span>Wp {{ master.willpower }}</span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>

                <!-- ═══════ Step 4: Hiring ═══════ -->
                <div v-if="editorStep === 'hiring'">
                    <!-- Crew header card -->
                    <Card class="mb-4">
                        <CardContent class="p-3 sm:p-4">
                            <!-- Row 1: Back, faction logo, crew name, encounter size -->
                            <div class="flex items-center gap-2">
                                <Button variant="ghost" size="icon" class="size-8 shrink-0" @click="goBack">
                                    <ArrowLeft class="size-4" />
                                </Button>
                                <img
                                    v-if="selectedFaction && factions[selectedFaction]"
                                    :src="factions[selectedFaction].logo"
                                    :alt="factions[selectedFaction].name"
                                    class="size-7 shrink-0"
                                />
                                <Input v-model="crewName" placeholder="Crew name..." class="min-w-0 flex-1" />
                                <div class="flex shrink-0 items-center gap-1">
                                    <GameIcon type="soulstone" class-name="h-4 inline-block" />
                                    <NumberField id="encounter_size" v-model="encounterSize" :min="1" class="w-20">
                                        <NumberFieldContent>
                                            <NumberFieldDecrement />
                                            <NumberFieldInput />
                                            <NumberFieldIncrement />
                                        </NumberFieldContent>
                                    </NumberField>
                                </div>
                            </div>

                            <!-- Overhire toggle -->
                            <div class="mt-2">
                                <label class="flex cursor-pointer items-center gap-2 text-xs text-muted-foreground">
                                    <Checkbox :checked="allowOverhire" @update:checked="(v: boolean) => (allowOverhire = v)" />
                                    Allow over-hiring
                                </label>
                            </div>

                            <!-- Row 2: Title switcher (if multiple titles) -->
                            <div v-if="masterTitleVariants.length > 1" class="mt-2 flex flex-wrap gap-1.5">
                                <Button
                                    v-for="master in masterTitleVariants"
                                    :key="master.id"
                                    :variant="selectedMasterTitle?.id === master.id ? 'default' : 'outline'"
                                    size="sm"
                                    class="h-7 text-xs"
                                    @click="swapMasterTitle(master)"
                                >
                                    {{ master.title || master.name }}
                                </Button>
                            </div>

                            <Separator class="my-2" />

                            <!-- Row 3: Actions + status -->
                            <div class="flex items-center gap-1.5">
                                <Button
                                    v-if="isAuthenticated"
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 gap-1 text-xs"
                                    @click="saveBuild"
                                    :disabled="isSaving"
                                >
                                    <Save class="size-3.5" />
                                    Save
                                </Button>
                                <Button
                                    v-if="isAuthenticated && currentBuildId"
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 gap-1 text-xs"
                                    :title="currentBuildIsPublic ? 'Public — click to make private' : 'Private — click to make public'"
                                    @click="toggleCurrentBuildPublic"
                                >
                                    <Globe v-if="currentBuildIsPublic" class="size-3.5" />
                                    <Lock v-else class="size-3.5" />
                                    {{ currentBuildIsPublic ? 'Public' : 'Private' }}
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 gap-1 text-xs"
                                    :disabled="isAuthenticated && !currentBuildIsPublic"
                                    :title="isAuthenticated && !currentBuildIsPublic ? 'Make public to share' : 'Copy share link'"
                                    @click="generateShareLink"
                                >
                                    <Copy class="size-3.5" />
                                    Share
                                </Button>
                                <Button variant="ghost" size="sm" class="h-7 gap-1 text-xs" @click="showDescriptionEditor = !showDescriptionEditor">
                                    <FileText class="size-3.5" />
                                    {{ showDescriptionEditor ? 'Hide' : '' }} Notes
                                </Button>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="sm" class="h-7 gap-1 text-xs">
                                            <Download class="size-3.5" />
                                            Export
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem :disabled="crew.length === 0" class="cursor-pointer text-xs" @click="exportAsText">
                                            <Copy class="mr-2 size-3.5" /> Copy as Text
                                        </DropdownMenuItem>
                                        <DropdownMenuItem :disabled="crew.length === 0" class="cursor-pointer text-xs" @click="exportAsMarkdown">
                                            <FileText class="mr-2 size-3.5" /> Copy as Markdown
                                        </DropdownMenuItem>
                                        <DropdownMenuItem :disabled="crew.length === 0" class="cursor-pointer text-xs" @click="printCrewPDF">
                                            <Printer class="mr-2 size-3.5" /> Export PDF (with References)
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            :disabled="!currentShareCode"
                                            class="cursor-pointer text-xs"
                                            @click="currentShareCode && openQuickRef()"
                                        >
                                            <Printer class="mr-2 size-3.5" /> Quick Reference Sheet
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>

                                <div class="ml-auto flex items-center gap-2">
                                    <span v-if="saveError" class="text-xs text-destructive">{{ saveError }}</span>
                                    <span v-if="lastSavedAt && isAuthenticated" class="hidden text-xs text-muted-foreground sm:inline">
                                        Saved {{ lastSavedAt }}
                                    </span>
                                    <Loader2 v-if="isSaving" class="size-4 animate-spin text-muted-foreground" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Description editor (collapsible) -->
                    <div v-if="showDescriptionEditor" class="mb-4">
                        <TipTapEditor v-model="crewDescription" />
                    </div>

                    <!-- Mobile: Stats + Tabbed layout -->
                    <div v-if="!isFixedCrew && isMobile">
                        <!-- Over budget warning -->
                        <div
                            v-if="isOverBudget"
                            class="mb-2 flex items-center gap-2 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-1.5 text-xs font-medium text-destructive"
                        >
                            <ShieldAlert class="size-4 shrink-0" />
                            Over budget by {{ Math.abs(remaining) }}<GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                        </div>

                        <!-- Stats bar -->
                        <div class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                            <div class="flex items-center gap-1">
                                <span class="text-muted-foreground">Spent:</span>
                                <span class="font-medium" :class="totalSpent > encounterSize ? 'text-destructive' : ''">
                                    {{ totalSpent }} / {{ encounterSize }}
                                </span>
                                <GameIcon type="soulstone" class-name="h-4 inline-block" />
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="text-muted-foreground">Pool:</span>
                                <span class="font-medium">{{ soulstonePool }}</span>
                                <GameIcon type="soulstone" class-name="h-4 inline-block" />
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="text-muted-foreground">OOK:</span>
                                <span class="font-medium" :class="ookCount >= 2 ? 'text-amber-600 dark:text-amber-400' : ''">
                                    {{ ookCount }} / 2
                                </span>
                            </div>
                        </div>

                        <!-- Crew Stats Panel -->
                        <div v-if="crewStats" class="mb-3 rounded-md border border-border/50 bg-accent/30 p-2">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5">
                                <div class="text-center">
                                    <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Models</div>
                                    <div class="text-sm font-bold leading-tight">{{ crewStats.models }}</div>
                                </div>
                                <div v-if="crewStats.avgCost != null" class="text-center">
                                    <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Avg Cost</div>
                                    <div class="text-sm font-bold leading-tight">{{ crewStats.avgCost }}</div>
                                </div>
                                <div v-if="crewStats.avgHealth != null" class="text-center">
                                    <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Avg HP</div>
                                    <div class="text-sm font-bold leading-tight">{{ crewStats.avgHealth }}</div>
                                </div>
                                <div v-if="crewStats.avgSpeed != null" class="text-center">
                                    <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Avg Spd</div>
                                    <div class="text-sm font-bold leading-tight">{{ crewStats.avgSpeed }}</div>
                                </div>
                                <div v-if="crewStats.avgDefense != null" class="text-center">
                                    <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Avg Def</div>
                                    <div class="text-sm font-bold leading-tight">{{ crewStats.avgDefense }}</div>
                                </div>
                                <div v-if="crewStats.avgWillpower != null" class="text-center">
                                    <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Avg Wp</div>
                                    <div class="text-sm font-bold leading-tight">{{ crewStats.avgWillpower }}</div>
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="crewStats && Object.keys(crewStats.suitCounts).length"
                            class="mb-3 rounded-md border border-border/50 bg-accent/30 p-2"
                        >
                            <div class="mb-1 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Trigger Counts</div>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                <div
                                    v-for="suit in ['crow', 'mask', 'ram', 'tome', 'soulstone'].filter((s) => crewStats!.suitCounts[s])"
                                    :key="suit"
                                    class="text-center"
                                >
                                    <GameIcon :type="suit" class-name="mx-auto h-4" />
                                    <div class="text-sm font-bold leading-tight">{{ crewStats.suitCounts[suit] }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabs: Crew / Hiring Pool -->
                        <Tabs v-model="mobileBuilderTab" class="w-full">
                            <TabsList class="mb-3 grid w-full grid-cols-2">
                                <TabsTrigger value="crew">
                                    Crew
                                    <Badge v-if="crew.length" variant="secondary" class="ml-1.5 px-1.5 py-0 text-[10px]">
                                        {{ crew.length }}
                                    </Badge>
                                </TabsTrigger>
                                <TabsTrigger value="hiring">Hiring Pool</TabsTrigger>
                            </TabsList>
                            <TabsContent value="hiring">
                                <Card>
                                    <CardContent class="p-2">
                                        <div class="mb-3 flex items-center gap-2">
                                            <div class="relative flex-1">
                                                <Search class="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                                <Input v-model="filterText" placeholder="Search models..." class="pl-9" />
                                            </div>
                                            <CircleX v-if="filterText" class="shrink-0 cursor-pointer text-destructive" @click="filterText = ''" />
                                        </div>

                                        <div class="mb-2 flex flex-wrap items-center gap-1">
                                            <Button
                                                v-for="f in ['in-keyword', 'versatile', 'ook', 'all'] as const"
                                                :key="f"
                                                :variant="poolFilter === f ? 'default' : 'outline'"
                                                size="sm"
                                                class="h-6 gap-1 px-2 text-[11px]"
                                                @click="poolFilter = f"
                                            >
                                                {{ { 'in-keyword': 'Keyword', versatile: 'Versatile', ook: 'OOK', all: 'All' }[f] }}
                                                <span class="text-[10px] opacity-60">{{ poolFilterCounts[f] }}</span>
                                            </Button>
                                            <Button
                                                v-if="ownedSet.size > 0"
                                                :variant="ownedOnly ? 'default' : 'outline'"
                                                size="sm"
                                                class="h-6 gap-1 px-2 text-[11px]"
                                                @click="ownedOnly = !ownedOnly"
                                            >
                                                Owned
                                            </Button>
                                            <span class="ml-auto text-xs text-muted-foreground">{{ augmentedPool.length }} shown</span>
                                        </div>
                                        <div class="mb-2 flex items-center gap-1">
                                            <span class="text-[11px] text-muted-foreground">Sort:</span>
                                            <Button
                                                v-for="s in ['station', 'name', 'cost'] as const"
                                                :key="s"
                                                :variant="poolSort === s ? 'default' : 'ghost'"
                                                size="sm"
                                                class="h-5 px-1.5 text-[10px]"
                                                @click="poolSort = s"
                                            >
                                                {{ { station: 'Station', name: 'Name', cost: 'Cost' }[s] }}
                                            </Button>
                                        </div>

                                        <div ref="poolScrollRef" class="h-[60vh] overflow-y-auto">
                                            <div
                                                :style="{
                                                    height: `${poolVirtualizer.getTotalSize()}px`,
                                                    position: 'relative',
                                                    width: '100%',
                                                }"
                                            >
                                                <div
                                                    v-for="virtualRow in poolVirtualizer.getVirtualItems()"
                                                    :key="virtualRow.key"
                                                    :data-index="virtualRow.index"
                                                    :ref="
                                                        (el) => {
                                                            if (el) poolVirtualizer.measureElement(el as Element);
                                                        }
                                                    "
                                                    :style="{
                                                        position: 'absolute',
                                                        top: 0,
                                                        left: 0,
                                                        width: '100%',
                                                        transform: `translateY(${virtualRow.start}px)`,
                                                    }"
                                                >
                                                    <div
                                                        v-if="augmentedPool[virtualRow.index]"
                                                        :class="[
                                                            factionBackground(augmentedPool[virtualRow.index].character.faction),
                                                            !augmentedPool[virtualRow.index].hireCheck.allowed ? 'opacity-40' : '',
                                                        ]"
                                                        class="my-0.5 flex items-center justify-between rounded-md border border-white/20 px-2 py-1.5 text-white transition-colors hover:brightness-110"
                                                    >
                                                        <div
                                                            class="min-w-0 flex-1 cursor-pointer"
                                                            @click="openCardPreview(augmentedPool[virtualRow.index].character)"
                                                        >
                                                            <div class="flex items-center gap-1.5 text-sm font-semibold">
                                                                {{ augmentedPool[virtualRow.index].character.display_name }}
                                                                <Badge
                                                                    v-if="augmentedPool[virtualRow.index].character.miniatures?.length > 1"
                                                                    variant="outline"
                                                                    class="border-white/30 px-1 py-0 text-[9px] font-normal text-white/80"
                                                                >
                                                                    {{ augmentedPool[virtualRow.index].character.miniatures.length }} sculpts
                                                                </Badge>
                                                            </div>
                                                            <div class="flex flex-wrap items-center gap-1.5 text-xs text-white/70">
                                                                <span class="flex items-center text-sm font-bold text-white">
                                                                    {{ augmentedPool[virtualRow.index].effectiveCost
                                                                    }}<GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                                                    <span
                                                                        v-if="augmentedPool[virtualRow.index].category === 'ook'"
                                                                        class="text-xs font-normal text-red-300"
                                                                        >({{ augmentedPool[virtualRow.index].character.cost }}+1)</span
                                                                    >
                                                                </span>
                                                                <Badge
                                                                    variant="secondary"
                                                                    class="bg-white/15 px-1 py-0 text-[10px] capitalize text-white/90"
                                                                >
                                                                    {{ augmentedPool[virtualRow.index].character.station }}
                                                                    <span v-if="augmentedPool[virtualRow.index].character.count > 1">
                                                                        ({{ hiredCountOf(augmentedPool[virtualRow.index].character.id) }}/{{
                                                                            augmentedPool[virtualRow.index].character.count
                                                                        }})
                                                                    </span>
                                                                </Badge>
                                                                <Badge
                                                                    :class="categoryColor(augmentedPool[virtualRow.index].category)"
                                                                    class="px-1 py-0 text-[10px]"
                                                                >
                                                                    {{ categoryLabel(augmentedPool[virtualRow.index].category) }}
                                                                </Badge>
                                                                <Badge
                                                                    v-if="augmentedPool[virtualRow.index].henchman"
                                                                    class="bg-amber-400/20 px-1 py-0 text-[10px] text-amber-200"
                                                                >
                                                                    Henchman
                                                                </Badge>
                                                                <Badge
                                                                    v-if="augmentedPool[virtualRow.index].unique"
                                                                    class="bg-cyan-400/20 px-1 py-0 text-[10px] text-cyan-200"
                                                                >
                                                                    Unique
                                                                </Badge>
                                                                <span class="hidden truncate text-white/50 sm:inline">
                                                                    {{
                                                                        augmentedPool[virtualRow.index].character.keywords
                                                                            .map((k: Keyword) => k.name)
                                                                            .join(', ')
                                                                    }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="flex shrink-0 items-center gap-1">
                                                            <span
                                                                v-if="!augmentedPool[virtualRow.index].hireCheck.allowed"
                                                                class="text-[10px] text-white/50"
                                                            >
                                                                {{ augmentedPool[virtualRow.index].hireCheck.reason }}
                                                            </span>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                class="size-7 text-white hover:bg-white/10 hover:text-white"
                                                                :disabled="!augmentedPool[virtualRow.index].hireCheck.allowed"
                                                                @click.stop="addToCrewById(augmentedPool[virtualRow.index].character)"
                                                            >
                                                                <UserPlus class="size-4" />
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Custom Characters Section (Mobile) -->
                                        <div v-if="customCharacters.length && selectedFaction" class="mt-3 border-t pt-3">
                                            <div class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                                Your Custom Characters
                                            </div>
                                            <div class="space-y-1">
                                                <div
                                                    v-for="cc in customCharacters.filter((c) => c.faction === selectedFaction)"
                                                    :key="'cc-mob-' + cc.id"
                                                    class="flex items-center justify-between rounded-md bg-purple-500/10 px-2 py-1.5"
                                                >
                                                    <div class="flex min-w-0 items-center gap-2">
                                                        <Badge class="shrink-0 bg-purple-600 px-1 py-0 text-[8px] text-white">Custom</Badge>
                                                        <span class="truncate text-xs font-medium">{{ cc.display_name }}</span>
                                                        <span class="shrink-0 text-[10px] text-muted-foreground">{{ cc.cost ?? 0 }}ss</span>
                                                    </div>
                                                    <Button variant="ghost" size="icon" class="size-7" @click="addCustomToCrew(cc)">
                                                        <UserPlus class="size-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>
                            <TabsContent value="crew">
                                <Card>
                                    <CardContent class="p-2">
                                        <!-- Required Hires Notice -->
                                        <div
                                            v-if="requiredHires.length > 0 && activeUpgradeHiringRules?.required_characteristic"
                                            class="mb-3 flex items-center gap-2 rounded-md border border-orange-500/30 bg-orange-500/10 px-3 py-1.5 text-xs font-medium text-orange-700 dark:text-orange-400"
                                        >
                                            <Star class="size-3.5 shrink-0" />
                                            {{ requiredHires.length }} required {{ activeUpgradeHiringRules.required_characteristic }} models
                                            auto-added
                                        </div>

                                        <div class="mb-3 flex items-center justify-end">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="h-7 gap-1 text-xs text-destructive hover:text-destructive"
                                                @click="clearHiredModels"
                                            >
                                                <Trash2 class="size-3" />
                                                Clear Hired
                                            </Button>
                                        </div>

                                        <Separator class="mb-3" />

                                        <!-- Crew Cards -->
                                        <div v-if="selectedMasterTitle?.crew_upgrades?.length" class="mb-3 space-y-1">
                                            <div
                                                v-for="upgrade in selectedMasterTitle.crew_upgrades"
                                                :key="upgrade.id"
                                                class="flex items-center gap-1.5 rounded-md border px-2 py-1.5 transition-colors"
                                                :class="[
                                                    activeCrewUpgradeId === upgrade.id || hasSingleCrewUpgrade
                                                        ? 'border-amber-500/50 bg-amber-500/10'
                                                        : 'border-border/50 bg-accent/30 opacity-60',
                                                    upgrade.front_image ? 'cursor-pointer hover:bg-accent' : '',
                                                ]"
                                                @click="openUpgradePreview(upgrade)"
                                            >
                                                <button
                                                    v-if="!hasSingleCrewUpgrade"
                                                    class="shrink-0 rounded p-0.5 transition-colors hover:bg-accent"
                                                    :title="activeCrewUpgradeId === upgrade.id ? 'Deselect crew card' : 'Select as active crew card'"
                                                    @click.stop="toggleCrewUpgradeActive(upgrade)"
                                                >
                                                    <Star
                                                        class="size-3.5"
                                                        :class="
                                                            activeCrewUpgradeId === upgrade.id
                                                                ? 'fill-amber-500 text-amber-500'
                                                                : 'text-muted-foreground'
                                                        "
                                                    />
                                                </button>
                                                <Star v-else class="size-3.5 shrink-0 fill-amber-500 text-amber-500" />
                                                <div class="min-w-0 flex-1">
                                                    <div class="text-xs font-semibold">{{ upgrade.name }}</div>
                                                    <div class="text-[10px] text-muted-foreground">
                                                        {{
                                                            activeCrewUpgradeId === upgrade.id || hasSingleCrewUpgrade
                                                                ? 'Active Crew Card'
                                                                : 'Crew Card'
                                                        }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Crew list -->
                                        <div class="max-h-[50vh] space-y-0.5 overflow-y-auto">
                                            <div v-if="crew.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                                                Select a master to begin hiring
                                            </div>

                                            <div
                                                v-for="(member, index) in crew"
                                                :key="index"
                                                :class="factionBackground(member.character.faction)"
                                                class="rounded-md border border-white/20 px-2 py-1.5 text-white transition-colors hover:brightness-110"
                                            >
                                                <div class="flex items-center justify-between">
                                                    <div class="min-w-0 flex-1 cursor-pointer" @click="openCrewMemberPreview(index)">
                                                        <div class="flex items-center gap-1.5 text-sm font-semibold">
                                                            <TooltipProvider
                                                                v-if="
                                                                    member.hiringCategory === 'leader' ||
                                                                    member.hiringCategory === 'totem' ||
                                                                    member.hiringCategory === 'ook'
                                                                "
                                                            >
                                                                <Tooltip>
                                                                    <TooltipTrigger as-child>
                                                                        <Shield
                                                                            v-if="member.hiringCategory === 'leader'"
                                                                            class="size-3.5 shrink-0 text-amber-300"
                                                                        />
                                                                        <Swords
                                                                            v-if="member.hiringCategory === 'totem'"
                                                                            class="size-3.5 shrink-0 text-purple-300"
                                                                        />
                                                                        <ShieldAlert
                                                                            v-if="member.hiringCategory === 'ook'"
                                                                            class="size-3.5 shrink-0 text-red-300"
                                                                        />
                                                                    </TooltipTrigger>
                                                                    <TooltipContent side="top">
                                                                        <p class="text-xs">{{ categoryLabel(member.hiringCategory) }}</p>
                                                                    </TooltipContent>
                                                                </Tooltip>
                                                            </TooltipProvider>
                                                            <span class="truncate">{{
                                                                member.miniature?.display_name || member.character.display_name
                                                            }}</span>
                                                            <Badge
                                                                v-if="member.isCustom"
                                                                class="ml-1 shrink-0 bg-purple-600 px-1 py-0 text-[8px] text-white"
                                                                >Custom</Badge
                                                            >
                                                        </div>
                                                        <div class="flex items-center gap-1.5 text-xs text-white/70">
                                                            <span
                                                                v-if="member.hiringCategory === 'ook'"
                                                                class="flex items-center text-sm font-bold text-white"
                                                                >{{ member.effectiveCost
                                                                }}<GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                                                <span class="text-xs font-normal text-red-300"
                                                                    >({{ member.character.cost }}+1)</span
                                                                ></span
                                                            >
                                                            <span v-else class="flex items-center text-sm font-bold text-white"
                                                                >{{ member.effectiveCost
                                                                }}<GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block"
                                                            /></span>
                                                            <Badge :class="categoryColor(member.hiringCategory)" class="px-1 py-0 text-[10px]">
                                                                {{ categoryLabel(member.hiringCategory) }}
                                                            </Badge>
                                                        </div>
                                                    </div>
                                                    <Button
                                                        v-if="
                                                            member.hiringCategory !== 'leader' &&
                                                            member.hiringCategory !== 'totem' &&
                                                            member.hiringCategory !== 'fixed-crew' &&
                                                            member.hiringCategory !== 'required'
                                                        "
                                                        variant="ghost"
                                                        size="icon"
                                                        class="size-7 shrink-0 text-white hover:bg-white/10 hover:text-white"
                                                        @click="removeFromCrew(index)"
                                                    >
                                                        <UserMinus class="size-4 text-red-300" />
                                                    </Button>
                                                </div>
                                                <!-- Miniature version selector -->
                                                <div v-if="member.character.miniatures?.length > 1" class="mt-1">
                                                    <Select
                                                        :model-value="String(member.miniature?.id ?? '')"
                                                        @update:model-value="
                                                            (val: string) => {
                                                                member.miniature =
                                                                    member.character.miniatures.find((m) => m.id === Number(val)) ?? null;
                                                                triggerAutosave();
                                                            }
                                                        "
                                                    >
                                                        <SelectTrigger class="h-6 border-white/20 bg-white/10 text-[11px] text-white">
                                                            <SelectValue placeholder="Select miniature..." />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                v-for="mini in member.character.miniatures"
                                                                :key="mini.id"
                                                                :value="String(mini.id)"
                                                            >
                                                                {{ mini.display_name }}
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- References -->
                                        <CrewBuilderReferences
                                            :references="references"
                                            :loading="referencesLoading"
                                            editable
                                            @add-reference="addCustomReference"
                                        />
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>
                    </div>

                    <!-- Desktop: Side-by-side grid layout / Fixed crew: centered -->
                    <div v-if="!isMobile || isFixedCrew" :class="isFixedCrew ? 'mx-auto max-w-xl' : 'grid grid-cols-5 gap-4'">
                        <!-- Hiring Pool (left on desktop) -->
                        <div v-if="!isFixedCrew" class="md:col-span-3">
                            <Card>
                                <CardContent class="p-2 md:p-3">
                                    <div class="mb-3 flex items-center gap-2">
                                        <div class="relative flex-1">
                                            <Search class="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                            <Input v-model="filterText" placeholder="Search models..." class="pl-9" />
                                        </div>
                                        <CircleX v-if="filterText" class="shrink-0 cursor-pointer text-destructive" @click="filterText = ''" />
                                    </div>

                                    <div class="mb-2 flex flex-wrap items-center gap-1">
                                        <Button
                                            v-for="f in ['in-keyword', 'versatile', 'ook', 'all'] as const"
                                            :key="f"
                                            :variant="poolFilter === f ? 'default' : 'outline'"
                                            size="sm"
                                            class="h-6 gap-1 px-2 text-[11px]"
                                            @click="poolFilter = f"
                                        >
                                            {{ { 'in-keyword': 'Keyword', versatile: 'Versatile', ook: 'OOK', all: 'All' }[f] }}
                                            <span class="text-[10px] opacity-60">{{ poolFilterCounts[f] }}</span>
                                        </Button>
                                        <Button
                                            v-if="ownedSet.size > 0"
                                            :variant="ownedOnly ? 'default' : 'outline'"
                                            size="sm"
                                            class="h-6 gap-1 px-2 text-[11px]"
                                            @click="ownedOnly = !ownedOnly"
                                        >
                                            Owned
                                        </Button>
                                        <span class="ml-auto text-xs text-muted-foreground">{{ augmentedPool.length }} shown</span>
                                    </div>
                                    <div class="mb-2 flex items-center gap-1">
                                        <span class="text-[11px] text-muted-foreground">Sort:</span>
                                        <Button
                                            v-for="s in ['station', 'name', 'cost'] as const"
                                            :key="s"
                                            :variant="poolSort === s ? 'default' : 'ghost'"
                                            size="sm"
                                            class="h-5 px-1.5 text-[10px]"
                                            @click="poolSort = s"
                                        >
                                            {{ { station: 'Station', name: 'Name', cost: 'Cost' }[s] }}
                                        </Button>
                                    </div>

                                    <div ref="poolScrollRef" class="h-[calc(100vh-18rem)] overflow-y-auto">
                                        <div
                                            :style="{
                                                height: `${poolVirtualizer.getTotalSize()}px`,
                                                position: 'relative',
                                                width: '100%',
                                            }"
                                        >
                                            <div
                                                v-for="virtualRow in poolVirtualizer.getVirtualItems()"
                                                :key="virtualRow.key"
                                                :data-index="virtualRow.index"
                                                :ref="
                                                    (el) => {
                                                        if (el) poolVirtualizer.measureElement(el as Element);
                                                    }
                                                "
                                                :style="{
                                                    position: 'absolute',
                                                    top: 0,
                                                    left: 0,
                                                    width: '100%',
                                                    transform: `translateY(${virtualRow.start}px)`,
                                                }"
                                            >
                                                <div
                                                    v-if="augmentedPool[virtualRow.index]"
                                                    :class="[
                                                        factionBackground(augmentedPool[virtualRow.index].character.faction),
                                                        !augmentedPool[virtualRow.index].hireCheck.allowed ? 'opacity-40' : '',
                                                    ]"
                                                    class="my-0.5 flex items-center justify-between rounded-md border border-white/20 px-2 py-1.5 text-white transition-colors hover:brightness-110"
                                                >
                                                    <div
                                                        class="min-w-0 flex-1 cursor-pointer"
                                                        @click="openCardPreview(augmentedPool[virtualRow.index].character)"
                                                    >
                                                        <div class="flex items-center gap-1.5 text-sm font-semibold">
                                                            {{ augmentedPool[virtualRow.index].character.display_name }}
                                                            <Badge
                                                                v-if="augmentedPool[virtualRow.index].character.miniatures?.length > 1"
                                                                variant="outline"
                                                                class="border-white/30 px-1 py-0 text-[9px] font-normal text-white/80"
                                                            >
                                                                {{ augmentedPool[virtualRow.index].character.miniatures.length }} sculpts
                                                            </Badge>
                                                        </div>
                                                        <div class="flex flex-wrap items-center gap-1.5 text-xs text-white/70">
                                                            <span class="flex items-center text-sm font-bold text-white">
                                                                {{ augmentedPool[virtualRow.index].effectiveCost
                                                                }}<GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                                                <span
                                                                    v-if="augmentedPool[virtualRow.index].category === 'ook'"
                                                                    class="text-xs font-normal text-red-300"
                                                                    >({{ augmentedPool[virtualRow.index].character.cost }}+1)</span
                                                                >
                                                            </span>
                                                            <Badge
                                                                variant="secondary"
                                                                class="bg-white/15 px-1 py-0 text-[10px] capitalize text-white/90"
                                                            >
                                                                {{ augmentedPool[virtualRow.index].character.station }}
                                                                <span v-if="augmentedPool[virtualRow.index].character.count > 1">
                                                                    ({{ hiredCountOf(augmentedPool[virtualRow.index].character.id) }}/{{
                                                                        augmentedPool[virtualRow.index].character.count
                                                                    }})
                                                                </span>
                                                            </Badge>
                                                            <Badge
                                                                :class="categoryColor(augmentedPool[virtualRow.index].category)"
                                                                class="px-1 py-0 text-[10px]"
                                                            >
                                                                {{ categoryLabel(augmentedPool[virtualRow.index].category) }}
                                                            </Badge>
                                                            <Badge
                                                                v-if="augmentedPool[virtualRow.index].henchman"
                                                                class="bg-amber-400/20 px-1 py-0 text-[10px] text-amber-200"
                                                            >
                                                                Henchman
                                                            </Badge>
                                                            <Badge
                                                                v-if="augmentedPool[virtualRow.index].unique"
                                                                class="bg-cyan-400/20 px-1 py-0 text-[10px] text-cyan-200"
                                                            >
                                                                Unique
                                                            </Badge>
                                                            <span class="hidden truncate text-white/50 sm:inline">
                                                                {{
                                                                    augmentedPool[virtualRow.index].character.keywords
                                                                        .map((k: Keyword) => k.name)
                                                                        .join(', ')
                                                                }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="flex shrink-0 items-center gap-1">
                                                        <span
                                                            v-if="!augmentedPool[virtualRow.index].hireCheck.allowed"
                                                            class="text-[10px] text-white/50"
                                                        >
                                                            {{ augmentedPool[virtualRow.index].hireCheck.reason }}
                                                        </span>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            class="size-7 text-white hover:bg-white/10 hover:text-white"
                                                            :disabled="!augmentedPool[virtualRow.index].hireCheck.allowed"
                                                            @click.stop="addToCrewById(augmentedPool[virtualRow.index].character)"
                                                        >
                                                            <UserPlus class="size-4" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Custom Characters Section (Desktop) -->
                                    <div v-if="customCharacters.length && selectedFaction" class="mt-3 border-t pt-3">
                                        <div class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                            Your Custom Characters
                                        </div>
                                        <div class="space-y-1">
                                            <div
                                                v-for="cc in customCharacters.filter((c) => c.faction === selectedFaction)"
                                                :key="'cc-desk-' + cc.id"
                                                class="flex items-center justify-between rounded-md bg-purple-500/10 px-2 py-1.5"
                                            >
                                                <div class="flex min-w-0 items-center gap-2">
                                                    <Badge class="shrink-0 bg-purple-600 px-1 py-0 text-[8px] text-white">Custom</Badge>
                                                    <span class="truncate text-xs font-medium">{{ cc.display_name }}</span>
                                                    <span class="shrink-0 text-[10px] text-muted-foreground">{{ cc.cost ?? 0 }}ss</span>
                                                </div>
                                                <Button variant="ghost" size="icon" class="size-7" @click="addCustomToCrew(cc)">
                                                    <UserPlus class="size-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- Crew Panel (right on desktop, top on mobile) -->
                        <div :class="isFixedCrew ? '' : 'md:col-span-2'">
                            <!-- Over budget warning -->
                            <div
                                v-if="isOverBudget"
                                class="mb-2 flex items-center gap-2 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-1.5 text-xs font-medium text-destructive"
                            >
                                <ShieldAlert class="size-4 shrink-0" />
                                Over budget by {{ Math.abs(remaining) }}<GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                            </div>
                            <Card>
                                <CardContent class="p-2 md:p-3">
                                    <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                                        <div class="flex items-center gap-1">
                                            <span class="text-muted-foreground">Spent:</span>
                                            <span class="font-medium" :class="totalSpent > encounterSize ? 'text-destructive' : ''">
                                                {{ totalSpent }} / {{ encounterSize }}
                                            </span>
                                            <GameIcon type="soulstone" class-name="h-4 inline-block" />
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <span class="text-muted-foreground">Pool:</span>
                                            <span class="font-medium">{{ soulstonePool }}</span>
                                            <GameIcon type="soulstone" class-name="h-4 inline-block" />
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <span class="text-muted-foreground">OOK:</span>
                                            <span class="font-medium" :class="ookCount >= 2 ? 'text-amber-600 dark:text-amber-400' : ''">
                                                {{ ookCount }} / 2
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Crew Stats Panel -->
                                    <div v-if="crewStats" class="mb-3 rounded-md border border-border/50 bg-accent/30 p-2">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5 sm:gap-x-4">
                                            <div class="text-center">
                                                <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Models</div>
                                                <div class="text-sm font-bold leading-tight">{{ crewStats.models }}</div>
                                            </div>
                                            <Separator orientation="vertical" class="hidden h-6 sm:block" />
                                            <div v-if="crewStats.avgCost != null" class="text-center">
                                                <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Avg Cost</div>
                                                <div class="text-sm font-bold leading-tight">{{ crewStats.avgCost }}</div>
                                            </div>
                                            <div v-if="crewStats.avgHealth != null" class="text-center">
                                                <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Avg HP</div>
                                                <div class="text-sm font-bold leading-tight">{{ crewStats.avgHealth }}</div>
                                            </div>
                                            <div v-if="crewStats.avgSpeed != null" class="text-center">
                                                <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Avg Spd</div>
                                                <div class="text-sm font-bold leading-tight">{{ crewStats.avgSpeed }}</div>
                                            </div>
                                            <div v-if="crewStats.avgDefense != null" class="text-center">
                                                <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Avg Def</div>
                                                <div class="text-sm font-bold leading-tight">{{ crewStats.avgDefense }}</div>
                                            </div>
                                            <div v-if="crewStats.avgWillpower != null" class="text-center">
                                                <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Avg Wp</div>
                                                <div class="text-sm font-bold leading-tight">{{ crewStats.avgWillpower }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="Object.keys(crewStats.suitCounts).length" class="mt-2 border-t border-border/30 pt-2">
                                        <div class="mb-1 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Trigger Counts</div>
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                            <div
                                                v-for="suit in ['crow', 'mask', 'ram', 'tome', 'soulstone'].filter((s) => crewStats!.suitCounts[s])"
                                                :key="suit"
                                                class="text-center"
                                            >
                                                <GameIcon :type="suit" class-name="mx-auto h-4" />
                                                <div class="text-sm font-bold leading-tight">{{ crewStats.suitCounts[suit] }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Fixed Crew Notice -->
                                    <div
                                        v-if="isFixedCrew"
                                        class="mb-3 flex items-center gap-2 rounded-md border border-cyan-500/30 bg-cyan-500/10 px-3 py-1.5 text-xs font-medium text-cyan-700 dark:text-cyan-400"
                                    >
                                        <Star class="size-3.5 shrink-0" />
                                        Fixed roster — crew is preset by upgrade
                                    </div>

                                    <!-- Required Hires Notice -->
                                    <div
                                        v-else-if="requiredHires.length > 0 && activeUpgradeHiringRules?.required_characteristic"
                                        class="mb-3 flex items-center gap-2 rounded-md border border-orange-500/30 bg-orange-500/10 px-3 py-1.5 text-xs font-medium text-orange-700 dark:text-orange-400"
                                    >
                                        <Star class="size-3.5 shrink-0" />
                                        {{ requiredHires.length }} required {{ activeUpgradeHiringRules.required_characteristic }} models auto-added
                                    </div>

                                    <div v-if="!isFixedCrew" class="mb-3 flex items-center justify-end">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 gap-1 text-xs text-destructive hover:text-destructive"
                                            @click="clearHiredModels"
                                        >
                                            <Trash2 class="size-3" />
                                            Clear Hired
                                        </Button>
                                    </div>

                                    <Separator class="mb-3" />

                                    <!-- Crew Cards -->
                                    <div v-if="selectedMasterTitle?.crew_upgrades?.length" class="mb-3 space-y-1">
                                        <div
                                            v-for="upgrade in selectedMasterTitle.crew_upgrades"
                                            :key="upgrade.id"
                                            class="flex items-center gap-1.5 rounded-md border px-2 py-1.5 transition-colors"
                                            :class="[
                                                activeCrewUpgradeId === upgrade.id || hasSingleCrewUpgrade
                                                    ? 'border-amber-500/50 bg-amber-500/10'
                                                    : 'border-border/50 bg-accent/30 opacity-60',
                                                upgrade.front_image ? 'cursor-pointer hover:bg-accent' : '',
                                            ]"
                                            @click="openUpgradePreview(upgrade)"
                                        >
                                            <button
                                                v-if="!hasSingleCrewUpgrade"
                                                class="shrink-0 rounded p-0.5 transition-colors hover:bg-accent"
                                                :title="activeCrewUpgradeId === upgrade.id ? 'Deselect crew card' : 'Select as active crew card'"
                                                @click.stop="toggleCrewUpgradeActive(upgrade)"
                                            >
                                                <Star
                                                    class="size-3.5"
                                                    :class="
                                                        activeCrewUpgradeId === upgrade.id ? 'fill-amber-500 text-amber-500' : 'text-muted-foreground'
                                                    "
                                                />
                                            </button>
                                            <Star v-else class="size-3.5 shrink-0 fill-amber-500 text-amber-500" />
                                            <div class="min-w-0 flex-1">
                                                <div class="text-xs font-semibold">{{ upgrade.name }}</div>
                                                <div class="text-[10px] text-muted-foreground">
                                                    {{
                                                        activeCrewUpgradeId === upgrade.id || hasSingleCrewUpgrade ? 'Active Crew Card' : 'Crew Card'
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Crew list -->
                                    <div class="max-h-[calc(100vh-20rem)] space-y-0.5 overflow-y-auto">
                                        <div v-if="crew.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                                            Select a master to begin hiring
                                        </div>

                                        <div
                                            v-for="(member, index) in crew"
                                            :key="index"
                                            :class="factionBackground(member.character.faction)"
                                            class="rounded-md border border-white/20 px-2 py-1.5 text-white transition-colors hover:brightness-110"
                                        >
                                            <div class="flex items-center justify-between">
                                                <div class="min-w-0 flex-1 cursor-pointer" @click="openCrewMemberPreview(index)">
                                                    <div class="flex items-center gap-1.5 text-sm font-semibold">
                                                        <TooltipProvider
                                                            v-if="
                                                                member.hiringCategory === 'leader' ||
                                                                member.hiringCategory === 'totem' ||
                                                                member.hiringCategory === 'ook'
                                                            "
                                                        >
                                                            <Tooltip>
                                                                <TooltipTrigger as-child>
                                                                    <Shield
                                                                        v-if="member.hiringCategory === 'leader'"
                                                                        class="size-3.5 shrink-0 text-amber-300"
                                                                    />
                                                                    <Swords
                                                                        v-if="member.hiringCategory === 'totem'"
                                                                        class="size-3.5 shrink-0 text-purple-300"
                                                                    />
                                                                    <ShieldAlert
                                                                        v-if="member.hiringCategory === 'ook'"
                                                                        class="size-3.5 shrink-0 text-red-300"
                                                                    />
                                                                </TooltipTrigger>
                                                                <TooltipContent side="top">
                                                                    <p class="text-xs">{{ categoryLabel(member.hiringCategory) }}</p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                        <span class="truncate">{{
                                                            member.miniature?.display_name || member.character.display_name
                                                        }}</span>
                                                        <Badge
                                                            v-if="member.isCustom"
                                                            class="ml-1 shrink-0 bg-purple-600 px-1 py-0 text-[8px] text-white"
                                                            >Custom</Badge
                                                        >
                                                    </div>
                                                    <div class="flex items-center gap-1.5 text-xs text-white/70">
                                                        <span
                                                            v-if="member.hiringCategory === 'ook'"
                                                            class="flex items-center text-sm font-bold text-white"
                                                            >{{ member.effectiveCost
                                                            }}<GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                                            <span class="text-xs font-normal text-red-300"
                                                                >({{ member.character.cost }}+1)</span
                                                            ></span
                                                        >
                                                        <span v-else class="flex items-center text-sm font-bold text-white"
                                                            >{{ member.effectiveCost
                                                            }}<GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block"
                                                        /></span>
                                                        <Badge :class="categoryColor(member.hiringCategory)" class="px-1 py-0 text-[10px]">
                                                            {{ categoryLabel(member.hiringCategory) }}
                                                        </Badge>
                                                    </div>
                                                </div>
                                                <Button
                                                    v-if="
                                                        member.hiringCategory !== 'leader' &&
                                                        member.hiringCategory !== 'totem' &&
                                                        member.hiringCategory !== 'fixed-crew' &&
                                                        member.hiringCategory !== 'required'
                                                    "
                                                    variant="ghost"
                                                    size="icon"
                                                    class="size-7 shrink-0 text-white hover:bg-white/10 hover:text-white"
                                                    @click="removeFromCrew(index)"
                                                >
                                                    <UserMinus class="size-4 text-red-300" />
                                                </Button>
                                            </div>
                                            <!-- Miniature version selector -->
                                            <div v-if="member.character.miniatures?.length > 1" class="mt-1">
                                                <Select
                                                    :model-value="String(member.miniature?.id ?? '')"
                                                    @update:model-value="
                                                        (val: string) => {
                                                            member.miniature = member.character.miniatures.find((m) => m.id === Number(val)) ?? null;
                                                            triggerAutosave();
                                                        }
                                                    "
                                                >
                                                    <SelectTrigger class="h-6 border-white/20 bg-white/10 text-[11px] text-white">
                                                        <SelectValue placeholder="Select miniature..." />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            v-for="mini in member.character.miniatures"
                                                            :key="mini.id"
                                                            :value="String(mini.id)"
                                                        >
                                                            {{ mini.display_name }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- References -->
                                    <CrewBuilderReferences
                                        :references="references"
                                        :loading="referencesLoading"
                                        editable
                                        @add-reference="addCustomReference"
                                    />
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Share link toast -->
        <div
            v-if="shareTooltip"
            class="fixed bottom-6 left-1/2 z-50 -translate-x-1/2 rounded-lg bg-foreground px-4 py-2 text-sm text-background shadow-lg"
        >
            <Check class="mr-1.5 inline size-4" />
            Share link copied!
        </div>
    </div>

    <!-- Card Preview Drawer (hiring pool + master selection) -->
    <Drawer v-model:open="previewDrawerOpen">
        <DrawerContent>
            <div v-if="previewCharacter" class="mx-auto w-full max-w-sm">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">
                        {{ previewCharacter.display_name }}
                        <template v-if="editorStep === 'hiring'">
                            <span class="text-yellow-400"
                                >({{ getEffectiveCost(previewCharacter) }}<GameIcon type="soulstone" class-name="ml-0.5 h-3.5 inline-block" />)</span
                            >
                        </template>
                    </DrawerTitle>
                    <div v-if="editorStep === 'hiring'" class="mt-1 flex items-center justify-center gap-1.5">
                        <Badge variant="secondary" class="text-[10px] capitalize">{{ previewCharacter.station }}</Badge>
                        <Badge v-if="getHiringCategory(previewCharacter) === 'ook'" variant="secondary" class="gap-0.5 text-xs font-bold"
                            >{{ getEffectiveCost(previewCharacter) }}<GameIcon type="soulstone" class-name="h-3 inline-block" />
                            <span class="font-normal opacity-70">({{ previewCharacter.cost }}+1)</span></Badge
                        >
                        <Badge v-else variant="secondary" class="gap-0.5 text-xs font-bold"
                            >{{ getEffectiveCost(previewCharacter) }}<GameIcon type="soulstone" class-name="h-3 inline-block"
                        /></Badge>
                        <Badge :class="categoryColorTheme(getHiringCategory(previewCharacter))" class="px-1.5 py-0 text-[10px]">
                            {{ categoryLabel(getHiringCategory(previewCharacter)) }}
                        </Badge>
                    </div>
                </DrawerHeader>
                <div class="flex min-h-0 flex-1 flex-col px-4 pb-2">
                    <!-- Miniature version picker -->
                    <div v-if="previewCharacter.miniatures?.length > 1" class="mb-3 shrink-0">
                        <Select
                            :model-value="String(previewMiniature?.id ?? '')"
                            @update:model-value="
                                (val: string) => {
                                    previewMiniature = previewCharacter!.miniatures.find((m) => m.id === Number(val)) ?? null;
                                }
                            "
                        >
                            <SelectTrigger class="h-8 text-xs">
                                <SelectValue placeholder="Select sculpt..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="mini in previewCharacter.miniatures" :key="mini.id" :value="String(mini.id)">
                                    {{ mini.display_name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex min-h-0 flex-1 items-start justify-center [&_img]:max-h-[55dvh] [&_img]:w-auto [&_img]:object-contain">
                        <CharacterCardView
                            v-if="previewMiniature?.front_image"
                            :key="previewMiniature?.id"
                            :miniature="previewMiniature"
                            :show-link="true"
                            :character-slug="previewCharacter.slug"
                        />
                        <div v-else class="py-8 text-center text-sm text-muted-foreground">No card image available</div>
                    </div>
                </div>
                <DrawerFooter class="shrink-0 pt-2">
                    <div class="flex flex-wrap items-center justify-center gap-2">
                        <template v-if="editorStep === 'hiring'">
                            <Button
                                v-if="canHire(previewCharacter).allowed"
                                class="gap-1.5"
                                @click="
                                    addToCrewWithMiniature(previewCharacter!, previewMiniature);
                                    previewDrawerOpen = false;
                                "
                            >
                                <Plus class="size-4" />
                                Add to Crew
                            </Button>
                            <div v-else class="text-xs text-muted-foreground">
                                {{ canHire(previewCharacter).reason }}
                            </div>
                        </template>
                        <DrawerClose as-child>
                            <Button variant="outline">Close</Button>
                        </DrawerClose>
                    </div>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>

    <!-- Crew Member Preview Drawer -->
    <Drawer v-model:open="crewPreviewDrawerOpen">
        <DrawerContent>
            <div v-if="crewPreviewMember" class="mx-auto w-full max-w-sm">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">
                        {{ crewPreviewMember.character.display_name }}
                        <template v-if="crewPreviewMember.character.cost != null">
                            <span v-if="crewPreviewMember.hiringCategory === 'ook'" class="text-yellow-400"
                                >({{ crewPreviewMember.effectiveCost }}<GameIcon type="soulstone" class-name="ml-0.5 h-3.5 inline-block" />)</span
                            >
                            <span v-else class="text-yellow-400"
                                >({{ crewPreviewMember.effectiveCost }}<GameIcon type="soulstone" class-name="ml-0.5 h-3.5 inline-block" />)</span
                            >
                        </template>
                    </DrawerTitle>
                    <div class="mt-1 flex items-center justify-center gap-1.5">
                        <Badge variant="secondary" class="text-[10px] capitalize">{{ crewPreviewMember.character.station }}</Badge>
                        <template v-if="crewPreviewMember.character.cost != null">
                            <Badge v-if="crewPreviewMember.hiringCategory === 'ook'" variant="secondary" class="gap-0.5 text-xs font-bold"
                                >{{ crewPreviewMember.effectiveCost }}<GameIcon type="soulstone" class-name="h-3 inline-block" />
                                <span class="font-normal opacity-70">({{ crewPreviewMember.character.cost }}+1)</span></Badge
                            >
                            <Badge v-else variant="secondary" class="gap-0.5 text-xs font-bold"
                                >{{ crewPreviewMember.effectiveCost }}<GameIcon type="soulstone" class-name="h-3 inline-block"
                            /></Badge>
                        </template>
                        <Badge :class="categoryColorTheme(crewPreviewMember.hiringCategory)" class="px-1.5 py-0 text-[10px]">
                            {{ categoryLabel(crewPreviewMember.hiringCategory) }}
                        </Badge>
                    </div>
                </DrawerHeader>
                <div class="flex min-h-0 flex-1 flex-col px-4 pb-2">
                    <!-- Miniature version picker -->
                    <div v-if="crewPreviewMember.character.miniatures?.length > 1" class="mb-3 shrink-0">
                        <Select
                            :model-value="String(crewPreviewMember.miniature?.id ?? '')"
                            @update:model-value="
                                (val: string) => {
                                    crewPreviewMember!.miniature = crewPreviewMember!.character.miniatures.find((m) => m.id === Number(val)) ?? null;
                                    triggerAutosave();
                                }
                            "
                        >
                            <SelectTrigger class="h-8 text-xs">
                                <SelectValue placeholder="Select sculpt..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="mini in crewPreviewMember.character.miniatures" :key="mini.id" :value="String(mini.id)">
                                    {{ mini.display_name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex min-h-0 flex-1 items-start justify-center [&_img]:max-h-[55dvh] [&_img]:w-auto [&_img]:object-contain">
                        <CharacterCardView
                            v-if="crewPreviewMember.miniature?.front_image"
                            :key="crewPreviewMember.miniature?.id"
                            :miniature="crewPreviewMember.miniature"
                            :show-link="true"
                            :character-slug="crewPreviewMember.character.slug"
                        />
                        <div v-else class="py-8 text-center text-sm text-muted-foreground">No card image available</div>
                    </div>
                </div>
                <DrawerFooter class="shrink-0 pt-2">
                    <div class="flex flex-wrap items-center justify-center gap-2">
                        <Button
                            v-if="canHire(crewPreviewMember.character).allowed"
                            class="gap-1.5"
                            @click="
                                addToCrewWithMiniature(crewPreviewMember!.character, null);
                                crewPreviewDrawerOpen = false;
                            "
                        >
                            <UserPlus class="size-4" />
                            Add Another
                        </Button>
                        <Button
                            v-if="
                                crewPreviewMember.hiringCategory !== 'leader' &&
                                crewPreviewMember.hiringCategory !== 'totem' &&
                                crewPreviewMember.hiringCategory !== 'fixed-crew' &&
                                crewPreviewMember.hiringCategory !== 'required'
                            "
                            variant="destructive"
                            class="gap-1.5"
                            @click="
                                removeFromCrew(crewPreviewIndex!);
                                crewPreviewDrawerOpen = false;
                            "
                        >
                            <UserMinus class="size-4" />
                            Remove
                        </Button>
                        <DrawerClose as-child>
                            <Button variant="outline">Close</Button>
                        </DrawerClose>
                    </div>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>

    <!-- Upgrade Preview Drawer -->
    <Drawer v-model:open="upgradePreviewOpen">
        <DrawerContent>
            <div v-if="upgradePreviewUpgrade" class="mx-auto w-full max-w-sm">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ upgradePreviewUpgrade.name }}</DrawerTitle>
                    <div class="mt-1 text-center text-xs text-muted-foreground">Crew Card</div>
                </DrawerHeader>
                <div class="flex min-h-0 flex-1 flex-col px-4 pb-2">
                    <div class="flex min-h-0 flex-1 items-start justify-center [&_img]:max-h-[55dvh] [&_img]:w-auto [&_img]:object-contain">
                        <UpgradeFlipCard
                            :front-image="upgradePreviewUpgrade.front_image!"
                            :back-image="upgradePreviewUpgrade.back_image"
                            :alt-text="upgradePreviewUpgrade.name"
                            :upgrade-slug="upgradePreviewUpgrade.slug"
                            :show-link="true"
                        />
                    </div>
                </div>
                <DrawerFooter class="shrink-0 pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>

    <!-- Delete confirmation dialog -->
    <Dialog
        :open="!!deleteTarget"
        @update:open="
            (v: boolean) => {
                if (!v) deleteTarget = null;
            }
        "
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete Crew</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete <span class="font-semibold">"{{ deleteTarget?.name }}"</span>? This cannot be undone.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-3 sm:gap-x-3">
                <Button variant="outline" @click="deleteTarget = null" :disabled="deleting">Cancel</Button>
                <Button variant="destructive" @click="confirmDeleteBuild" :disabled="deleting">
                    {{ deleting ? 'Deleting...' : 'Delete' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Import from Text Dialog -->
    <Dialog v-model:open="importDialogOpen">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Import Crew from Text</DialogTitle>
                <DialogDescription>Paste a crew list exported from BiggerHat or in the standard format.</DialogDescription>
            </DialogHeader>
            <Textarea
                v-model="importText"
                rows="12"
                placeholder="Lucas McCabe, Tomb Delver (Explorer's Society)
Leader:
  Lucas McCabe, Tomb Delver
Totem(s):
  Cryptologist
Hires:
  Rough Rider
  Rough Rider
  ..."
                class="font-mono text-xs"
            />
            <div
                v-if="importError"
                class="rounded-md border border-amber-500/30 bg-amber-500/5 px-3 py-1.5 text-xs text-amber-700 dark:text-amber-400"
            >
                {{ importError }}
            </div>
            <DialogFooter class="gap-2 sm:gap-0">
                <Button
                    variant="outline"
                    @click="
                        importDialogOpen = false;
                        importText = '';
                        importError = '';
                    "
                    >Cancel</Button
                >
                <Button @click="parseAndImportCrew">Import Crew</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
