<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Card, CardContent } from '@/components/ui/card';
import { ROADMAP, ROADMAP_STAGES, ROADMAP_UPDATED, SHIPPED } from '@/lib/roadmap';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Roadmap" />
    <PageBanner title="Roadmap">
        <template #subtitle>
            <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                Where BiggerHat is going. Updated {{ ROADMAP_UPDATED }}.
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto px-4 sm:px-6" data-testid="roadmap">
        <div class="mx-auto max-w-4xl space-y-8 py-6">
            <p class="text-sm text-muted-foreground">
                This is a direction, not a promise: there are no dates, and things move as we hear from players. Tell us what matters to you on the
                <Link :href="route('feedback.show')" class="underline">Feedback page</Link> or in our Discord.
            </p>

            <section v-for="stage in ROADMAP_STAGES" :key="stage.key" :aria-labelledby="`roadmap-${stage.key}`" :data-testid="`roadmap-${stage.key}`">
                <h2 :id="`roadmap-${stage.key}`" class="text-lg font-semibold">{{ stage.title }}</h2>
                <p class="mb-3 text-sm text-muted-foreground">{{ stage.intro }}</p>
                <ul class="grid gap-3 sm:grid-cols-2">
                    <li v-for="item in ROADMAP[stage.key]" :key="item.title">
                        <Card class="h-full">
                            <CardContent class="space-y-1 p-4">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ item.area }}</p>
                                <h3 class="font-semibold">{{ item.title }}</h3>
                                <p class="text-sm text-muted-foreground">{{ item.body }}</p>
                            </CardContent>
                        </Card>
                    </li>
                </ul>
            </section>

            <section aria-labelledby="roadmap-shipped" data-testid="roadmap-shipped">
                <h2 id="roadmap-shipped" class="mb-3 text-lg font-semibold">Recently shipped</h2>
                <ul class="space-y-1.5 text-sm">
                    <li v-for="s in SHIPPED" :key="s.title" class="flex gap-3">
                        <span class="w-28 shrink-0 text-muted-foreground">{{ s.when }}</span>
                        <span>{{ s.title }}</span>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
