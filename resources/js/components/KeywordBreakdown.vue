<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import UpgradeCardView from '@/components/UpgradeCardView.vue';
import { Separator } from '@/components/ui/separator';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { computed } from 'vue';

const props = defineProps({
    keyword: {
        type: Object,
        required: true,
    },
});

const masters = computed(() => props.keyword?.masters ?? []);
const totemIds = computed(() => new Set(masters.value.map((m: any) => m.has_totem_id).filter(Boolean)));
const characters = computed(() => (props.keyword?.characters ?? []).filter((c: any) => !totemIds.value.has(c.id)));

const charCount = computed(() => characters.value.length);
const { delays } = useStaggeredEntry(charCount);

const stationOrder = ['unique', 'minion', 'peon'];

const stationLabels: Record<string, string> = {
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
        <!-- Masters: card + totem + crew upgrades -->
        <div v-if="masters.length">
            <Separator label="Masters" class="mb-3" />
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 md:grid-cols-4 lg:grid-cols-6">
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
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 md:grid-cols-4 lg:grid-cols-6">
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
