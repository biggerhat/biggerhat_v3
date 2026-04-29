<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import CardImage from '@/components/TOS/CardImage.vue';
import TosMarginCost from '@/components/TosMarginCost.vue';
import TosSuits from '@/components/TosSuits.vue';
import TosText from '@/components/TosText.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen } from 'lucide-vue-next';

interface Ability {
    id: number;
    name: string;
    body: string | null;
}

interface Trigger {
    id: number;
    name: string;
    suits: string | null;
    margin_cost: number | null;
    timing: string | null;
    body: string | null;
}

interface Action {
    id: number;
    name: string;
    body: string | null;
    av: number | null;
    av_target: string | null;
    av_suits: string | null;
    range: string | null;
    strength: number | null;
    type_links: Array<{ id: number; type: string }>;
    triggers: Trigger[];
}

interface Card_ {
    id: number;
    slug: string;
    name: string;
    type: string;
    secondary_type: string | null;
    body: string | null;
    primary_body: string | null;
    image_path: string | null;
    allegiance: { id: number; slug: string; name: string };
    abilities: Ability[];
    actions: Action[];
    triggers: Trigger[];
    primary_abilities: Ability[];
    primary_actions: Action[];
    primary_triggers: Trigger[];
}

defineProps<{
    card: Card_;
}>();
</script>

