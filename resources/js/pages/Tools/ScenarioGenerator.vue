<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import GameIcon from '@/components/GameIcon.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, Dices, Pencil, Share2 } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref } from 'vue';

interface Season {
    value: string;
    label: string;
}

interface StrategyItem {
    id: number;
    name: string;
    slug: string;
    suit: string | null;
    suit_label: string | null;
    setup: string | null;
    rules: string | null;
    scoring: string | null;
    additional_scoring: string | null;
    image_url: string | null;
}

interface DeploymentItem {
    value: string;
    label: string;
    suit: string;
    suit_label: string;
    description: string;
    image_url: string;
}

interface SchemeItem {
    id: number;
    name: string;
    slug: string;
    selector: string | null;
    prerequisite: string | null;
    reveal: string | null;
    scoring: string | null;
    additional: string | null;
    image_url: string | null;
}

const props = defineProps<{
    season: Season;
    seasons: Season[];
    deployments: DeploymentItem[];
    strategies: StrategyItem[];
    schemes: SchemeItem[];
}>();

const selectedSeason = ref(props.season.value);

const suitBorderColor: Record<string, string> = {
    crow: 'border-l-green-600 dark:border-l-green-400',
    mask: 'border-l-purple-600 dark:border-l-purple-400',
    ram: 'border-l-red-600 dark:border-l-red-400',
    tome: 'border-l-blue-600 dark:border-l-blue-400',
};

const navigateToSeason = (season: string) => {
    router.get(route('tools.scenario_generator'), { season });
};

const pickedDeployment = ref<DeploymentItem | null>(null);
const pickedStrategy = ref<StrategyItem | null>(null);
const pickedSchemes = ref<SchemeItem[]>([]);
const showResults = ref(false);

const canGenerate = computed(() => props.strategies.length >= 1 && props.schemes.length >= 3);

function shuffleArray<T>(arr: T[]): T[] {
    const shuffled = [...arr];
    for (let i = shuffled.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
    }
    return shuffled;
}

async function generate() {
    showResults.value = false;
    await nextTick();

    pickedDeployment.value = shuffleArray(props.deployments)[0];
    pickedStrategy.value = shuffleArray(props.strategies)[0];
    pickedSchemes.value = shuffleArray(props.schemes).slice(0, 3);

    showResults.value = true;
}

const resultCount = computed(() => (showResults.value ? 5 : 0));
const { delays: resultDelays } = useStaggeredEntry(resultCount);

// ─── Manual overrides ───
const editingDeployment = ref(false);
const editingStrategy = ref(false);
const editingSchemeIndex = ref<number | null>(null);

function setDeployment(value: string) {
    const found = props.deployments.find((d) => d.value === value);
    if (found) pickedDeployment.value = found;
    editingDeployment.value = false;
}

function setStrategy(value: string) {
    const found = props.strategies.find((s) => String(s.id) === value);
    if (found) pickedStrategy.value = found;
    editingStrategy.value = false;
}

function setScheme(index: number, value: string) {
    const found = props.schemes.find((s) => String(s.id) === value);
    if (found) pickedSchemes.value[index] = found;
    editingSchemeIndex.value = null;
}

const availableSchemes = computed(() => {
    const pickedIds = new Set(pickedSchemes.value.map((s) => s.id));
    return (index: number) => props.schemes.filter((s) => s.id === pickedSchemes.value[index]?.id || !pickedIds.has(s.id));
});

// ─── Share link ───
function buildShareUrl(): string {
    const params = new URLSearchParams();
    params.set('season', props.season.value);
    if (pickedDeployment.value) params.set('d', pickedDeployment.value.value);
    if (pickedStrategy.value) params.set('st', String(pickedStrategy.value.id));
    if (pickedSchemes.value.length) params.set('sc', pickedSchemes.value.map((s) => s.id).join(','));
    return `${route('tools.scenario_generator')}?${params.toString()}`;
}

const copied = ref(false);
async function copyShareLink() {
    const url = buildShareUrl();
    await navigator.clipboard.writeText(url);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

// ─── Restore from URL params ───
onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const dParam = params.get('d');
    const stParam = params.get('st');
    const scParam = params.get('sc');

    if (dParam && stParam && scParam) {
        const deployment = props.deployments.find((d) => d.value === dParam);
        const strategy = props.strategies.find((s) => s.id === Number(stParam));
        const schemeIds = scParam.split(',').map(Number);
        const schemes = schemeIds.map((id) => props.schemes.find((s) => s.id === id)).filter(Boolean) as SchemeItem[];

        if (deployment && strategy && schemes.length > 0) {
            pickedDeployment.value = deployment;
            pickedStrategy.value = strategy;
            pickedSchemes.value = schemes;
            showResults.value = true;
        }
    }
});
</script>

