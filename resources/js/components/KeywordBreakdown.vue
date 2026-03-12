<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import UpgradeCardView from '@/components/UpgradeCardView.vue';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    keyword: {
        type: Object,
        required: true,
    },
    statistics: {
        type: Object,
        required: false,
        default: () => ({}),
    },
});

const keywordName = computed(() => props.keyword?.keyword?.name ?? '');
const masters = computed(() => props.keyword?.masters ?? []);
const totemIds = computed(() => new Set(masters.value.map((m: any) => m.has_totem_id).filter(Boolean)));
const characters = computed(() => (props.keyword?.characters ?? []).filter((c: any) => !totemIds.value.has(c.id)));
const hasStats = computed(() => props.statistics && Object.keys(props.statistics).length > 0);

const charCount = computed(() => characters.value.length);
const { delays } = useStaggeredEntry(charCount);

const stationOrder = ['henchman', 'unique', 'minion', 'peon'];

const stationLabels: Record<string, string> = {
    henchman: 'Henchmen',
    unique: 'Unique',
    minion: 'Minions',
    peon: 'Peons',
};

const groupedCharacters = computed(() => {
    const groups: Record<string, any[]> = {};
    for (const char of characters.value) {
        const station = char.station ?? 'unique';
        if (!groups[station]) groups[station] = [];
        groups[station].push(char);
    }
    const ordered: { station: string; label: string; characters: any[] }[] = [];
    for (const s of stationOrder) {
        if (groups[s]) {
            ordered.push({ station: s, label: stationLabels[s] ?? s.charAt(0).toUpperCase() + s.slice(1) + 's', characters: groups[s] });
        }
    }
    for (const [s, chars] of Object.entries(groups)) {
        if (!stationOrder.includes(s)) {
            ordered.push({ station: s, label: stationLabels[s] ?? s.charAt(0).toUpperCase() + s.slice(1) + 's', characters: chars });
        }
    }
    return ordered;
});

const statItems = computed(() => {
    if (!hasStats.value) return [];
    return [
        { label: 'Avg Cost', value: props.statistics.avg_cost },
        { label: 'Avg HP', value: props.statistics.avg_health },
        { label: 'Avg Spd', value: props.statistics.avg_speed },
        { label: 'Avg Def', value: props.statistics.avg_defense },
        { label: 'Avg Wp', value: props.statistics.avg_willpower },
    ].filter((s) => s.value != null);
});

const stationCounts = computed(() => {
    if (!hasStats.value) return [];
    return [
        { label: 'Masters', value: props.statistics.total_masters },
        { label: 'Henchmen', value: props.statistics.total_henchmen },
        { label: 'Unique', value: props.statistics.total_unique },
        { label: 'Minions', value: props.statistics.total_minions },
        { label: 'Peons', value: props.statistics.total_peons },
    ].filter((s) => s.value > 0);
});

const groupOffsets = computed(() => {
    const offsets: number[] = [];
    let running = 0;
    for (const group of groupedCharacters.value) {
        offsets.push(running);
        running += group.characters.length;
    }
    return offsets;
});
</script>

<template>
    <div class="space-y-4">
        <!-- Header: keyword name + stats -->
        <div class="rounded-lg border bg-card p-3 sm:p-4">
            <div class="mb-2 text-lg font-semibold">{{ keywordName }}</div>
            <div v-if="hasStats" class="flex flex-wrap items-center gap-x-5 gap-y-2">
                <div v-if="statistics.factions?.length" class="flex items-center gap-1.5">
                    <Link v-for="f in statistics.factions" :key="f.value" :href="route('factions.view', f.value)">
                        <Badge variant="secondary" class="cursor-pointer gap-1.5 transition-colors hover:bg-accent">
                            <FactionLogo :faction="f.value" class-name="h-4 w-4" />
                            {{ f.name }}
                        </Badge>
                    </Link>
                </div>
                <div v-if="stationCounts.length" class="flex items-center gap-1.5">
                    <Badge v-for="s in stationCounts" :key="s.label" variant="outline" class="text-xs"> {{ s.value }} {{ s.label }} </Badge>
                </div>
                <div v-if="statItems.length" class="flex items-center gap-3">
                    <div v-for="stat in statItems" :key="stat.label" class="text-center">
                        <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">{{ stat.label }}</div>
                        <div class="text-sm font-bold leading-tight">{{ stat.value }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Masters: card + totem + crew upgrades -->
        <div v-if="masters.length">
            <Separator label="Masters" class="mb-3" />
            <div class="grid grid-cols-3 gap-2 sm:gap-3 md:grid-cols-4 lg:grid-cols-6">
                <template v-for="master in masters" :key="master.slug">
                    <div v-if="master.standard_miniatures?.[0]">
                        <CharacterCardView
                            :miniature="master.standard_miniatures[0]"
                            :character-slug="master.slug"
                            :all-miniature-ids="master.standard_miniatures.map((m: any) => m.id)"
                        />
                    </div>
                    <div v-if="master.totem?.standard_miniatures?.[0]">
                        <CharacterCardView
                            :miniature="master.totem.standard_miniatures[0]"
                            :character-slug="master.totem.slug"
                            :all-miniature-ids="master.totem.standard_miniatures.map((m: any) => m.id)"
                        />
                    </div>
                    <div v-for="upgrade in master.crew_upgrades ?? []" :key="upgrade.slug">
                        <UpgradeCardView :upgrade="upgrade" />
                    </div>
                </template>
            </div>
        </div>

        <!-- Non-master characters by station -->
        <div v-for="(group, groupIdx) in groupedCharacters" :key="group.station">
            <Separator :label="group.label" class="mb-3" />
            <div class="grid grid-cols-3 gap-2 sm:gap-3 md:grid-cols-4 lg:grid-cols-6">
                <div
                    v-for="(character, charIdx) in group.characters"
                    :key="character.slug"
                    class="animate-fade-in-up opacity-0"
                    :style="delays[groupOffsets[groupIdx] + charIdx] ?? {}"
                >
                    <CharacterCardView
                        v-if="character.standard_miniatures?.[0]"
                        :miniature="character.standard_miniatures[0]"
                        :character-slug="character.slug"
                        :all-miniature-ids="character.standard_miniatures.map((m: any) => m.id)"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
