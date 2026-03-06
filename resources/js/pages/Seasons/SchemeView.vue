<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ChevronRight } from 'lucide-vue-next';

interface NextScheme {
    id: number;
    name: string;
    slug: string;
}

interface SchemeData {
    id: number;
    name: string;
    slug: string;
    season: string;
    season_label: string;
    selector: string | null;
    prerequisite: string | null;
    reveal: string | null;
    scoring: string | null;
    additional: string | null;
    image_url: string | null;
    next_schemes: NextScheme[];
}

defineProps<{
    scheme: SchemeData;
}>();
</script>

<template>
    <Head :title="scheme.name" />

    <div class="relative h-full w-full">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <div class="container mx-auto px-4 pb-16 pt-6">
            <Link
                :href="route('seasons.view', scheme.season)"
                class="group mb-6 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                <ArrowLeft class="h-4 w-4 transition-transform group-hover:-translate-x-1" />
                Back to {{ scheme.season_label }}
            </Link>

            <div class="animate-fade-in-up">
                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Image -->
                    <div v-if="scheme.image_url" class="lg:col-span-2">
                        <div class="overflow-hidden rounded-xl shadow-lg">
                            <img :src="scheme.image_url" :alt="scheme.name" class="w-full rounded-xl" />
                        </div>
                    </div>

                    <!-- Info panel -->
                    <div :class="scheme.image_url ? '' : 'lg:col-span-3 max-w-2xl'">
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="text-2xl">{{ scheme.name }}</CardTitle>
                                <div class="flex flex-wrap gap-2">
                                    <Badge variant="secondary">{{ scheme.season_label }}</Badge>
                                    <Badge v-if="scheme.selector" variant="outline">{{ scheme.selector }}</Badge>
                                </div>
                            </CardHeader>
                            <CardContent class="space-y-6">
                                <div v-if="scheme.prerequisite">
                                    <h3 class="mb-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Prerequisite</h3>
                                    <p class="whitespace-pre-line text-sm">{{ scheme.prerequisite }}</p>
                                </div>

                                <div v-if="scheme.reveal">
                                    <h3 class="mb-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Reveal</h3>
                                    <p class="whitespace-pre-line text-sm">{{ scheme.reveal }}</p>
                                </div>

                                <div v-if="scheme.scoring">
                                    <h3 class="mb-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Scoring</h3>
                                    <p class="whitespace-pre-line text-sm">{{ scheme.scoring }}</p>
                                </div>

                                <div v-if="scheme.additional">
                                    <h3 class="mb-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Additional VP</h3>
                                    <p class="whitespace-pre-line text-sm">{{ scheme.additional }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Next Available Schemes -->
                        <Card v-if="scheme.next_schemes.length" class="mt-4">
                            <CardHeader class="pb-3">
                                <CardTitle class="text-base">Next Available Schemes</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="flex flex-col gap-2">
                                    <Link
                                        v-for="next in scheme.next_schemes"
                                        :key="next.id"
                                        :href="route('schemes.view', next.slug)"
                                        class="flex items-center justify-between rounded-md px-3 py-2 text-sm transition-colors hover:bg-accent"
                                    >
                                        <span class="font-medium">{{ next.name }}</span>
                                        <ChevronRight class="size-4 text-muted-foreground" />
                                    </Link>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