<template>
    <Head :title="`Scenario Generator — ${season.label}`" />

    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Scenario Generator">
            <template #subtitle>
                <div class="px-2 text-sm text-muted-foreground">Randomly generate a deployment, strategy, and 3 schemes for your game.</div>
            </template>
        </PageBanner>

        <div class="container mx-auto mt-6 px-4 lg:px-6">
            <div class="grid gap-8 lg:grid-cols-8">
                <!-- Sidebar: season selector -->
                <aside class="lg:col-span-2">
                    <!-- Mobile: dropdown -->
                    <div class="lg:hidden">
                        <Select v-model="selectedSeason" @update:model-value="navigateToSeason">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select Season" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="s in seasons" :key="s.value" :value="s.value">
                                    {{ s.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Desktop: sticky card -->
                    <div class="sticky top-6 hidden lg:block">
                        <Card>
                            <CardContent class="p-4">
                                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Season</h3>
                                <nav class="mb-4 flex flex-col gap-1">
                                    <button
                                        v-for="s in seasons"
                                        :key="s.value"
                                        class="rounded-md px-3 py-2 text-left text-sm transition-colors hover:bg-accent"
                                        :class="s.value === season.value ? 'bg-accent font-medium' : 'text-muted-foreground'"
                                        @click="navigateToSeason(s.value)"
                                    >
                                        {{ s.label }}
                                    </button>
                                </nav>

                                <div class="flex flex-col gap-2">
                                    <Button class="w-full" :disabled="!canGenerate" @click="generate">
                                        <Dices class="mr-2 size-4" />
                                        {{ showResults ? 'Re-roll' : 'Generate' }}
                                    </Button>
                                    <Button v-if="showResults" variant="outline" class="w-full" @click="copyShareLink">
                                        <Check v-if="copied" class="mr-2 size-4" />
                                        <Share2 v-else class="mr-2 size-4" />
                                        {{ copied ? 'Copied!' : 'Share Scenario' }}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </aside>

                <!-- Content -->
                <div class="lg:col-span-6">
                    <!-- Mobile generate button -->
                    <div class="mb-6 flex gap-2 lg:hidden">
                        <Button class="flex-1" :disabled="!canGenerate" @click="generate">
                            <Dices class="mr-2 size-4" />
                            {{ showResults ? 'Re-roll' : 'Generate' }}
                        </Button>
                        <Button v-if="showResults" variant="outline" @click="copyShareLink">
                            <Check v-if="copied" class="size-4" />
                            <Share2 v-else class="size-4" />
                        </Button>
                    </div>

                    <EmptyState
                        v-if="!canGenerate"
                        title="Not enough pool data"
                        description="This season needs at least 1 strategy and 3 schemes to generate a scenario."
                    />

                    <div v-else-if="!showResults" class="flex flex-col items-center justify-center py-10 text-center sm:py-16">
                        <Dices class="mb-4 size-12 text-muted-foreground/50" />
                        <p class="text-lg font-medium text-muted-foreground">Press Generate to create a random scenario</p>
                    </div>

                    <div v-else class="space-y-6 sm:space-y-8">
                        <!-- Deployment -->
                        <section v-if="pickedDeployment">
                            <div class="mb-4 flex items-center gap-2">
                                <h2 class="text-lg font-semibold">Deployment</h2>
                                <button
                                    class="rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                    @click="editingDeployment = !editingDeployment"
                                >
                                    <Pencil class="size-3.5" />
                                </button>
                            </div>
                            <div v-if="editingDeployment" class="mb-4">
                                <Select :model-value="pickedDeployment.value" @update:model-value="setDeployment">
                                    <SelectTrigger class="w-full sm:w-64">
                                        <SelectValue placeholder="Select Deployment" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="d in deployments" :key="d.value" :value="d.value">
                                            {{ d.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="animate-fade-in-up opacity-0" :style="resultDelays[0]">
                                <Card class="overflow-hidden md:flex">
                                    <img
                                        v-if="pickedDeployment.image_url"
                                        :src="pickedDeployment.image_url"
                                        :alt="pickedDeployment.label"
                                        class="w-full md:w-48"
                                    />
                                    <CardContent class="flex-1 border-l-4 p-4" :class="suitBorderColor[pickedDeployment.suit] ?? 'border-l-border'">
                                        <div class="flex items-center gap-2">
                                            <span class="text-lg font-medium">{{ pickedDeployment.label }}</span>
                                            <Badge variant="secondary" class="gap-1 text-xs">
                                                <GameIcon :type="pickedDeployment.suit" class-name="text-sm" />
                                                {{ pickedDeployment.suit_label }}
                                            </Badge>
                                        </div>
                                        <p class="mt-2 text-sm text-muted-foreground">{{ pickedDeployment.description }}</p>
                                    </CardContent>
                                </Card>
                            </div>
                        </section>

                        <!-- Strategy -->
                        <section v-if="pickedStrategy">
                            <div class="mb-4 flex items-center gap-2">
                                <h2 class="text-lg font-semibold">Strategy</h2>
                                <button
                                    class="rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                    @click="editingStrategy = !editingStrategy"
                                >
                                    <Pencil class="size-3.5" />
                                </button>
                            </div>
                            <div v-if="editingStrategy" class="mb-4">
                                <Select :model-value="String(pickedStrategy.id)" @update:model-value="setStrategy">
                                    <SelectTrigger class="w-full sm:w-64">
                                        <SelectValue placeholder="Select Strategy" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="s in strategies" :key="s.id" :value="String(s.id)">
                                            {{ s.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="animate-fade-in-up opacity-0" :style="resultDelays[1]">
                                <Link :href="route('strategies.view', pickedStrategy.slug)">
                                    <div
                                        v-if="pickedStrategy.image_url"
                                        class="overflow-hidden rounded-lg shadow-md transition-shadow hover:shadow-lg md:max-w-xs"
                                    >
                                        <img :src="pickedStrategy.image_url" :alt="pickedStrategy.name" class="w-full rounded-lg" />
                                    </div>
                                    <Card
                                        v-else
                                        class="border-l-4 transition-shadow hover:shadow-lg md:max-w-xs"
                                        :class="pickedStrategy.suit ? suitBorderColor[pickedStrategy.suit] : 'border-l-border'"
                                    >
                                        <CardContent class="flex items-center gap-3 p-4">
                                            <div class="min-w-0 flex-1">
                                                <div class="text-lg font-medium">{{ pickedStrategy.name }}</div>
                                                <Badge v-if="pickedStrategy.suit" variant="secondary" class="mt-1.5 gap-1 text-xs">
                                                    <GameIcon :type="pickedStrategy.suit" class-name="text-sm" />
                                                    {{ pickedStrategy.suit_label }}
                                                </Badge>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </Link>
                            </div>
                        </section>

                        <!-- Schemes -->
                        <section v-if="pickedSchemes.length">
                            <h2 class="mb-4 text-lg font-semibold">Schemes</h2>
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div
                                    v-for="(scheme, index) in pickedSchemes"
                                    :key="scheme.id"
                                    class="animate-fade-in-up opacity-0"
                                    :style="resultDelays[index + 2]"
                                >
                                    <div v-if="editingSchemeIndex === index" class="mb-3">
                                        <Select :model-value="String(scheme.id)" @update:model-value="(v: string) => setScheme(index, v)">
                                            <SelectTrigger class="w-full">
                                                <SelectValue placeholder="Select Scheme" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="s in availableSchemes(index)" :key="s.id" :value="String(s.id)">
                                                    {{ s.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="relative">
                                        <button
                                            class="absolute right-2 top-2 z-10 rounded-md bg-background/80 p-1 text-muted-foreground backdrop-blur-sm transition-colors hover:bg-accent hover:text-foreground"
                                            @click.prevent="editingSchemeIndex = editingSchemeIndex === index ? null : index"
                                        >
                                            <Pencil class="size-3.5" />
                                        </button>
                                        <Link :href="route('schemes.view', scheme.slug)">
                                            <div
                                                v-if="scheme.image_url"
                                                class="overflow-hidden rounded-lg shadow-md transition-shadow hover:shadow-lg"
                                            >
                                                <img :src="scheme.image_url" :alt="scheme.name" class="w-full rounded-lg" />
                                            </div>
                                            <Card v-else class="transition-shadow hover:shadow-lg">
                                                <CardContent class="flex items-center justify-between p-4">
                                                    <span class="font-medium">{{ scheme.name }}</span>
                                                </CardContent>
                                            </Card>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
