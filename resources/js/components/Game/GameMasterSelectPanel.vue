<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Check, Loader2, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface MasterTitle {
    id: number;
    display_name: string | null;
    title?: string | null;
    bonanza_cost?: number;
}
interface MasterOption {
    name: string;
    faction: string;
    second_faction: string | null;
    is_alternate_leader: boolean;
    front_image: string | null;
    titles: MasterTitle[];
}

const props = defineProps<{
    masters: MasterOption[];
    /** Campaign games only: the user's own built leader, separate from the
     *  catalog masters. Used for the user's own pick; opponent pick uses masters. */
    campaignLeaderOption?: MasterOption | null;
    myFaction: string | null;
    opponentFaction: string | null;
    isBonanza: boolean;
    isCampaign: boolean;
    isSolo: boolean;
    submitting: boolean;
    mySlot: number;
    isOpponentSetupPhase: boolean;
    masterStepDone: boolean;
    opponentMasterStepDone: boolean;
    /** The local player's confirmed master name (shown once locked in). */
    myMasterName: string | null;
}>();

// Shared with Crew Select (title is picked there for 2-player multi-title
// masters) and reset by the parent's status watcher — so these stay parent-owned.
const selectedMasterName = defineModel<string | null>('selectedMasterName', { required: true });
const selectedMasterTitle = defineModel<string | null>('selectedMasterTitle', { required: true });
const selectedOpponentMasterName = defineModel<string | null>('selectedOpponentMasterName', { required: true });

const emit = defineEmits<{
    /** Submit the local player's master (parent owns the POST + reload). */
    confirm: [body: Record<string, unknown>];
    /** Submit the opponent's master in solo setup. */
    'confirm-opponent': [masterName: string];
}>();

const availableMasters = computed(() => {
    if (props.isCampaign) {
        // Campaign: the user's own pick is their built leader, not a catalog master.
        return props.campaignLeaderOption ? [props.campaignLeaderOption] : [];
    }
    if (!props.myFaction) return [];
    const f = props.myFaction;
    return props.masters.filter((m) => m.faction === f || m.second_faction === f || m.is_alternate_leader);
});

const masterSearchQuery = ref('');

const filteredMasters = computed(() => {
    const q = masterSearchQuery.value.trim().toLowerCase();
    const list = availableMasters.value;
    if (!q) return list;
    return list.filter((m) => {
        if (m.name.toLowerCase().includes(q)) return true;
        return (m.titles ?? []).some((t) => (t.title ?? '').toLowerCase().includes(q) || (t.display_name ?? '').toLowerCase().includes(q));
    });
});

const pickMaster = (master: MasterOption) => {
    selectedMasterName.value = master.name;
    // Only Bonanza requires the title at master-select (no crew step follows).
    // Other formats defer the title pick to crew select, so we don't block here.
    if (!props.isBonanza || master.titles.length <= 1) {
        selectedMasterTitle.value = master.titles[0]?.display_name ?? master.name;
    } else {
        selectedMasterTitle.value = null;
    }
};

const masterRequiresTitle = computed(() => {
    if (!props.isBonanza || !selectedMasterName.value) return false;
    const m = availableMasters.value.find((x) => x.name === selectedMasterName.value);
    return (m?.titles?.length ?? 0) > 1;
});

const confirmMasterSelection = () => {
    if (!selectedMasterName.value) return;
    if (masterRequiresTitle.value && !selectedMasterTitle.value) return;
    // Standard games submit the base name (title resolved during crew select).
    // Bonanza submits the specific title display_name.
    const body: Record<string, unknown> = {
        master_name: props.isBonanza ? (selectedMasterTitle.value ?? selectedMasterName.value) : selectedMasterName.value,
    };
    if (props.isSolo) body.slot = props.mySlot;
    emit('confirm', body);
};

const opponentAvailableMasters = computed(() => {
    const f = props.opponentFaction;
    if (!f) return [];
    return props.masters.filter((m) => m.faction === f || m.second_faction === f || m.is_alternate_leader);
});

const confirmOpponentMasterSelection = () => {
    if (!selectedOpponentMasterName.value) return;
    emit('confirm-opponent', selectedOpponentMasterName.value);
};
</script>

