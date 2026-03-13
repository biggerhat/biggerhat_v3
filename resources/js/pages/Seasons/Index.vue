<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import GameIcon from '@/components/GameIcon.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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
    router.get(route('seasons.view', season));
};

const deploymentCount = computed(() => props.deployments.length);
const strategyCount = computed(() => props.strategies.length);
const schemeCount = computed(() => props.schemes.length);
const totalCount = computed(() => deploymentCount.value + strategyCount.value + schemeCount.value);

const { delays: deploymentDelays } = useStaggeredEntry(deploymentCount);
const { delays: strategyDelays } = useStaggeredEntry(strategyCount);
const { delays: schemeDelays } = useStaggeredEntry(schemeCount);
</script>

<template>
    <Head :title="`${season.label} — Seasons`" />

    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner :title="season.label">
            <template #subtitle>
                <div class="px-2 text-sm text-muted-foreground">
                    {{ deploymentCount }} {{ deploymentCount === 1 ? 'Deployment' : 'Deployments' }}, {{ strategyCount }}
                    {{ strategyCount === 1 ? 'Strategy' : 'Strategies' }}, {{ schemeCount }}
                    {{ schemeCount === 1 ? 'Scheme' : 'Schemes' }}
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto mt-6 px-4 lg:px-6">
            <div class="grid gap-4 lg:gap-8 lg:grid-cols-8">
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
                                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Seasons</h3>
                                <nav class="flex flex-col gap-1">
                                    <Link
                                        v-for="s in seasons"
                                        :key="s.value"
                                        :href="route('seasons.view', s.value)"
                                        class="rounded-md px-3 py-2 text-sm transition-colors hover:bg-accent"
                                        :class="s.value === season.value ? 'bg-accent font-medium' : 'text-muted-foreground'"
                                    >
                                        {{ s.label }}
                                    </Link>
                                </nav>
                            </CardContent>
                        </Card>
                    </div>
                </aside>

                <!-- Content -->
                <div class="lg:col-span-6">
                    <EmptyState
                        v-if="totalCount === 0"
                        title="No content yet"
                        description="This season has no deployments, strategies, or schemes."
                    />

                    <!-- Deployments -->
                    <section v-if="deployments.length" class="mb-12">
                        <h2 class="mb-4 text-lg font-semibold">Deployments</h2>
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <div
                                v-for="(deployment, index) in deployments"
                                :key="deployment.value"
                                class="animate-fade-in-up opacity-0"
                                :style="deploymentDelays[index]"
                            >
                                <Card class="overflow-hidden">
                                    <img v-if="deployment.image_url" :src="deployment.image_url" :alt="deployment.label" loading="lazy" decoding="async" class="w-full" />
                                    <CardContent class="border-l-4 p-4" :class="suitBorderColor[deployment.suit] ?? 'border-l-border'">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium">{{ deployment.label }}</span>
                                            <Badge variant="secondary" class="gap-1 text-xs">
                                                <GameIcon :type="deployment.suit" class-name="text-sm" />
                                                {{ deployment.suit_label }}
                                            </Badge>
                                        </div>
                                        <p class="mt-2 text-xs text-muted-foreground">{{ deployment.description }}</p>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </section>

                    <!-- Strategies -->
                    <section v-if="strategies.length" class="mb-12">
                        <h2 class="mb-4 text-lg font-semibold">Strategies</h2>
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <Link
                                v-for="(strategy, index) in strategies"
                                :key="strategy.id"
                                :href="route('strategies.view', strategy.slug)"
                                class="animate-fade-in-up opacity-0"
                                :style="strategyDelays[index]"
                            >
                                <!-- Image card -->
                                <div v-if="strategy.image_url" class="overflow-hidden rounded-lg shadow-md transition-shadow hover:shadow-lg">
                                    <img :src="strategy.image_url" :alt="strategy.name" loading="lazy" decoding="async" class="w-full rounded-lg" />
                                </div>

                                <!-- Text fallback -->
                                <Card
                                    v-else
                                    class="border-l-4 transition-shadow hover:shadow-lg"
                                    :class="strategy.suit ? suitBorderColor[strategy.suit] : 'border-l-border'"
                                >
                                    <CardContent class="flex items-center gap-3 p-4">
                                        <div class="min-w-0 flex-1">
                                            <div class="font-medium">{{ strategy.name }}</div>
                                            <Badge v-if="strategy.suit" variant="secondary" class="mt-1.5 gap-1 text-xs">
                                                <GameIcon :type="strategy.suit" class-name="text-sm" />
                                                {{ strategy.suit_label }}
                                            </Badge>
                                        </div>
                                        <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
                                    </CardContent>
                                </Card>
                            </Link>
                        </div>
                    </section>

                    <!-- Schemes -->
                    <section v-if="schemes.length">
                        <h2 class="mb-4 text-lg font-semibold">Schemes</h2>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <Link
                                v-for="(scheme, index) in schemes"
                                :key="scheme.id"
                                :href="route('schemes.view', scheme.slug)"
                                class="animate-fade-in-up opacity-0"
                                :style="schemeDelays[index]"
                            >
                                <!-- Image card -->
                                <div v-if="scheme.image_url" class="overflow-hidden rounded-lg shadow-md transition-shadow hover:shadow-lg">
                                    <img :src="scheme.image_url" :alt="scheme.name" loading="lazy" decoding="async" class="w-full rounded-lg" />
                                </div>

                                <!-- Text fallback -->
                                <Card v-else class="transition-shadow hover:shadow-lg">
                                    <CardContent class="flex items-center justify-between p-4">
                                        <span class="font-medium">{{ scheme.name }}</span>
                                        <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
                                    </CardContent>
                                </Card>
                            </Link>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>
