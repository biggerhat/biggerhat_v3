<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import EmptyState from '@/components/EmptyState.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import SearchableMultiselect from '@/components/SearchableMultiselect.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Head, router } from '@inertiajs/vue3';
import { Dices, Eraser } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface FactionOption {
    value: string;
    name: string;
    logo: string;
}

interface OptionEntry {
    value: string;
    name: string;
}

interface PickedMiniature {
    id: number;
    slug: string;
    display_name: string;
    character_id: number;
    front_image: string | null;
    back_image: string | null;
}

interface PickedCharacter {
    id: number;
    slug: string;
    display_name: string;
    miniature: PickedMiniature | null;
}

interface Filters {
    faction: string[];
    keyword: string[];
    characteristic: string[];
    cost_min: number | null;
    cost_max: number | null;
}

const props = defineProps<{
    factions: FactionOption[];
    keywords: OptionEntry[];
    characteristics: OptionEntry[];
    filters: Filters;
    picked: PickedCharacter | null;
}>();

// Local form state — mirrors props.filters but lets the user adjust before
// rolling. Selected factions are tracked as a Set of slugs (toggled via the
// faction logo buttons); keywords/characteristics use the existing
// multi-select primitive.
const selectedFactions = ref(new Set<string>(props.filters.faction));
const selectedKeywords = ref<string[]>([...props.filters.keyword]);
const selectedCharacteristics = ref<string[]>([...props.filters.characteristic]);
const costMin = ref<string>(props.filters.cost_min !== null ? String(props.filters.cost_min) : '');
const costMax = ref<string>(props.filters.cost_max !== null ? String(props.filters.cost_max) : '');

const toggleFaction = (slug: string) => {
    if (selectedFactions.value.has(slug)) selectedFactions.value.delete(slug);
    else selectedFactions.value.add(slug);
    // Mutating a Set doesn't trigger reactivity — replace the ref to fire watchers.
    selectedFactions.value = new Set(selectedFactions.value);
};

const hasFilters = computed(
    () =>
        selectedFactions.value.size > 0 ||
        selectedKeywords.value.length > 0 ||
        selectedCharacteristics.value.length > 0 ||
        costMin.value !== '' ||
        costMax.value !== '',
);

const buildQuery = () => {
    const q: Record<string, string> = { roll: '1' };
    if (selectedFactions.value.size) q.faction = [...selectedFactions.value].join(',');
    if (selectedKeywords.value.length) q.keyword = selectedKeywords.value.join(',');
    if (selectedCharacteristics.value.length) q.characteristic = selectedCharacteristics.value.join(',');
    if (costMin.value !== '') q.cost_min = costMin.value;
    if (costMax.value !== '') q.cost_max = costMax.value;
    return q;
};

const rolling = ref(false);
const roll = () => {
    rolling.value = true;
    // GET to the same route with `roll=1` so the URL is shareable — the
    // controller picks a fresh random character on every request, so a
    // re-roll is just a re-visit. preserveState keeps the form filled in.
    router.get(route('tools.random_character'), buildQuery(), {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => (rolling.value = false),
    });
};

const clearAll = () => {
    selectedFactions.value = new Set();
    selectedKeywords.value = [];
    selectedCharacteristics.value = [];
    costMin.value = '';
    costMax.value = '';
    router.get(route('tools.random_character'), {}, { preserveScroll: true, preserveState: true });
};
</script>

<template>
    <Head title="Random Character" />

    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Random Character" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Roll a random character — narrow the pool by faction, keyword, characteristic, or cost.
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto space-y-4 px-4 sm:px-4">
            <Card>
                <CardContent class="space-y-4 p-4">
                    <!-- Factions -->
                    <div class="space-y-2">
                        <label class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Factions</label>
                        <div class="grid grid-cols-4 gap-2 sm:grid-cols-8">
                            <button
                                v-for="f in factions"
                                :key="f.value"
                                type="button"
                                class="flex flex-col items-center gap-1.5 rounded-lg border-2 p-2 transition-all sm:p-3"
                                :class="selectedFactions.has(f.value) ? 'border-primary bg-primary/10' : 'border-transparent hover:bg-muted'"
                                @click="toggleFaction(f.value)"
                            >
                                <FactionLogo :faction="f.value" class-name="size-10 sm:size-12" />
                                <span class="text-center text-[10px] font-medium sm:text-xs">{{ f.name }}</span>
                            </button>
                        </div>
                        <p v-if="selectedFactions.size === 0" class="text-[11px] text-muted-foreground">Empty = any faction.</p>
                    </div>

                    <!-- Keyword / Characteristic / Cost -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Keywords</label>
                            <SearchableMultiselect v-model="selectedKeywords" :options="keywords" placeholder="Any keyword..." />
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Characteristics</label>
                            <SearchableMultiselect v-model="selectedCharacteristics" :options="characteristics" placeholder="Any characteristic..." />
                        </div>
                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Cost</label>
                            <div class="flex items-center gap-2">
                                <Input v-model="costMin" type="number" min="0" max="20" placeholder="Min" class="h-9 max-w-[120px]" />
                                <span class="text-xs text-muted-foreground">to</span>
                                <Input v-model="costMax" type="number" min="0" max="20" placeholder="Max" class="h-9 max-w-[120px]" />
                            </div>
                            <p class="text-[11px] text-muted-foreground">Leave both blank for any cost.</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 pt-2">
                        <Button :disabled="rolling" class="gap-1.5" @click="roll">
                            <Dices class="size-4" />
                            {{ picked ? 'Re-roll' : 'Roll' }}
                        </Button>
                        <Button v-if="hasFilters || picked" variant="ghost" class="gap-1.5" @click="clearAll">
                            <Eraser class="size-3.5" />
                            Reset
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Result — CharacterCardView owns the flip / fullscreen / collection
                 / wishlist / "View Character Page" affordances, so we just feed
                 it the miniature + character slug and let it do the rest. -->
            <Card v-if="picked?.miniature" class="overflow-hidden">
                <CardContent class="mx-auto max-w-md p-4">
                    <CharacterCardView :miniature="picked.miniature" :character-slug="picked.slug" />
                </CardContent>
            </Card>

            <EmptyState
                v-else-if="
                    filters.faction.length ||
                    filters.keyword.length ||
                    filters.characteristic.length ||
                    filters.cost_min !== null ||
                    filters.cost_max !== null
                "
                :icon="Dices"
                title="No characters match those filters"
                description="Loosen the filters and roll again."
            />
        </div>
    </div>
</template>