<template>
    <Card class="mb-6" :class="isOpponentSetupPhase ? 'border-amber-500/40 bg-amber-500/5 dark:bg-amber-500/5' : ''">
        <CardContent class="p-4 sm:p-6">
            <h2 class="mb-1 font-semibold">
                <template v-if="isBonanza">
                    {{ isSolo && masterStepDone ? "Select Opponent's Model" : 'Select Your Model' }}
                </template>
                <template v-else>
                    {{ isSolo && masterStepDone ? "Select Opponent's Master" : 'Select Your Master' }}
                </template>
                <Badge v-if="isOpponentSetupPhase" variant="outline" class="ml-1 border-amber-500/50 text-[10px] text-amber-600 dark:text-amber-400"
                    >Opponent</Badge
                >
            </h2>
            <p v-if="masterStepDone && !isSolo" class="mb-4 text-xs text-muted-foreground">
                <Loader2 class="mr-1 inline size-3 animate-spin" /> Waiting for opponent...
            </p>
            <p v-else-if="!masterStepDone" class="mb-4 text-xs text-muted-foreground">
                <template v-if="isBonanza">
                    Pick any single model from your faction within 11ss. Totems and dash-cost models cost
                    <span class="font-medium">max wounds − 1 (capped at 10)</span>.
                </template>
                <template v-else>Choose the master for your crew.</template>
            </p>
            <p v-else class="mb-4 text-xs text-muted-foreground">
                <template v-if="isBonanza">Choose the model for the opponent.</template>
                <template v-else>Choose the master for the opponent.</template>
            </p>

            <template v-if="!masterStepDone">
                <div class="sticky top-0 z-10 -mx-3 mb-3 bg-background/95 px-3 pb-2 pt-1 backdrop-blur sm:-mx-0 sm:px-0">
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-2 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="masterSearchQuery" placeholder="Search by name or title…" class="h-9 pl-7 text-sm" />
                    </div>
                    <div class="mt-1 text-[11px] text-muted-foreground">
                        {{ filteredMasters.length }} of {{ availableMasters.length }}
                        {{ availableMasters.length === 1 ? 'master' : 'masters' }}
                    </div>
                </div>

                <div
                    v-if="filteredMasters.length === 0"
                    class="rounded-md border border-dashed bg-muted/30 p-6 text-center text-sm text-muted-foreground"
                >
                    <template v-if="isCampaign && availableMasters.length === 0">
                        Build your campaign leader before selecting crew.
                    </template>
                    <template v-else>
                        No masters match "{{ masterSearchQuery }}".
                    </template>
                </div>
                <!-- pb-24: room under the last row for the floating confirm bar. -->
                <div v-else class="grid grid-cols-1 gap-3 pb-24 sm:grid-cols-2 lg:grid-cols-3">
                    <Card
                        v-for="master in filteredMasters"
                        :key="master.name"
                        class="cursor-pointer transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md hover:ring-1 hover:ring-primary/50"
                        :class="selectedMasterName === master.name ? 'ring-2 ring-primary' : ''"
                        @click="pickMaster(master)"
                    >
                        <CardContent class="flex items-start gap-3 p-3">
                            <div v-if="master.front_image" class="shrink-0 overflow-hidden rounded-md">
                                <img
                                    :src="'/storage/' + master.front_image"
                                    :alt="master.name"
                                    class="size-16 object-cover object-top"
                                    loading="lazy"
                                    decoding="async"
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-sm font-semibold">{{ master.name }}</span>
                                    <Badge
                                        v-if="master.is_alternate_leader"
                                        variant="outline"
                                        class="border-cyan-500/50 px-1 py-0 text-[9px] text-cyan-600 dark:text-cyan-400"
                                    >
                                        Alt Leader
                                    </Badge>
                                    <!-- Bonanza-only: cost hint per the format's totem/peon derivation. -->
                                    <Badge
                                        v-if="isBonanza && master.titles.length === 1 && master.titles[0].bonanza_cost !== undefined"
                                        variant="outline"
                                        class="border-purple-500/50 px-1 py-0 text-[9px] text-purple-600 dark:text-purple-300"
                                        >{{ master.titles[0].bonanza_cost }}ss</Badge
                                    >
                                </div>
                                <div v-if="!isBonanza && master.titles.length > 1" class="mt-0.5 text-[10px] text-muted-foreground">
                                    {{ master.titles.length }} titles — choose during crew select
                                </div>
                                <div v-else-if="isBonanza && master.titles.length > 1" class="mt-1 flex flex-wrap gap-1">
                                    <button
                                        v-for="t in master.titles"
                                        :key="t.id"
                                        type="button"
                                        class="rounded border px-1.5 py-0.5 text-[10px] transition-colors"
                                        :class="
                                            selectedMasterName === master.name && selectedMasterTitle === (t.display_name ?? master.name)
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-purple-500/40 text-purple-700 hover:bg-purple-500/10 dark:text-purple-300'
                                        "
                                        @click.stop="
                                            selectedMasterName = master.name;
                                            selectedMasterTitle = t.display_name ?? master.name;
                                        "
                                    >
                                        {{ t.title || t.display_name }} · {{ t.bonanza_cost }}ss
                                    </button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    leave-active-class="transition duration-150 ease-in"
                    enter-from-class="translate-y-4 opacity-0"
                    leave-to-class="translate-y-4 opacity-0"
                >
                    <div
                        v-if="selectedMasterName"
                        class="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 px-4 py-3 shadow-lg backdrop-blur sm:inset-x-auto sm:bottom-6 sm:left-1/2 sm:-translate-x-1/2 sm:rounded-lg sm:border sm:py-2"
                    >
                        <div class="mx-auto flex w-full max-w-md items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-[10px] uppercase tracking-wider text-muted-foreground">Selected</div>
                                <div class="truncate text-sm font-medium">{{ selectedMasterTitle ?? selectedMasterName }}</div>
                                <div v-if="masterRequiresTitle && !selectedMasterTitle" class="text-[11px] text-amber-600 dark:text-amber-400">
                                    Pick a title to continue
                                </div>
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-9"
                                @click="
                                    selectedMasterName = null;
                                    selectedMasterTitle = null;
                                "
                                >Clear</Button
                            >
                            <Button
                                :disabled="submitting || (masterRequiresTitle && !selectedMasterTitle)"
                                class="h-9"
                                @click="confirmMasterSelection"
                            >
                                <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                                Confirm
                            </Button>
                        </div>
                    </div>
                </Transition>
            </template>
            <!-- My master done -->
            <template v-else-if="!isSolo || opponentMasterStepDone">
                <div class="py-4 text-center">
                    <Badge variant="secondary" class="text-sm">{{ myMasterName }}</Badge>
                    <Check class="ml-2 inline size-5 text-green-500" />
                </div>
            </template>

            <!-- Solo: pick opponent master -->
            <template v-else-if="isSolo && masterStepDone && !opponentMasterStepDone">
                <div class="mb-3">
                    <Badge variant="secondary" class="text-sm">{{ myMasterName }}</Badge>
                    <Check class="ml-2 inline size-4 text-green-500" />
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Card
                        v-for="master in opponentAvailableMasters"
                        :key="master.name"
                        class="cursor-pointer transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md hover:ring-1 hover:ring-primary/50"
                        :class="selectedOpponentMasterName === master.name ? 'ring-2 ring-primary' : ''"
                        @click="selectedOpponentMasterName = master.name"
                    >
                        <CardContent class="flex items-start gap-3 p-3">
                            <div v-if="master.front_image" class="shrink-0 overflow-hidden rounded-md">
                                <img
                                    :src="'/storage/' + master.front_image"
                                    :alt="master.name"
                                    class="size-16 object-cover object-top"
                                    loading="lazy"
                                    decoding="async"
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <span class="text-sm font-semibold">{{ master.name }}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>
                <div v-if="selectedOpponentMasterName" class="mt-4 flex justify-center">
                    <Button :disabled="submitting" @click="confirmOpponentMasterSelection">
                        <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                        Confirm {{ selectedOpponentMasterName }}
                    </Button>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
