<script setup lang="ts">
import GameIcon from '@/components/GameIcon.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

interface StrategyData {
    id: number;
    name: string;
    slug: string;
    season: string;
    season_label: string;
    suit: string | null;
    suit_label: string | null;
    setup: string | null;
    rules: string | null;
    scoring: string | null;
    additional_scoring: string | null;
    image_url: string | null;
}

defineProps<{
    strategy: StrategyData;
}>();
</script>

<template>
    <Head :title="strategy.name" />

    <div class="relative h-full w-full">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <div class="container mx-auto px-4 pb-8 pt-4 lg:pb-16 lg:pt-6">
            <Link
                :href="route('seasons.view', strategy.season)"
                class="group mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground lg:mb-6"
            >
                <ArrowLeft class="h-4 w-4 transition-transform group-hover:-translate-x-1" />
                Back to {{ strategy.season_label }}
            </Link>

            <div class="animate-fade-in-up">
                <div class="grid gap-6 lg:grid-cols-3 lg:gap-8">
                    <!-- Info panel — shown first on mobile -->
                    <div :class="strategy.image_url ? 'order-1 lg:order-2' : 'order-1 lg:col-span-3 max-w-2xl'">
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="text-xl leading-tight lg:text-2xl">{{ strategy.name }}</CardTitle>
                                <div class="flex flex-wrap gap-2">
                                    <Badge variant="secondary">{{ strategy.season_label }}</Badge>
                                    <Badge v-if="strategy.suit" variant="outline" class="gap-1">
                                        <GameIcon :type="strategy.suit" class-name="text-sm" />
                                        {{ strategy.suit_label }}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent class="space-y-6">
                                <div v-if="strategy.setup">
                                    <h3 class="mb-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Setup</h3>
                                    <p class="whitespace-pre-line text-sm">{{ strategy.setup }}</p>
                                </div>

                                <div v-if="strategy.rules">
                                    <h3 class="mb-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Rules</h3>
                                    <p class="whitespace-pre-line text-sm">{{ strategy.rules }}</p>
                                </div>

                                <div v-if="strategy.scoring">
                                    <h3 class="mb-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Scoring</h3>
                                    <p class="whitespace-pre-line text-sm">{{ strategy.scoring }}</p>
                                </div>

                                <div v-if="strategy.additional_scoring">
                                    <h3 class="mb-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Additional Scoring</h3>
                                    <p class="whitespace-pre-line text-sm">{{ strategy.additional_scoring }}</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Image — shown second on mobile -->
                    <div v-if="strategy.image_url" class="order-2 lg:order-1 lg:col-span-2">
                        <div class="overflow-hidden rounded-xl shadow-lg">
                            <img :src="strategy.image_url" :alt="strategy.name" class="w-full" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