<template>
    <Head :title="`${card.name} — Allegiance Card`" />
    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner :title="card.name" class="mb-2">
            <template #subtitle>
                <div class="my-auto flex items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    <Link :href="route('tos.allegiances.view', card.allegiance.slug)" class="hover:text-foreground">
                        {{ card.allegiance.name }}
                    </Link>
                    <Badge variant="outline" class="text-[10px] capitalize">{{ card.type }}</Badge>
                    <Badge v-if="card.secondary_type" variant="outline" class="text-[10px] capitalize">{{ card.secondary_type }}</Badge>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto space-y-4 sm:px-4">
            <Link :href="route('tos.allegiance_cards.index')" class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-3" /> All allegiance cards
            </Link>

            <Card class="overflow-hidden">
                <div class="grid gap-4 p-4 lg:grid-cols-[minmax(0,260px)_1fr]">
                    <CardImage
                        :src="card.image_path"
                        :alt="card.name"
                        :allegiance-slug="card.allegiance.slug"
                        :placeholder-icon="BookOpen"
                    />

                    <CardContent class="space-y-6 px-0 pb-0">
                        <!-- Standard tier -->
                        <section class="space-y-3">
                            <div class="flex items-center gap-2 border-l-4 border-primary/60 pl-3">
                                <h3 class="text-sm font-semibold uppercase tracking-wider">Standard</h3>
                            </div>

                            <p v-if="card.body" class="text-sm text-muted-foreground"><TosText :text="card.body" /></p>

                            <div v-if="card.abilities.length">
                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Abilities</p>
                                <ul class="space-y-1.5 text-sm">
                                    <li v-for="a in card.abilities" :key="a.id">
                                        <span class="font-medium">{{ a.name }}.</span>
                                        <span v-if="a.body" class="ml-1 text-muted-foreground"><TosText :text="a.body" /></span>
                                    </li>
                                </ul>
                            </div>

                            <div v-if="card.actions.length">
                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Actions</p>
                                <ul class="space-y-2 text-sm">
                                    <li v-for="ac in card.actions" :key="ac.id" class="rounded border bg-muted/30 p-2">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-1.5">
                                                <span v-for="l in ac.type_links" :key="l.id" class="rounded bg-secondary px-1 py-0.5 text-[9px] capitalize text-secondary-foreground">{{ l.type }}</span>
                                                <span class="font-medium">{{ ac.name }}</span>
                                            </div>
                                            <span class="text-[10px] text-muted-foreground">
                                                <template v-if="ac.av != null">
                                                    {{ ac.av }}<TosSuits v-if="ac.av_suits" :suits="ac.av_suits" /><template v-if="ac.av_target"> v {{ ac.av_target }}</template>
                                                </template>
                                                <template v-if="ac.range"> · {{ ac.range }}</template>
                                                <template v-if="ac.strength != null"> · Str {{ ac.strength }}</template>
                                            </span>
                                        </div>
                                        <p v-if="ac.body" class="mt-1 text-xs text-muted-foreground"><TosText :text="ac.body" /></p>
                                        <ul v-if="ac.triggers?.length" class="mt-1 space-y-1 border-l-2 border-border pl-2 text-xs">
                                            <li v-for="t in ac.triggers" :key="t.id">
                                                <TosSuits v-if="t.suits" :suits="t.suits" />
                                                <TosMarginCost v-else-if="t.margin_cost != null" :cost="t.margin_cost" />
                                                <span class="font-medium">{{ t.name }}</span>
                                                <span v-if="t.body" class="text-muted-foreground"> — <TosText :text="t.body" /></span>
                                            </li>
                                        </ul>
                                    </li>
                                </ul>
                            </div>

                            <div v-if="card.triggers.length">
                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Triggers</p>
                                <ul class="space-y-1.5 text-sm">
                                    <li v-for="t in card.triggers" :key="t.id">
                                        <TosSuits v-if="t.suits" :suits="t.suits" />
                                        <TosMarginCost v-else-if="t.margin_cost != null" :cost="t.margin_cost" />
                                        <span class="font-medium">{{ t.name }}</span>
                                        <span v-if="t.body" class="text-muted-foreground"> — <TosText :text="t.body" /></span>
                                    </li>
                                </ul>
                            </div>
                        </section>

                        <!-- Primary tier — only render when something is set -->
                        <section
                            v-if="card.primary_body || card.primary_abilities.length || card.primary_actions.length || card.primary_triggers.length"
                            class="space-y-3"
                        >
                            <div class="flex items-center gap-2 border-l-4 border-amber-500/70 pl-3">
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-400">Primary</h3>
                            </div>

                            <p v-if="card.primary_body" class="text-sm text-muted-foreground"><TosText :text="card.primary_body" /></p>

                            <div v-if="card.primary_abilities.length">
                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Abilities</p>
                                <ul class="space-y-1.5 text-sm">
                                    <li v-for="a in card.primary_abilities" :key="a.id">
                                        <span class="font-medium">{{ a.name }}.</span>
                                        <span v-if="a.body" class="ml-1 text-muted-foreground"><TosText :text="a.body" /></span>
                                    </li>
                                </ul>
                            </div>

                            <div v-if="card.primary_actions.length">
                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Actions</p>
                                <ul class="space-y-2 text-sm">
                                    <li v-for="ac in card.primary_actions" :key="ac.id" class="rounded border bg-muted/30 p-2">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-1.5">
                                                <span v-for="l in ac.type_links" :key="l.id" class="rounded bg-secondary px-1 py-0.5 text-[9px] capitalize text-secondary-foreground">{{ l.type }}</span>
                                                <span class="font-medium">{{ ac.name }}</span>
                                            </div>
                                            <span class="text-[10px] text-muted-foreground">
                                                <template v-if="ac.av != null">
                                                    {{ ac.av }}<TosSuits v-if="ac.av_suits" :suits="ac.av_suits" /><template v-if="ac.av_target"> v {{ ac.av_target }}</template>
                                                </template>
                                                <template v-if="ac.range"> · {{ ac.range }}</template>
                                                <template v-if="ac.strength != null"> · Str {{ ac.strength }}</template>
                                            </span>
                                        </div>
                                        <p v-if="ac.body" class="mt-1 text-xs text-muted-foreground"><TosText :text="ac.body" /></p>
                                        <ul v-if="ac.triggers?.length" class="mt-1 space-y-1 border-l-2 border-border pl-2 text-xs">
                                            <li v-for="t in ac.triggers" :key="t.id">
                                                <TosSuits v-if="t.suits" :suits="t.suits" />
                                                <TosMarginCost v-else-if="t.margin_cost != null" :cost="t.margin_cost" />
                                                <span class="font-medium">{{ t.name }}</span>
                                                <span v-if="t.body" class="text-muted-foreground"> — <TosText :text="t.body" /></span>
                                            </li>
                                        </ul>
                                    </li>
                                </ul>
                            </div>

                            <div v-if="card.primary_triggers.length">
                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Triggers</p>
                                <ul class="space-y-1.5 text-sm">
                                    <li v-for="t in card.primary_triggers" :key="t.id">
                                        <TosSuits v-if="t.suits" :suits="t.suits" />
                                        <TosMarginCost v-else-if="t.margin_cost != null" :cost="t.margin_cost" />
                                        <span class="font-medium">{{ t.name }}</span>
                                        <span v-if="t.body" class="text-muted-foreground"> — <TosText :text="t.body" /></span>
                                    </li>
                                </ul>
                            </div>
                        </section>
                    </CardContent>
                </div>
            </Card>
        </div>
    </div>
</template>
