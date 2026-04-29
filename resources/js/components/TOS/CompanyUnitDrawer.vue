<script setup lang="ts">
import FlipCard from '@/components/TOS/FlipCard.vue';
import TosText from '@/components/TosText.vue';
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ArrowRight, Crown, Plus, Swords, UserMinus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface SpecialRule {
    id: number;
    slug: string;
    name: string;
}

interface Sculpt {
    id: number;
    slug: string;
    name: string | null;
    front_image: string | null;
    back_image: string | null;
    combination_image: string | null;
}

interface UnitMin {
    id: number;
    slug: string;
    name: string;
    title: string | null;
    scrip: number;
    restriction: string | null;
    description?: string | null;
    special_unit_rules: SpecialRule[];
    sculpts?: Sculpt[];
    hire_category?: 'direct' | 'neutral';
}

const props = defineProps<{
    open: boolean;
    unit: UnitMin | null;
    /** When mode is 'roster', show remove + sculpt-save actions; when 'pool', show hire actions. */
    mode: 'roster' | 'pool';
    /** The currently-saved sculpt id for this unit (roster mode only). */
    selectedSculptId?: number | null;
    /** Roster: this unit's commander state. Pool: ignored. */
    isCommander?: boolean;
    /** Pool: whether the company has any commander yet — drives the dual hire affordances. */
    hasCommander?: boolean;
    /** Pool: whether hiring this unit would exceed the budget. */
    unaffordable?: boolean;
    allegianceSlug?: string | null;
    allegianceColorSlug?: string | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'sculpt-change', sculptId: number): void;
    (e: 'hire', asCommander: boolean): void;
    (e: 'remove'): void;
}>();

const localOpen = computed({
    get: () => props.open,
    set: (v: boolean) => emit('update:open', v),
});

// Track the currently-displayed sculpt. Initialised from selectedSculptId
// (roster) or the first sculpt (pool), then updated as the user picks others.
const activeSculptId = ref<number | null>(null);

watch(
    () => [props.open, props.unit?.id],
    () => {
        if (!props.open || !props.unit) return;
        const initial = props.selectedSculptId ?? props.unit.sculpts?.[0]?.id ?? null;
        activeSculptId.value = initial;
    },
    { immediate: true },
);

const activeSculpt = computed<Sculpt | null>(() => {
    if (!props.unit) return null;
    return props.unit.sculpts?.find((s) => s.id === activeSculptId.value) ?? props.unit.sculpts?.[0] ?? null;
});

const hasMultipleSculpts = computed(() => (props.unit?.sculpts?.length ?? 0) > 1);

const isCommanderEligible = computed(
    () => props.unit?.special_unit_rules.some((r) => r.slug === 'commander') ?? false,
);

function pickSculpt(value: string) {
    const id = Number(value);
    activeSculptId.value = id;
    if (props.mode === 'roster') {
        emit('sculpt-change', id);
    }
}
</script>

<template>
    <Drawer v-model:open="localOpen">
        <DrawerContent>
            <div v-if="unit" class="mx-auto w-full max-w-md">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">
                        <span class="inline-flex items-center gap-1.5">
                            <Crown v-if="isCommander" class="size-4 text-amber-500" />
                            <span>{{ unit.name }}</span>
                            <span v-if="unit.title" class="text-sm font-normal italic text-muted-foreground">
                                — {{ unit.title }}
                            </span>
                        </span>
                    </DrawerTitle>
                    <div class="mt-1 flex flex-wrap items-center justify-center gap-1.5">
                        <Badge
                            class="bg-emerald-500/15 text-[10px] tabular-nums text-emerald-700 dark:text-emerald-400"
                        >{{ isCommander ? `+${unit.scrip}` : unit.scrip }} Scrip</Badge>
                        <Badge v-if="unit.restriction" variant="outline" class="text-[10px] capitalize">
                            Neutral · {{ unit.restriction }}
                        </Badge>
                        <Badge
                            v-for="r in unit.special_unit_rules"
                            :key="r.id"
                            variant="outline"
                            class="text-[10px]"
                        >{{ r.name }}</Badge>
                    </div>
                </DrawerHeader>

                <div class="flex min-h-0 flex-1 flex-col px-4 pb-2">
                    <!-- Sculpt picker -->
                    <div v-if="hasMultipleSculpts" class="mb-3 shrink-0">
                        <Select
                            :model-value="String(activeSculpt?.id ?? '')"
                            @update:model-value="(v) => pickSculpt(v as string)"
                        >
                            <SelectTrigger class="h-8 text-xs">
                                <SelectValue placeholder="Pick a sculpt…" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="s in unit.sculpts" :key="s.id" :value="String(s.id)">
                                    {{ s.name || `Sculpt #${s.id}` }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Flip card preview -->
                    <div class="mx-auto w-full max-w-[280px]">
                        <FlipCard
                            v-if="activeSculpt"
                            :key="activeSculpt.id"
                            :front-image="activeSculpt.front_image"
                            :back-image="activeSculpt.back_image"
                            :front-alt="`${unit.name} (Standard)`"
                            :back-alt="`${unit.name} (Glory)`"
                            :allegiance-slug="allegianceSlug"
                            :placeholder-icon="Swords"
                            :single-side="!activeSculpt.back_image"
                        />
                        <p
                            v-if="activeSculpt && (activeSculpt.front_image || activeSculpt.back_image)"
                            class="mt-2 text-center text-[10px] italic text-muted-foreground"
                        >
                            {{ activeSculpt.back_image ? 'Click the card to flip between Standard and Glory sides' : 'Standard side only' }}
                        </p>
                    </div>

                    <!-- Optional unit description -->
                    <p
                        v-if="unit.description"
                        class="mt-3 line-clamp-3 text-center text-[11px] text-muted-foreground"
                    >
                        <TosText :text="unit.description" />
                    </p>
                </div>

                <DrawerFooter class="shrink-0 pt-2">
                    <div class="flex flex-wrap items-center justify-center gap-2">
                        <a
                            v-if="activeSculpt"
                            :href="route('tos.units.view', activeSculpt.slug)"
                            target="_blank"
                            class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                        >
                            View full unit page <ArrowRight class="size-3" />
                        </a>
                    </div>
                    <div class="flex flex-wrap items-center justify-center gap-2">
                        <template v-if="mode === 'pool'">
                            <Button
                                v-if="!hasCommander && isCommanderEligible"
                                class="gap-1.5 bg-amber-500 hover:bg-amber-600"
                                @click="emit('hire', true)"
                            >
                                <Crown class="size-4" /> Add as Commander
                            </Button>
                            <Button
                                :disabled="hasCommander && unaffordable"
                                class="gap-1.5"
                                @click="emit('hire', false)"
                            >
                                <Plus class="size-4" />
                                {{ hasCommander && unaffordable ? 'Over budget' : 'Add to Roster' }}
                            </Button>
                        </template>
                        <template v-else>
                            <Button
                                v-if="!isCommander"
                                variant="outline"
                                class="gap-1.5 text-rose-600 hover:bg-rose-500/10 hover:text-rose-700"
                                @click="emit('remove')"
                            >
                                <UserMinus class="size-4" /> Remove
                            </Button>
                        </template>
                        <DrawerClose as-child>
                            <Button variant="ghost">Close</Button>
                        </DrawerClose>
                    </div>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>
</template>
