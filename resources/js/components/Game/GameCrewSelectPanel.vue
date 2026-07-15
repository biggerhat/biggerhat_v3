<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import GameIcon from '@/components/GameIcon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { factionBackground } from '@/composables/useFactionColor';
import { CARD_HOVER_PROMINENT } from '@/lib/cardHover';
import { categoryColor, categoryLabel } from '@/lib/gameDisplay';
import type { GameData, GamePlayer } from '@/types/game';
import { Link } from '@inertiajs/vue3';
import { Check, ChevronDown, Loader2, Pencil, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface MasterTitle {
    id: number;
    display_name: string | null;
    title?: string | null;
}
interface MasterOption {
    name: string;
    titles: MasterTitle[];
}
interface CrewOptionMember {
    display_name: string;
    faction: string;
    cost: number;
    effective_cost: number;
    category: string;
}
interface CrewOption {
    id: number;
    name: string;
    share_code: string;
    faction: string;
    master_name: string;
    encounter_size: number;
    crew_count: number;
    total_spent: number;
    soulstone_pool: number;
    ook_count: number;
    is_over_budget: boolean;
    members: CrewOptionMember[];
}

interface CampaignArsenalModel {
    /** The CampaignArsenalModel row's own id — distinct per owned physical
     *  copy, even when several share the same character_id. Selection state
     *  keys off this, not character_id, so owning multiple copies of the
     *  same catalog Character can be hired individually. */
    id: number;
    character_id: number;
    name: string;
    faction: string;
    station: string;
    cost: number;
    effective_cost: number;
    is_ook: boolean;
    is_peon: boolean;
}

interface CampaignOwnedEquipment {
    id: number;
    name: string;
    slug: string;
    front_image: string | null;
    back_image: string | null;
    type: string | null;
    plentiful: number;
    power_bar_count: number | null;
    description: string | null;
    // Pg 31: once an Attack/Tactical Mod advancement targets an
    // equipment-granted action, that equipment is tied to the Leader/Totem
    // going forward — can't be assigned to any other hired model.
    is_advanced: boolean;
}

const props = defineProps<{
    game: GameData;
    myCrews: CrewOption[];
    masters: MasterOption[];
    myPlayer?: GamePlayer;
    opponentPlayer?: GamePlayer;
    isSolo: boolean;
    isCampaign: boolean;
    campaignArsenal: CampaignArsenalModel[];
    campaignOwnedEquipment: CampaignOwnedEquipment[];
    campaignTotem: { id: number; name: string } | null;
    submitting: boolean;
    mySlot: number;
    opponentSlot: number;
    isOpponentSetupPhase: boolean;
    crewStepDone: boolean;
    opponentCrewStepDone: boolean;
}>();

const emit = defineEmits<{
    /** Select a saved crew (parent owns the POST + reload). Body carries crew_build_id + slot. */
    confirm: [body: Record<string, unknown>];
    /** Campaign games: confirm with selected CampaignArsenalModel row IDs + optional equipment assignments (pg 19). */
    'confirm-campaign-crew': [arsenalModelIds: number[], equipmentAssignments: { equipment_id: number; target: string }[]];
    /** Skip the opponent's crew in solo setup, optionally locking in their title first. */
    'skip-opponent-crew': [titleDisplayName: string | null];
}>();

const masterTitleOptions = computed(() => {
    if (!props.myPlayer?.master_name) return [];
    const baseName = props.myPlayer.master_name.split(',')[0];
    const masterGroup = props.masters.find((m) => m.name === baseName);
    return masterGroup?.titles ?? [];
});

// Title filter for crew select (filters visible crews, doesn't submit).
const filterTitleId = ref<number | null>(null);
// Reset title filter when master changes.
watch(
    () => props.myPlayer?.master_id,
    () => {
        filterTitleId.value = null;
    },
);

const expandedCrewId = ref<number | null>(null);
const expandedOpponentCrewId = ref<number | null>(null);

const crewBuilderUrl = (player: GamePlayer | undefined) => {
    const faction = player?.faction ?? '';
    const gameParam = '&from_game=' + encodeURIComponent(props.game.uuid);
    const masterId = player?.master_id;
    if (masterId) {
        return route('tools.crew_builder.editor') + '?step=hiring&faction=' + encodeURIComponent(faction) + '&master=' + masterId + gameParam;
    }
    const masterName = player?.master_name?.split(',')[0] ?? '';
    return (
        route('tools.crew_builder.editor') +
        '?step=title&faction=' +
        encodeURIComponent(faction) +
        '&master=' +
        encodeURIComponent(masterName) +
        gameParam
    );
};
const newCrewUrl = computed(() => crewBuilderUrl(props.myPlayer));
const newOpponentCrewUrl = computed(() => crewBuilderUrl(props.opponentPlayer));

const crewsForMaster = (masterName: string | null | undefined, titleId: number | null, titleOptions: MasterTitle[]) => {
    if (!masterName) return [];
    const baseName = masterName.split(',')[0].trim();
    let crews = props.myCrews.filter((c) => c.master_name.split(',')[0].trim() === baseName);
    if (titleId) {
        const title = titleOptions.find((t) => t.id === titleId);
        if (title) crews = crews.filter((c) => c.master_name === title.display_name);
    }
    return crews;
};
const matchingCrews = computed(() => crewsForMaster(props.myPlayer?.master_name, filterTitleId.value, masterTitleOptions.value));

const opponentFilterTitleId = ref<number | null>(null);
const opponentTitleOptions = computed(() => {
    const oppMasterName = props.opponentPlayer?.master_name;
    if (!oppMasterName) return [];
    const baseName = oppMasterName.split(',')[0].trim();
    const masterGroup = props.masters.find((m) => m.name === baseName);
    return masterGroup?.titles ?? [];
});
const opponentMatchingCrews = computed(() =>
    crewsForMaster(props.opponentPlayer?.master_name, opponentFilterTitleId.value, opponentTitleOptions.value),
);

const selectedOpponentTitleForSkip = ref<number | null>(null);
const skipOpponentCrew = () => {
    const titleId = selectedOpponentTitleForSkip.value;
    const title = titleId ? opponentTitleOptions.value.find((t) => t.id === titleId) : null;
    emit('skip-opponent-crew', title?.display_name ?? null);
};

// Keyed by CampaignArsenalModel row id (not character_id) so owning several
// copies of the same catalog Character — each its own arsenal row — can be
// selected/hired individually instead of all copies toggling together.
const selectedArsenalIds = ref<number[]>([]);
const toggleArsenalModel = (arsenalModelId: number) => {
    const idx = selectedArsenalIds.value.indexOf(arsenalModelId);
    if (idx >= 0) {
        selectedArsenalIds.value.splice(idx, 1);
    } else {
        selectedArsenalIds.value.push(arsenalModelId);
    }
};
const campaignTotalCost = computed(() =>
    selectedArsenalIds.value.reduce((sum, id) => {
        const m = props.campaignArsenal.find((a) => a.id === id);
        return sum + (m?.effective_cost ?? 0);
    }, 0),
);
const campaignOverBudget = computed(() => campaignTotalCost.value > props.game.encounter_size);

// "(2/3)" copy-index suffix when the arsenal owns more than one of the same
// catalog model — otherwise two identical-looking "Guild Guard" rows would
// give no visual hint they're independently selectable owned copies.
const campaignArsenalCopyLabel = computed(() => {
    const totalByName: Record<string, number> = {};
    for (const m of props.campaignArsenal) totalByName[m.name] = (totalByName[m.name] ?? 0) + 1;
    const seen: Record<string, number> = {};
    const labels: Record<number, string | null> = {};
    for (const m of props.campaignArsenal) {
        if (totalByName[m.name] <= 1) {
            labels[m.id] = null;
            continue;
        }
        seen[m.name] = (seen[m.name] ?? 0) + 1;
        labels[m.id] = `${seen[m.name]}/${totalByName[m.name]}`;
    }

    return labels;
});

// Equipment assignment (pg 19): optional, one slot per owned copy. Value is
// '__none__' (unassigned), 'leader', or a selected arsenal row id as a string.
const equipmentTargets = ref<Record<string, string>>({});
interface EquipmentSlot {
    key: string;
    equipmentId: number;
    name: string;
    copyLabel: string | null;
}
const equipmentSlots = computed<EquipmentSlot[]>(() =>
    props.campaignOwnedEquipment.flatMap((e) =>
        Array.from({ length: e.plentiful }, (_, i) => ({
            key: `${e.id}:${i}`,
            equipmentId: e.id,
            name: e.name,
            copyLabel: e.plentiful > 1 ? `copy ${i + 1}/${e.plentiful}` : null,
        })),
    ),
);
// Per-equipment-id target options — advanced equipment (pg 31: tied to the
// Leader/Totem once an Attack/Tactical Mod advancement targets it) drops the
// hired-model options entirely, since assigning it there would just be
// rejected server-side.
const equipmentTargetOptionsFor = (equipmentId: number) => {
    // Disambiguate when the same catalog model is hired more than once this
    // game (multiple selected arsenal rows sharing a name) — otherwise the
    // dropdown would show two identical, unpickable-apart "Guild Guard" entries.
    const nameCounts: Record<string, number> = {};
    for (const id of selectedArsenalIds.value) {
        const name = props.campaignArsenal.find((a) => a.id === id)?.name;
        if (name) nameCounts[name] = (nameCounts[name] ?? 0) + 1;
    }
    const seen: Record<string, number> = {};
    const isAdvanced = props.campaignOwnedEquipment.find((e) => e.id === equipmentId)?.is_advanced ?? false;

    return [
        { value: 'leader', label: 'Leader' },
        ...(props.campaignTotem ? [{ value: 'totem', label: props.campaignTotem.name }] : []),
        ...(isAdvanced
            ? []
            : selectedArsenalIds.value.map((id) => {
                  const name = props.campaignArsenal.find((a) => a.id === id)?.name ?? `#${id}`;
                  if ((nameCounts[name] ?? 0) > 1) {
                      seen[name] = (seen[name] ?? 0) + 1;

                      return { value: String(id), label: `${name} (${seen[name]})` };
                  }

                  return { value: String(id), label: name };
              })),
    ];
};
// Drop any assignment whose target was deselected from the crew.
watch(selectedArsenalIds, () => {
    const validTargets = new Set(['__none__', 'leader', 'totem', ...selectedArsenalIds.value.map(String)]);
    for (const key of Object.keys(equipmentTargets.value)) {
        if (!validTargets.has(equipmentTargets.value[key])) equipmentTargets.value[key] = '__none__';
    }
});

const confirmCampaignCrew = () => {
    const equipmentAssignments = equipmentSlots.value
        .map((slot) => ({ equipment_id: slot.equipmentId, target: equipmentTargets.value[slot.key] ?? '__none__' }))
        .filter((a) => a.target !== '__none__');
    emit('confirm-campaign-crew', selectedArsenalIds.value, equipmentAssignments);
};
</script>

<template>
    <Card class="mb-6" :class="isOpponentSetupPhase ? 'border-amber-500/40 bg-amber-500/5 dark:bg-amber-500/5' : ''">
        <CardContent class="p-4 sm:p-6">
            <h2 class="mb-1 text-lg font-semibold">
                {{ isSolo && crewStepDone ? "Opponent's Crew" : 'Select Your Crew' }}
                <Badge v-if="isOpponentSetupPhase" variant="outline" class="ml-1 border-amber-500/50 text-[10px] text-amber-600 dark:text-amber-400"
                    >Opponent</Badge
                >
            </h2>
            <p v-if="crewStepDone && !isSolo" class="mb-4 text-xs text-muted-foreground">
                <Loader2 class="mr-1 inline size-3 animate-spin" /> Waiting for opponent...
            </p>
            <template v-else>
                <template v-if="isCampaign">
                    <p class="mb-2 text-xs text-muted-foreground">
                        Select models from your campaign arsenal to bring to this game ({{ game.encounter_size }}ss budget, leader and totem are
                        free).
                    </p>
                </template>
                <template v-else>
                    <p class="mb-2 text-xs text-muted-foreground">
                        Choose a saved crew for <strong class="text-foreground">{{ myPlayer?.master_name?.split(',')[0] }}</strong> or
                        <Link :href="newCrewUrl" class="text-primary underline">create a new one</Link>.
                    </p>
                    <div v-if="masterTitleOptions.length > 1" class="mb-4 flex flex-wrap items-center gap-1.5">
                        <span class="text-[11px] text-muted-foreground">Filter:</span>
                        <button
                            class="rounded-md border px-2 py-0.5 text-[11px] transition-colors"
                            :class="!filterTitleId ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-muted'"
                            @click="filterTitleId = null"
                        >
                            All
                        </button>
                        <button
                            v-for="title in masterTitleOptions"
                            :key="title.id"
                            class="rounded-md border px-2 py-0.5 text-[11px] transition-colors"
                            :class="filterTitleId === title.id ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-muted'"
                            @click="filterTitleId = title.id"
                        >
                            {{ title.title || title.display_name }}
                        </button>
                    </div>
                </template>
            </template>

            <!-- Campaign: inline arsenal picker -->
            <template v-if="isCampaign && !crewStepDone">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <span class="text-xs font-medium" :class="campaignOverBudget ? 'text-destructive' : 'text-foreground'">
                        {{ campaignTotalCost }} / {{ game.encounter_size }}ss
                        <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                        <span v-if="campaignOverBudget" class="ml-1 text-[10px]">Over budget</span>
                    </span>
                    <Button :disabled="submitting || campaignOverBudget" size="sm" @click="confirmCampaignCrew">
                        <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                        Confirm Crew
                    </Button>
                </div>
                <div v-if="campaignArsenal.length" class="space-y-1">
                    <div
                        v-for="m in campaignArsenal"
                        :key="m.id"
                        class="flex cursor-pointer items-center justify-between rounded-md border px-3 py-2 text-sm transition-colors"
                        :class="selectedArsenalIds.includes(m.id) ? 'border-primary bg-primary/10' : 'hover:bg-muted/50'"
                        @click="toggleArsenalModel(m.id)"
                    >
                        <div class="flex min-w-0 items-center gap-2">
                            <div
                                class="size-4 shrink-0 rounded border-2 transition-colors"
                                :class="selectedArsenalIds.includes(m.id) ? 'border-primary bg-primary' : 'border-muted-foreground'"
                            />
                            <span class="truncate font-medium">{{ m.name }}</span>
                            <span v-if="campaignArsenalCopyLabel[m.id]" class="shrink-0 text-[10px] text-muted-foreground"
                                >({{ campaignArsenalCopyLabel[m.id] }})</span
                            >
                            <Badge
                                v-if="m.is_ook"
                                variant="outline"
                                class="shrink-0 border-amber-500/50 px-1 py-0 text-[9px] text-amber-600 dark:text-amber-400"
                                >OOK</Badge
                            >
                            <Badge v-if="m.is_peon" variant="outline" class="shrink-0 px-1 py-0 text-[9px]">Peon</Badge>
                        </div>
                        <div class="ml-2 shrink-0 font-bold">
                            {{ m.effective_cost }}<GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                            <span v-if="m.is_ook" class="ml-0.5 text-[9px] font-normal text-muted-foreground">({{ m.cost }}+1)</span>
                        </div>
                    </div>
                </div>
                <div v-else class="py-6 text-center text-sm text-muted-foreground">No models in your campaign arsenal yet.</div>

                <!-- Owned equipment: optional assignment to the Leader or a hired model (pg 19) -->
                <div v-if="equipmentSlots.length" class="mt-4 border-t pt-3">
                    <p class="mb-2 text-xs font-medium text-muted-foreground">Assign owned equipment (optional):</p>
                    <div class="space-y-1.5">
                        <div v-for="slot in equipmentSlots" :key="slot.key" class="flex items-center justify-between gap-2 text-sm">
                            <span class="min-w-0 truncate"
                                >{{ slot.name }} <span v-if="slot.copyLabel" class="text-xs text-muted-foreground">({{ slot.copyLabel }})</span>
                                <Badge
                                    v-if="campaignOwnedEquipment.find((e) => e.id === slot.equipmentId)?.is_advanced"
                                    variant="outline"
                                    class="ml-1 text-[9px]"
                                    title="Has an advancement tied to it — Leader/Totem only"
                                >
                                    Advanced
                                </Badge>
                            </span>
                            <Select
                                :model-value="equipmentTargets[slot.key] ?? '__none__'"
                                @update:model-value="(v) => (equipmentTargets[slot.key] = v as string)"
                            >
                                <SelectTrigger class="h-8 w-40 shrink-0 text-xs">
                                    <SelectValue placeholder="Unassigned" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__none__">Unassigned</SelectItem>
                                    <SelectItem v-for="opt in equipmentTargetOptionsFor(slot.equipmentId)" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Standard: saved crew builds picker -->
            <template v-else-if="!isCampaign && !crewStepDone">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <span class="text-xs text-muted-foreground">{{ matchingCrews.length }} saved crews</span>
                    <Link :href="newCrewUrl">
                        <Button size="sm" class="gap-1.5">
                            <Plus class="size-3.5" />
                            Create New Crew
                        </Button>
                    </Link>
                </div>
                <div v-if="matchingCrews.length" class="grid gap-2.5 sm:grid-cols-2">
                    <div v-for="crew in matchingCrews" :key="crew.id">
                        <Card
                            class="transition-all duration-200"
                            :class="[
                                expandedCrewId === crew.id ? 'shadow-md ring-1 ring-primary/50' : CARD_HOVER_PROMINENT,
                                crew.is_over_budget ? 'border-destructive/50' : '',
                            ]"
                        >
                            <!-- Card header -->
                            <CardContent
                                class="flex cursor-pointer items-start gap-3 p-3"
                                @click="expandedCrewId = expandedCrewId === crew.id ? null : crew.id"
                            >
                                <FactionLogo :faction="crew.faction" class-name="size-7 shrink-0 mt-0.5" />
                                <div class="min-w-0 flex-1">
                                    <p class="break-words text-sm font-medium leading-tight">{{ crew.name }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-1">
                                        <Badge v-if="crew.master_name" variant="secondary" class="text-[10px]">{{ crew.master_name }}</Badge>
                                        <Badge variant="secondary" class="text-[10px]">{{ crew.encounter_size }}ss</Badge>
                                        <Badge v-if="crew.is_over_budget" variant="destructive" class="text-[10px]">Over Budget</Badge>
                                    </div>
                                </div>
                                <ChevronDown
                                    class="mt-1 size-4 shrink-0 text-muted-foreground transition-transform duration-200"
                                    :class="expandedCrewId === crew.id ? 'rotate-180' : ''"
                                />
                            </CardContent>

                            <!-- Expanded details -->
                            <div v-if="expandedCrewId === crew.id" class="border-t px-3 pb-3 pt-2">
                                <!-- Stats -->
                                <div class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                    <span>
                                        Spent:
                                        <span class="font-medium text-foreground" :class="crew.is_over_budget ? 'text-destructive' : ''">
                                            {{ crew.total_spent }}/{{ game.encounter_size }}
                                        </span>
                                        <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                    </span>
                                    <span>
                                        Pool: <span class="font-medium text-foreground">{{ crew.soulstone_pool }}</span>
                                        <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                    </span>
                                    <span>
                                        OOK:
                                        <span
                                            class="font-medium text-foreground"
                                            :class="crew.ook_count >= 2 ? 'text-amber-600 dark:text-amber-400' : ''"
                                        >
                                            {{ crew.ook_count }}/2
                                        </span>
                                    </span>
                                </div>

                                <!-- Member list -->
                                <div class="space-y-0.5">
                                    <div
                                        v-for="(member, mIdx) in crew.members"
                                        :key="mIdx"
                                        :class="factionBackground(member.faction)"
                                        class="flex items-center justify-between rounded px-2 py-1 text-xs text-white"
                                    >
                                        <div class="flex min-w-0 items-center gap-1.5">
                                            <span class="truncate font-medium">{{ member.display_name }}</span>
                                            <Badge :class="categoryColor(member.category)" class="shrink-0 px-1 py-0 text-[9px]">
                                                {{ categoryLabel(member.category) }}
                                            </Badge>
                                        </div>
                                        <div v-if="member.effective_cost > 0" class="flex shrink-0 items-center font-bold">
                                            <template v-if="member.category === 'ook'">
                                                {{ member.effective_cost }}
                                                <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                                <span class="ml-0.5 text-[9px] font-normal text-red-300">({{ member.cost }}+1)</span>
                                            </template>
                                            <template v-else>
                                                {{ member.effective_cost }}
                                                <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 flex gap-2">
                                    <Button
                                        class="flex-1"
                                        size="sm"
                                        :disabled="submitting || crew.is_over_budget"
                                        @click="$emit('confirm', { crew_build_id: crew.id, ...(isSolo ? { slot: mySlot } : {}) })"
                                    >
                                        <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                                        {{ crew.is_over_budget ? 'Over Budget' : 'Select This Crew' }}
                                    </Button>
                                    <Link :href="route('tools.crew_builder.editor') + '?build=' + crew.share_code">
                                        <Button variant="outline" size="sm" class="gap-1">
                                            <Pencil class="size-3" />
                                            Edit
                                        </Button>
                                    </Link>
                                </div>
                            </div>
                        </Card>
                    </div>
                </div>
                <div v-else class="py-6 text-center text-sm text-muted-foreground">No saved crews for this faction yet.</div>
            </template>
            <template v-else-if="!isSolo || opponentCrewStepDone">
                <div class="py-4 text-center text-sm text-muted-foreground"><Check class="inline size-5 text-green-500" /> Crew selected</div>
            </template>

            <!-- Solo: opponent crew (optional) -->
            <template v-else-if="isSolo && crewStepDone && !opponentCrewStepDone">
                <div class="mb-3 text-center text-sm text-muted-foreground"><Check class="inline size-4 text-green-500" /> Your crew selected</div>
                <p class="mb-3 text-xs text-muted-foreground">
                    Optionally select a saved crew for
                    <strong class="text-foreground">{{ opponentPlayer?.master_name?.split(',')[0] }}</strong
                    >, or skip to track points only.
                </p>
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <span class="text-xs text-muted-foreground">{{ opponentMatchingCrews.length }} saved crews</span>
                    <Link :href="newOpponentCrewUrl">
                        <Button size="sm" class="gap-1.5">
                            <Plus class="size-3.5" />
                            Create New Crew
                        </Button>
                    </Link>
                </div>
                <div v-if="opponentTitleOptions.length > 1" class="mb-4 flex flex-wrap items-center gap-1.5">
                    <span class="text-[11px] text-muted-foreground">Filter:</span>
                    <button
                        class="rounded-md border px-2 py-0.5 text-[11px] transition-colors"
                        :class="!opponentFilterTitleId ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-muted'"
                        @click="opponentFilterTitleId = null"
                    >
                        All
                    </button>
                    <button
                        v-for="title in opponentTitleOptions"
                        :key="title.id"
                        class="rounded-md border px-2 py-0.5 text-[11px] transition-colors"
                        :class="opponentFilterTitleId === title.id ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-muted'"
                        @click="opponentFilterTitleId = title.id"
                    >
                        {{ title.title || title.display_name }}
                    </button>
                </div>
                <div v-if="opponentMatchingCrews.length" class="mb-3 grid gap-2.5 sm:grid-cols-2">
                    <div v-for="crew in opponentMatchingCrews" :key="crew.id">
                        <Card
                            class="transition-all duration-200"
                            :class="[
                                expandedOpponentCrewId === crew.id ? 'shadow-md ring-1 ring-primary/50' : CARD_HOVER_PROMINENT,
                                crew.is_over_budget ? 'border-destructive/50' : '',
                            ]"
                        >
                            <CardContent
                                class="flex cursor-pointer items-start gap-3 p-3"
                                @click="expandedOpponentCrewId = expandedOpponentCrewId === crew.id ? null : crew.id"
                            >
                                <FactionLogo :faction="crew.faction" class-name="size-7 shrink-0 mt-0.5" />
                                <div class="min-w-0 flex-1">
                                    <p class="break-words text-sm font-medium leading-tight">{{ crew.name }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-1">
                                        <Badge v-if="crew.master_name" variant="secondary" class="text-[10px]">{{ crew.master_name }}</Badge>
                                        <Badge variant="secondary" class="text-[10px]">{{ crew.encounter_size }}ss</Badge>
                                        <Badge v-if="crew.is_over_budget" variant="destructive" class="text-[10px]">Over Budget</Badge>
                                    </div>
                                </div>
                                <ChevronDown
                                    class="mt-1 size-4 shrink-0 text-muted-foreground transition-transform duration-200"
                                    :class="expandedOpponentCrewId === crew.id ? 'rotate-180' : ''"
                                />
                            </CardContent>

                            <div v-if="expandedOpponentCrewId === crew.id" class="border-t px-3 pb-3 pt-2">
                                <div class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                    <span>
                                        Spent:
                                        <span class="font-medium text-foreground" :class="crew.is_over_budget ? 'text-destructive' : ''">
                                            {{ crew.total_spent }}/{{ game.encounter_size }}
                                        </span>
                                        <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                    </span>
                                    <span>
                                        Pool: <span class="font-medium text-foreground">{{ crew.soulstone_pool }}</span>
                                        <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                    </span>
                                    <span>
                                        OOK:
                                        <span
                                            class="font-medium text-foreground"
                                            :class="crew.ook_count >= 2 ? 'text-amber-600 dark:text-amber-400' : ''"
                                        >
                                            {{ crew.ook_count }}/2
                                        </span>
                                    </span>
                                </div>
                                <div class="space-y-0.5">
                                    <div
                                        v-for="(member, mIdx) in crew.members"
                                        :key="mIdx"
                                        :class="factionBackground(member.faction)"
                                        class="flex items-center justify-between rounded px-2 py-1 text-xs text-white"
                                    >
                                        <div class="flex min-w-0 items-center gap-1.5">
                                            <span class="truncate font-medium">{{ member.display_name }}</span>
                                            <Badge :class="categoryColor(member.category)" class="shrink-0 px-1 py-0 text-[9px]">
                                                {{ categoryLabel(member.category) }}
                                            </Badge>
                                        </div>
                                        <div v-if="member.effective_cost > 0" class="flex shrink-0 items-center font-bold">
                                            <template v-if="member.category === 'ook'">
                                                {{ member.effective_cost }}
                                                <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                                <span class="ml-0.5 text-[9px] font-normal text-red-300">({{ member.cost }}+1)</span>
                                            </template>
                                            <template v-else>
                                                {{ member.effective_cost }}
                                                <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                            </template>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <Button
                                        class="w-full"
                                        size="sm"
                                        :disabled="submitting || crew.is_over_budget"
                                        @click="$emit('confirm', { crew_build_id: crew.id, slot: opponentSlot })"
                                    >
                                        <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                                        {{ crew.is_over_budget ? 'Over Budget' : 'Select This Crew' }}
                                    </Button>
                                </div>
                            </div>
                        </Card>
                    </div>
                </div>
                <!-- Title selection required before skipping -->
                <div v-if="opponentTitleOptions.length > 1" class="mt-3 rounded-md border p-3">
                    <div class="mb-2 text-xs font-medium text-muted-foreground">Select opponent's title before skipping:</div>
                    <div class="mb-2 flex flex-wrap gap-1.5">
                        <button
                            v-for="title in opponentTitleOptions"
                            :key="'skip-title-' + title.id"
                            class="rounded-md border px-2 py-0.5 text-[11px] transition-colors"
                            :class="selectedOpponentTitleForSkip === title.id ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-muted'"
                            @click="selectedOpponentTitleForSkip = title.id"
                        >
                            {{ title.title || title.display_name }}
                        </button>
                    </div>
                    <Button variant="outline" class="w-full" :disabled="!selectedOpponentTitleForSkip" @click="skipOpponentCrew">
                        Skip Opponent Crew
                    </Button>
                </div>
                <Button v-else variant="outline" class="w-full" @click="skipOpponentCrew"> Skip Opponent Crew </Button>
            </template>
        </CardContent>
    </Card>
</template>
