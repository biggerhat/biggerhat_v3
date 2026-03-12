<script setup lang="ts">
import TipTapEditor from '@/components/blog/TipTapEditor.vue';
import CharacterCardView from '@/components/CharacterCardView.vue';
import GameIcon from '@/components/GameIcon.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { NumberField, NumberFieldContent, NumberFieldDecrement, NumberFieldIncrement, NumberFieldInput } from '@/components/ui/number-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import UpgradeFlipCard from '@/components/UpgradeFlipCard.vue';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { useVirtualizer } from '@tanstack/vue-virtual';
import { refDebounced } from '@vueuse/core';
import {
    Archive,
    ArchiveRestore,
    ArrowLeft,
    Check,
    CircleX,
    Copy,
    FileText,
    Globe,
    Loader2,
    Lock,
    Pencil,
    Plus,
    Save,
    Search,
    Shield,
    ShieldAlert,
    Star,
    Swords,
    Printer,
    Trash2,
    UserMinus,
    UserPlus,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

interface Keyword {
    id: number;
    name: string;
    slug: string;
}

interface CrewUpgrade {
    id: number;
    name: string;
    slug: string;
    front_image: string | null;
    back_image: string | null;
    keywords: Keyword[];
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
    hiringCategory: 'leader' | 'totem' | 'in-keyword' | 'versatile' | 'ook';
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
    is_archived: boolean;
    is_public: boolean;
    updated_at: string;
    copied_from: { name: string; share_code: string; is_public: boolean } | null;
}

const props = defineProps<{
    characters: CharacterData[];
    factions: Record<string, Faction>;
    keywords: Keyword[];
    savedBuilds: SavedBuild[];
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
const crewName = ref('Untitled Crew');
const crewDescription = ref<Record<string, unknown> | null>(null);
const showDescriptionEditor = ref(false);
const currentBuildId = ref<number | null>(null);
const currentShareCode = ref<string | null>(null);
const isSaving = ref(false);
const saveDebounceTimer = ref<ReturnType<typeof setTimeout> | null>(null);
const lastSavedAt = ref<string | null>(null);
const shareTooltip = ref(false);
const saveError = ref<string | null>(null);
const savedBuilds = ref<SavedBuild[]>([...(props.savedBuilds as SavedBuild[])]);
const buildsTab = ref('active');

// ─── View mode: 'builds' (list) or 'editor' (crew builder) ───
const urlParams = new URLSearchParams(window.location.search);
const startInEditor = urlParams.has('new') || urlParams.has('build') || urlParams.has('crew');
const viewMode = ref<'builds' | 'editor'>(isAuthenticated.value && !startInEditor ? 'builds' : 'editor');

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

// ─── CSRF helper ───
const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

// ─── Faction selection ───
const selectFaction = (factionSlug: string) => {
    selectedFaction.value = factionSlug;
    selectedMasterName.value = null;
    selectedMasterTitle.value = null;
    crew.value = [];
};

const lastPushedCode = ref<string | null>(null);

const pushBuildToUrl = () => {
    if (!currentShareCode.value) return;
    const url = new URL(window.location.href);
    url.searchParams.set('build', currentShareCode.value);
    if (lastPushedCode.value !== currentShareCode.value) {
        window.history.pushState({ crewBuilder: true }, '', url.toString());
        lastPushedCode.value = currentShareCode.value;
    } else {
        window.history.replaceState({ crewBuilder: true }, '', url.toString());
    }
};

const clearBuildFromUrl = () => {
    const url = new URL(window.location.href);
    url.searchParams.delete('build');
    url.searchParams.delete('crew');
    window.history.replaceState({}, '', url.toString());
    lastPushedCode.value = null;
};

const onPopState = () => {
    const params = new URLSearchParams(window.location.search);
    if (!params.has('build') && !params.has('crew')) {
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
        lastPushedCode.value = null;
        viewMode.value = isAuthenticated.value ? 'builds' : 'editor';
    }
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
    crew.value = [];
    clearBuildFromUrl();
};

// ─── Masters for selected faction ───
const mastersForFaction = computed(() => {
    if (!selectedFaction.value) return [];
    return (props.characters as CharacterData[]).filter(
        (c) => c.station === 'master' && (c.faction === selectedFaction.value || c.second_faction === selectedFaction.value),
    );
});

const uniqueMasterNames = computed(() => {
    const names = new Set<string>();
    mastersForFaction.value.forEach((m) => names.add(m.name));
    return [...names].sort();
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
    }
};

const selectMasterTitle = (master: CharacterData) => {
    selectedMasterTitle.value = master;
    crew.value = [];
    poolFilter.value = 'in-keyword';
    activeCrewUpgradeId.value = master.crew_upgrades?.[0]?.id ?? null;

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

    // Remove old leaders and totems
    crew.value = crew.value.filter((m) => m.hiringCategory !== 'leader' && m.hiringCategory !== 'totem');

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
    return {
        models: crew.value.length,
        avgCost: avg(nums(hirable.map((m) => m.effectiveCost))),
        avgHealth: avg(nums(crew.value.map((m) => m.character.health))),
        avgSpeed: avg(nums(crew.value.map((m) => m.character.speed))),
        avgDefense: avg(nums(crew.value.map((m) => m.character.defense))),
        avgWillpower: avg(nums(crew.value.map((m) => m.character.willpower))),
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

const removeFromCrew = (index: number) => {
    if (crew.value[index].hiringCategory === 'leader' || crew.value[index].hiringCategory === 'totem') return;
    crew.value.splice(index, 1);
    triggerAutosave();
};

const clearHiredModels = () => {
    crew.value = crew.value.filter((m) => m.hiringCategory === 'leader' || m.hiringCategory === 'totem');
    triggerAutosave();
};

const recalculateHiringCategories = () => {
    crew.value.forEach((member) => {
        if (member.hiringCategory === 'leader' || member.hiringCategory === 'totem') return;
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
    return (props.characters as CharacterData[]).filter((c) => {
        if (c.station === 'master') return false;
        if (c.cost == null) return false;
        if (isTotemOfAnotherMaster(c)) return false;
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

const poolFilterCounts = computed(() => ({
    'in-keyword': hiringPool.value.filter((c) => getHiringCategory(c) === 'in-keyword').length,
    versatile: hiringPool.value.filter((c) => getHiringCategory(c) === 'versatile').length,
    ook: hiringPool.value.filter((c) => getHiringCategory(c) === 'ook').length,
    all: hiringPool.value.length,
}));

// ─── Virtual scroller ───
const poolScrollRef = ref<HTMLElement | null>(null);
const poolVirtualizer = useVirtualizer(
    computed(() => ({
        count: filteredHiringPool.value.length,
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
    } else if (editorStep.value === 'master-name') {
        selectedFaction.value = null;
    }
};

// ─── Crew upgrade display ───
const activeCrewUpgradeId = ref<number | null>(null);
const hasSingleCrewUpgrade = computed(() => (selectedMasterTitle.value?.crew_upgrades?.length ?? 0) === 1);

const toggleCrewUpgradeActive = (upgrade: CrewUpgrade) => {
    if (hasSingleCrewUpgrade.value) return;
    activeCrewUpgradeId.value = activeCrewUpgradeId.value === upgrade.id ? null : upgrade.id;
};

// ─── Category helpers ───
const categoryLabel = (cat: string): string =>
    ({ leader: 'Leader', totem: 'Totem', 'in-keyword': 'In Keyword', versatile: 'Versatile', ook: 'Out of Keyword' })[cat] ?? cat;

// Colors for faction-colored bars (white text context)
const categoryColor = (cat: string): string =>
    ({
        leader: 'bg-amber-400/20 text-amber-200',
        totem: 'bg-purple-400/20 text-purple-200',
        'in-keyword': 'bg-green-400/20 text-green-200',
        versatile: 'bg-blue-400/20 text-blue-200',
        ook: 'bg-red-400/20 text-red-200',
    })[cat] ?? '';

// Colors for normal theme backgrounds (drawers, cards)
const categoryColorTheme = (cat: string): string =>
    ({
        leader: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        totem: 'bg-purple-500/10 text-purple-700 dark:text-purple-400',
        'in-keyword': 'bg-green-500/10 text-green-700 dark:text-green-400',
        versatile: 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
        ook: 'bg-red-500/10 text-red-700 dark:text-red-400',
    })[cat] ?? '';

// ═══════════════════════════════════════
// Save / Load / Share / Archive
// ═══════════════════════════════════════

const buildCrewData = (): number[] =>
    crew.value.filter((m) => m.hiringCategory !== 'leader' && m.hiringCategory !== 'totem').map((m) => m.character.id);

const buildPayload = () => ({
    name: crewName.value,
    description: crewDescription.value,
    faction: selectedFaction.value,
    master_id: selectedMasterTitle.value?.id,
    encounter_size: encounterSize.value,
    crew_data: buildCrewData(),
});

const saveBuild = async () => {
    if (!isAuthenticated.value || !selectedMasterTitle.value || isSaving.value) return;

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
const rebuildCrew = (faction: string, masterId: number, crewData: number[]) => {
    selectedFaction.value = faction;
    const master = characterById.value.get(masterId);
    if (!master) return;

    selectedMasterName.value = master.name;
    selectedMasterTitle.value = master;
    activeCrewUpgradeId.value = master.crew_upgrades?.[0]?.id ?? null;
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

    crewData?.forEach((charId: number) => {
        const character = characterById.value.get(charId);
        if (character) {
            const cat = getHiringCategory(character);
            crew.value.push({
                character,
                miniature: getNextMiniature(character),
                isTotem: false,
                effectiveCost: cat === 'ook' ? character.cost + 1 : character.cost,
                hiringCategory: cat,
            });
        }
    });
};

const loadBuild = (build: SavedBuild) => {
    currentBuildId.value = build.id;
    currentShareCode.value = build.share_code;
    crewName.value = build.name;
    crewDescription.value = build.description ?? null;
    showDescriptionEditor.value = !!build.description;
    encounterSize.value = build.encounter_size;
    rebuildCrew(build.faction, build.master_id, build.crew_data);
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
        await fetch(route('tools.crew_builder.destroy', { crewBuild: deleteTarget.value.id }), {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        });
        savedBuilds.value = savedBuilds.value.filter((b) => b.id !== deleteTarget.value!.id);
        if (currentBuildId.value === deleteTarget.value.id) resetBuildState();
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
        while (isSaving.value) {
            await new Promise((r) => setTimeout(r, 100));
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
    };
    const encoded = btoa(JSON.stringify(state));
    navigator.clipboard.writeText(`${route('tools.crew_builder.editor')}?crew=${encoded}`);
    shareTooltip.value = true;
    setTimeout(() => (shareTooltip.value = false), 2000);
};

const printCrewPDF = () => {
    if (crew.value.length === 0) return;

    const cards: Array<{ card_type: string; id: number }> = [];

    for (const member of crew.value) {
        const mini = member.miniature ?? member.character.miniatures?.[0];
        if (mini) {
            cards.push({ card_type: 'miniature', id: mini.id });
        }

        // Insert crew upgrades right after the master
        if (member.hiringCategory === 'leader') {
            for (const upgrade of member.character.crew_upgrades ?? []) {
                cards.push({ card_type: 'upgrade', id: upgrade.id });
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
};

// ─── Init ───

// ─── Init: handle anonymous share via URL param ───
onMounted(() => {
    window.addEventListener('popstate', onPopState);

    const params = new URLSearchParams(window.location.search);

    const buildParam = params.get('build');
    if (buildParam) {
        const build = savedBuilds.value.find((b) => b.share_code === buildParam);
        if (build) {
            lastPushedCode.value = buildParam;
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
            rebuildCrew(state.f, state.m, state.c ?? []);
            currentBuildId.value = null;
            currentShareCode.value = null;
            lastSavedAt.value = null;
        } catch {
            // Invalid param
        }
    }
});

onUnmounted(() => {
    window.removeEventListener('popstate', onPopState);
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
                <div class="px-2 text-sm text-muted-foreground">Build your crew for Malifaux encounters.</div>
            </template>
        </PageBanner>

        <div class="container mx-auto px-4 lg:px-6" :class="viewMode === 'builds' || editorStep !== 'hiring' ? 'mt-6' : 'mt-4'">
            <!-- ═══════════════════════════════════════════ -->
            <!-- BUILDS LIST (authenticated landing page)   -->
            <!-- ═══════════════════════════════════════════ -->
            <div v-if="viewMode === 'builds'">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">My Crews</h2>
                    <Button size="sm" class="gap-1.5" @click="startNewBuild">
                        <Plus class="size-4" />
                        New Crew
                    </Button>
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
                                        <img v-if="factions[build.faction]" :src="factions[build.faction].logo" class="mt-0.5 size-8 shrink-0" />
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
                    </div>

                    <!-- Step 2: Master Name -->
                    <div v-if="editorStep === 'master-name'">
                        <div class="mb-4 flex items-center justify-center gap-3">
                            <img :src="factions[selectedFaction!].logo" :alt="factions[selectedFaction!].name" class="size-8" />
                            <h2 class="text-lg font-semibold">Choose Your Master</h2>
                        </div>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                            <button
                                v-for="name in uniqueMasterNames"
                                :key="name"
                                @click="selectMasterName(name)"
                                class="rounded-lg border-2 border-transparent p-4 text-center transition-all hover:border-primary/50 hover:bg-accent"
                            >
                                <div class="text-sm font-semibold">{{ name }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ mastersForFaction.filter((m) => m.name === name).length }}
                                    {{ mastersForFaction.filter((m) => m.name === name).length === 1 ? 'title' : 'titles' }}
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: Master Title -->
                    <div v-if="editorStep === 'master-title'">
                        <div class="mb-4 flex items-center justify-center gap-3">
                            <img :src="factions[selectedFaction!].logo" :alt="factions[selectedFaction!].name" class="size-8" />
                            <h2 class="text-lg font-semibold">Choose Title for {{ selectedMasterName }}</h2>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3">
                            <button
                                v-for="master in masterTitleVariants"
                                :key="master.id"
                                @click="selectMasterTitle(master)"
                                class="rounded-lg border-2 border-transparent p-4 text-left transition-all hover:border-primary/50 hover:bg-accent"
                            >
                                <div class="text-sm font-semibold">{{ master.display_name }}</div>
                                <div class="flex flex-wrap gap-1 text-xs text-muted-foreground">
                                    <Badge v-for="kw in master.keywords" :key="kw.slug" variant="secondary" class="px-1 py-0 text-[10px]">
                                        {{ kw.name }}
                                    </Badge>
                                </div>
                                <div v-if="master.crew_upgrades?.length" class="mt-1 text-xs text-muted-foreground">
                                    Crew: {{ master.crew_upgrades.map((u: CrewUpgrade) => u.name).join(', ') }}
                                </div>
                            </button>
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
                                <Button variant="ghost" size="sm" class="h-7 gap-1 text-xs" :disabled="crew.length === 0" @click="printCrewPDF">
                                    <Printer class="size-3.5" />
                                    PDF
                                </Button>

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

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
                        <!-- Hiring Pool (left on desktop, below on mobile) -->
                        <div class="order-2 md:order-1 md:col-span-3">
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
                                        <span class="ml-auto text-xs text-muted-foreground">{{ filteredHiringPool.length }} shown</span>
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

                                    <div ref="poolScrollRef" class="h-[40vh] overflow-y-auto sm:h-[50vh] md:h-[calc(100vh-18rem)]">
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
                                                    :class="[
                                                        factionBackground(filteredHiringPool[virtualRow.index].faction),
                                                        !canHire(filteredHiringPool[virtualRow.index]).allowed ? 'opacity-40' : '',
                                                    ]"
                                                    class="my-0.5 flex items-center justify-between rounded-md border border-white/20 px-2 py-1.5 text-white transition-colors hover:brightness-110"
                                                >
                                                    <div
                                                        class="min-w-0 flex-1 cursor-pointer"
                                                        @click="openCardPreview(filteredHiringPool[virtualRow.index])"
                                                    >
                                                        <div class="flex items-center gap-1.5 text-sm font-semibold">
                                                            {{ filteredHiringPool[virtualRow.index].display_name }}
                                                            <Badge
                                                                v-if="filteredHiringPool[virtualRow.index].miniatures?.length > 1"
                                                                variant="outline"
                                                                class="border-white/30 px-1 py-0 text-[9px] font-normal text-white/80"
                                                            >
                                                                {{ filteredHiringPool[virtualRow.index].miniatures.length }} sculpts
                                                            </Badge>
                                                        </div>
                                                        <div class="flex flex-wrap items-center gap-1.5 text-xs text-white/70">
                                                            <span class="flex items-center text-sm font-bold text-white">
                                                                {{ getEffectiveCost(filteredHiringPool[virtualRow.index]) }}<GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                                                <span
                                                                    v-if="getHiringCategory(filteredHiringPool[virtualRow.index]) === 'ook'"
                                                                    class="text-xs font-normal text-red-300"
                                                                    >({{ filteredHiringPool[virtualRow.index].cost }}+1)</span
                                                                >
                                                            </span>
                                                            <Badge
                                                                variant="secondary"
                                                                class="bg-white/15 px-1 py-0 text-[10px] capitalize text-white/90"
                                                            >
                                                                {{ filteredHiringPool[virtualRow.index].station }}
                                                                <span v-if="filteredHiringPool[virtualRow.index].count > 1">
                                                                    ({{ hiredCountOf(filteredHiringPool[virtualRow.index].id) }}/{{
                                                                        filteredHiringPool[virtualRow.index].count
                                                                    }})
                                                                </span>
                                                            </Badge>
                                                            <Badge
                                                                :class="categoryColor(getHiringCategory(filteredHiringPool[virtualRow.index]))"
                                                                class="px-1 py-0 text-[10px]"
                                                            >
                                                                {{ categoryLabel(getHiringCategory(filteredHiringPool[virtualRow.index])) }}
                                                            </Badge>
                                                            <Badge
                                                                v-if="isHenchman(filteredHiringPool[virtualRow.index])"
                                                                class="bg-amber-400/20 px-1 py-0 text-[10px] text-amber-200"
                                                            >
                                                                Henchman
                                                            </Badge>
                                                            <Badge
                                                                v-if="isUnique(filteredHiringPool[virtualRow.index])"
                                                                class="bg-cyan-400/20 px-1 py-0 text-[10px] text-cyan-200"
                                                            >
                                                                Unique
                                                            </Badge>
                                                            <span class="hidden truncate text-white/50 sm:inline">
                                                                {{
                                                                    filteredHiringPool[virtualRow.index].keywords
                                                                        .map((k: Keyword) => k.name)
                                                                        .join(', ')
                                                                }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="flex shrink-0 items-center gap-1">
                                                        <span
                                                            v-if="!canHire(filteredHiringPool[virtualRow.index]).allowed"
                                                            class="text-[10px] text-white/50"
                                                        >
                                                            {{ canHire(filteredHiringPool[virtualRow.index]).reason }}
                                                        </span>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            class="size-7 text-white hover:bg-white/10 hover:text-white"
                                                            :disabled="!canHire(filteredHiringPool[virtualRow.index]).allowed"
                                                            @click.stop="addToCrewById(filteredHiringPool[virtualRow.index])"
                                                        >
                                                            <UserPlus class="size-4" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- Crew Panel (right on desktop, top on mobile) -->
                        <div class="order-1 md:order-2 md:col-span-2">
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
                                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5">
                                            <div class="text-center">
                                                <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Models</div>
                                                <div class="text-sm font-bold leading-tight">{{ crewStats.models }}</div>
                                            </div>
                                            <Separator orientation="vertical" class="h-6" />
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

                                    <!-- Crew Upgrades -->
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
                                                :title="
                                                    activeCrewUpgradeId === upgrade.id ? 'Deselect crew upgrade' : 'Select as active crew upgrade'
                                                "
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
                                                    {{ activeCrewUpgradeId === upgrade.id || hasSingleCrewUpgrade ? 'Active Crew Upgrade' : 'Crew Upgrade' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Crew list -->
                                    <div class="max-h-[30vh] space-y-0.5 overflow-y-auto sm:max-h-[40vh] md:max-h-[calc(100vh-20rem)]">
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
                                                    </div>
                                                    <div class="flex items-center gap-1.5 text-xs text-white/70">
                                                        <span v-if="member.hiringCategory === 'ook'" class="flex items-center text-sm font-bold text-white"
                                                            >{{ member.effectiveCost }}<GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                                            <span class="text-xs font-normal text-red-300"
                                                                >({{ member.character.cost }}+1)</span
                                                            ></span
                                                        >
                                                        <span v-else class="flex items-center text-sm font-bold text-white">{{ member.effectiveCost }}<GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" /></span>
                                                        <Badge :class="categoryColor(member.hiringCategory)" class="px-1 py-0 text-[10px]">
                                                            {{ categoryLabel(member.hiringCategory) }}
                                                        </Badge>
                                                    </div>
                                                </div>
                                                <Button
                                                    v-if="member.hiringCategory !== 'leader' && member.hiringCategory !== 'totem'"
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

    <!-- Card Preview Drawer (hiring pool) -->
    <Drawer v-model:open="previewDrawerOpen">
        <DrawerContent>
            <div v-if="previewCharacter" class="mx-auto w-full max-w-sm">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">
                        {{ previewCharacter.display_name }}
                        <span v-if="getHiringCategory(previewCharacter) === 'ook'" class="text-yellow-400"
                            >({{ getEffectiveCost(previewCharacter) }}<GameIcon type="soulstone" class-name="ml-0.5 h-3.5 inline-block" />)</span
                        >
                        <span v-else class="text-yellow-400">({{ getEffectiveCost(previewCharacter) }}<GameIcon type="soulstone" class-name="ml-0.5 h-3.5 inline-block" />)</span>
                    </DrawerTitle>
                    <div class="mt-1 flex items-center justify-center gap-1.5">
                        <Badge variant="secondary" class="text-[10px] capitalize">{{ previewCharacter.station }}</Badge>
                        <Badge v-if="getHiringCategory(previewCharacter) === 'ook'" variant="secondary" class="gap-0.5 text-xs font-bold"
                            >{{ getEffectiveCost(previewCharacter) }}<GameIcon type="soulstone" class-name="h-3 inline-block" />
                            <span class="font-normal opacity-70">({{ previewCharacter.cost }}+1)</span></Badge
                        >
                        <Badge v-else variant="secondary" class="gap-0.5 text-xs font-bold">{{ getEffectiveCost(previewCharacter) }}<GameIcon type="soulstone" class-name="h-3 inline-block" /></Badge>
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
                            <span v-else class="text-yellow-400">({{ crewPreviewMember.effectiveCost }}<GameIcon type="soulstone" class-name="ml-0.5 h-3.5 inline-block" />)</span>
                        </template>
                    </DrawerTitle>
                    <div class="mt-1 flex items-center justify-center gap-1.5">
                        <Badge variant="secondary" class="text-[10px] capitalize">{{ crewPreviewMember.character.station }}</Badge>
                        <template v-if="crewPreviewMember.character.cost != null">
                            <Badge v-if="crewPreviewMember.hiringCategory === 'ook'" variant="secondary" class="gap-0.5 text-xs font-bold"
                                >{{ crewPreviewMember.effectiveCost }}<GameIcon type="soulstone" class-name="h-3 inline-block" />
                                <span class="font-normal opacity-70">({{ crewPreviewMember.character.cost }}+1)</span></Badge
                            >
                            <Badge v-else variant="secondary" class="gap-0.5 text-xs font-bold">{{ crewPreviewMember.effectiveCost }}<GameIcon type="soulstone" class-name="h-3 inline-block" /></Badge>
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
                            v-if="crewPreviewMember.hiringCategory !== 'leader' && crewPreviewMember.hiringCategory !== 'totem'"
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
                    <div class="mt-1 text-center text-xs text-muted-foreground">Crew Upgrade</div>
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
    <Dialog :open="!!deleteTarget" @update:open="(v: boolean) => { if (!v) deleteTarget = null }">
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
</template>
