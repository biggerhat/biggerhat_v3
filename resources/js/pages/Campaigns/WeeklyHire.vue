<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import GameIcon from '@/components/GameIcon.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useCampaignChannel } from '@/composables/useCampaignChannel';
import { factionBackground } from '@/composables/useFactionColor';
import { Head, Link, router } from '@inertiajs/vue3';
import { UserPlus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface KeywordRow {
    id: number;
    name: string;
}
interface CharRow {
    id: number;
    display_name: string;
    cost: number | null;
    faction: string;
    station: string;
    keywords?: KeywordRow[];
    characteristics?: { name: string }[];
}
interface CrewData {
    id: number;
    share_code: string;
    name: string;
    faction: string | null;
    scrip: number;
    keyword_1_id: number | null;
    keyword_2_id: number | null;
}
interface CampaignData {
    id: number;
    name: string;
    status: string;
    current_week: number;
    length_weeks: number;
}

const props = defineProps<{
    campaign: CampaignData;
    crew: CrewData;
    hireable: CharRow[];
    already_hired_this_week: number;
    locked: boolean;
}>();

interface HireRow {
    character_id: number;
    label: string | null;
    display_name: string;
    base_cost: number;
    out_of_keyword: boolean;
}

const hires = ref<HireRow[]>([]);
const filter = ref('');

// Real-time updates (T3-34) — another crew's changes don't affect this page,
// but a week advance (which gates `locked`/`already_hired_this_week`) does.
useCampaignChannel(props.campaign.id, ['campaign', 'crew', 'hireable', 'already_hired_this_week', 'locked']);

const isVersatile = (c: CharRow): boolean => c.characteristics?.some((ch) => ch.name.toLowerCase() === 'versatile') ?? false;

const isOutOfKeyword = (c: CharRow): boolean => {
    if (isVersatile(c)) return false;
    const keywordIds = [props.crew.keyword_1_id, props.crew.keyword_2_id].filter(Boolean);
    if (keywordIds.length === 0) return false;
    return !c.keywords?.some((k) => keywordIds.includes(k.id));
};

const getCategory = (c: CharRow): 'keyword' | 'versatile' | 'ook' => {
    const kwIds = [props.crew.keyword_1_id, props.crew.keyword_2_id].filter((id): id is number => id !== null);
    if (kwIds.length > 0 && c.keywords?.some((k) => kwIds.includes(k.id))) return 'keyword';
    if (isVersatile(c)) return 'versatile';
    return 'ook';
};

type PoolFilter = 'keyword' | 'versatile' | 'ook' | 'all';
const poolFilter = ref<PoolFilter>('all');

const filteredHireable = computed(() => {
    const f = filter.value.toLowerCase().trim();
    return props.hireable.filter((c) => {
        if (poolFilter.value !== 'all' && getCategory(c) !== poolFilter.value) return false;
        if (f && !c.display_name.toLowerCase().includes(f) && !c.keywords?.some((k) => k.name.toLowerCase().includes(f))) return false;
        return true;
    });
});

const poolFilterCounts = computed(() => ({
    keyword: props.hireable.filter((c) => getCategory(c) === 'keyword').length,
    versatile: props.hireable.filter((c) => getCategory(c) === 'versatile').length,
    ook: props.hireable.filter((c) => getCategory(c) === 'ook').length,
    all: props.hireable.length,
}));

// Compute the scrip cost of an individual hire given the running first-hire flag.
const scripCostFor = (h: HireRow, indexInHires: number): number => {
    let cost = h.base_cost;
    if (h.out_of_keyword) cost += 1;
    if (props.already_hired_this_week + indexInHires === 0) cost -= 5;

    return Math.max(0, cost);
};

const totalScripCost = computed(() => hires.value.reduce((sum, h, i) => sum + scripCostFor(h, i), 0));
const remaining = computed(() => props.crew.scrip - totalScripCost.value);

// What it would cost to hire this model next, given the current running hires
// list — mirrors StartingArsenal's per-row "Over budget" affordability check.
const costIfAddedNow = (c: CharRow): number =>
    scripCostFor({ character_id: c.id, label: null, display_name: c.display_name, base_cost: c.cost ?? 0, out_of_keyword: isOutOfKeyword(c) }, hires.value.length);

const canAfford = (c: CharRow): boolean => costIfAddedNow(c) <= remaining.value;

const addHire = (c: CharRow) => {
    if (props.locked || !canAfford(c)) return;
    hires.value.push({
        character_id: c.id,
        label: null,
        display_name: c.display_name,
        base_cost: c.cost ?? 0,
        out_of_keyword: isOutOfKeyword(c),
    });
};

const removeHire = (idx: number) => {
    if (props.locked) return;
    hires.value.splice(idx, 1);
};

const submit = () => {
    if (props.locked || hires.value.length === 0 || totalScripCost.value > props.crew.scrip) return;
    router.post(route('campaigns.crews.weekly-hire.update', [props.campaign.id, props.crew.share_code]), {
        hires: hires.value.map((h) => ({ character_id: h.character_id, label: h.label })),
    });
};
</script>

<template>
    <Head :title="`Weekly Hire — Week ${campaign.current_week}`" />

    <PageBanner :title="`Weekly Hire — Week ${campaign.current_week}`">
        <template #subtitle>
            <div class="px-2">
                <span class="text-sm text-muted-foreground">
                    {{ campaign.name }} • <strong class="text-foreground">{{ crew.name }}</strong> • mandatory: at least 1 hire
                </span>
                <p v-if="already_hired_this_week > 0" class="mt-1 text-xs text-amber-600">
                    Already hired {{ already_hired_this_week }} model(s) this week — first-hire discount no longer applies.
                </p>
            </div>
        </template>
        <template #actions>
            <div class="flex items-center px-2 py-2 md:py-4">
                <Link :href="route('campaigns.crews.arsenal.show', [campaign.id, crew.share_code])">
                    <Button variant="outline">← Back to Arsenal</Button>
                </Link>
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto max-w-6xl px-4 pb-16">
        <div class="mb-6 grid grid-cols-3 gap-3 rounded-lg border bg-muted/40 p-3 text-center text-sm">
            <div>
                <span class="block text-xs uppercase text-muted-foreground">Current Scrip</span><span class="tabular-nums">{{ crew.scrip }}</span>
            </div>
            <div :class="totalScripCost > crew.scrip ? 'text-destructive' : ''">
                <span class="block text-xs uppercase text-muted-foreground">Hire Cost</span>
                <span class="tabular-nums">{{ totalScripCost }}</span>
            </div>
            <div :class="remaining < 0 ? 'text-destructive' : ''">
                <span class="block text-xs uppercase text-muted-foreground">Remaining</span>
                <span class="tabular-nums">{{ remaining }}</span>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle>Hireable Models ({{ filteredHireable.length }})</CardTitle>
                    <Input v-model="filter" placeholder="Search by name or keyword…" class="mt-2" />
                    <div class="mt-1.5 flex flex-wrap items-center gap-1">
                        <Button
                            v-for="f in ['keyword', 'versatile', 'ook', 'all'] as const"
                            :key="f"
                            :variant="poolFilter === f ? 'default' : 'outline'"
                            size="sm"
                            class="h-6 gap-1 px-2 text-[11px]"
                            @click="poolFilter = f"
                        >
                            {{ { keyword: 'Keyword', versatile: 'Versatile', ook: 'OOK', all: 'All' }[f] }}
                            <span class="text-[10px] opacity-60">{{ poolFilterCounts[f] }}</span>
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <ul class="max-h-[60vh] space-y-0.5 overflow-y-auto pr-1">
                        <li
                            v-for="c in filteredHireable"
                            :key="c.id"
                            :class="[factionBackground(c.faction), locked || !canAfford(c) ? 'opacity-40' : '']"
                            class="rounded-md border border-white/20 text-white transition-colors hover:brightness-110"
                        >
                            <div class="flex items-center justify-between px-2 py-1.5">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold">{{ c.display_name }}</p>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="flex items-center text-sm font-bold text-white">
                                            {{ c.cost }}<GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                            <span v-if="isOutOfKeyword(c)" class="ml-0.5 text-xs font-normal text-red-300">+1</span>
                                        </span>
                                        <Badge variant="secondary" class="bg-white/15 px-1 py-0 text-[10px] capitalize text-white/90">
                                            {{ c.station }}
                                        </Badge>
                                        <Badge v-if="isOutOfKeyword(c)" class="bg-red-400/30 px-1 py-0 text-[10px] text-red-200"> OOK </Badge>
                                        <span v-if="c.keywords?.length" class="hidden truncate text-xs text-white/50 sm:inline">
                                            {{ c.keywords.map((k) => k.name).join(', ') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <span v-if="!locked && !canAfford(c)" class="text-[10px] text-white/50">Over budget</span>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-7 text-white hover:bg-white/10 hover:text-white"
                                        :disabled="locked || !canAfford(c)"
                                        @click="addHire(c)"
                                    >
                                        <UserPlus class="size-4" />
                                    </Button>
                                </div>
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    ><CardTitle>This Week's Hires ({{ hires.length }})</CardTitle></CardHeader
                >
                <CardContent>
                    <ul v-if="hires.length" class="space-y-1">
                        <li
                            v-for="(h, idx) in hires"
                            :key="`${h.character_id}-${idx}`"
                            class="flex items-center justify-between rounded-md border px-2 py-1.5 text-sm"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ h.display_name }}</p>
                                <Input v-model="h.label" placeholder="Label (e.g. 'Zombie A')" class="mt-1 h-7 text-xs" :disabled="locked" />
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge variant="outline" class="text-[10px] tabular-nums"> {{ scripCostFor(h, idx) }} scrip </Badge>
                                <Button variant="ghost" size="sm" :disabled="locked" @click="removeHire(idx)">×</Button>
                            </div>
                        </li>
                    </ul>
                    <EmptyState v-else compact title="No hires yet" description="Add from the left — at least one is required." />
                </CardContent>
            </Card>
        </div>

        <p v-if="totalScripCost > crew.scrip" class="mt-4 text-right text-xs text-destructive">
            Not enough scrip — needs {{ totalScripCost }}, have {{ crew.scrip }}.
        </p>
        <div class="mt-8 flex justify-end gap-2">
            <Link :href="route('campaigns.crews.arsenal.show', [campaign.id, crew.share_code])">
                <Button variant="outline">Cancel</Button>
            </Link>
            <Button :disabled="locked || hires.length === 0 || totalScripCost > crew.scrip" @click="submit"> Confirm Hires </Button>
        </div>
    </div>
</template>
