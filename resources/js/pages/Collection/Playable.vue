<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import SeoHead from '@/components/SeoHead.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { Library, Swords } from 'lucide-vue-next';
import { computed } from 'vue';

interface OwnedModel {
    slug: string;
    name: string;
    cost: number;
    quantity: number;
}

interface PlayableMaster {
    slug: string;
    name: string;
    faction: string | null;
    keywords: string[];
    models: OwnedModel[];
    model_count: number;
    total_cost: number;
}

const props = defineProps<{
    masters: PlayableMaster[];
    encounter_size: number;
}>();

const fullCrews = computed(() => props.masters.filter((m) => m.total_cost >= props.encounter_size).length);

const progress = (master: PlayableMaster) => Math.min(100, Math.round((master.total_cost / props.encounter_size) * 100));
</script>

<template>
    <SeoHead title="What Can I Play?" description="See which crews your collection can field." />
    <PageBanner title="What Can I Play?">
        <template #subtitle>
            <div class="flex flex-wrap items-center gap-3 px-2">
                <span class="text-sm text-muted-foreground">
                    For each Master you own, the models in your collection that share one of its keywords.
                </span>
                <Link :href="route('collection.index')">
                    <Button variant="ghost" size="sm" class="h-7 gap-1 text-xs"><Library class="size-3.5" />My Collection</Button>
                </Link>
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto mt-6 space-y-4 pb-10 sm:px-4 lg:px-6">
        <EmptyState
            v-if="masters.length === 0"
            :icon="Swords"
            title="No Masters in your collection yet"
            description="Add a Master and some of its keyword models to your collection and the crews you can field will appear here."
        >
            <template #action>
                <Link :href="route('collection.index')"><Button>Open My Collection</Button></Link>
            </template>
        </EmptyState>

        <template v-else>
            <p class="px-1 text-sm text-muted-foreground">
                {{ fullCrews }} of {{ masters.length }} {{ masters.length === 1 ? 'Master' : 'Masters' }} can field a full {{ encounter_size }}ss crew
                from what you own. Counts include models you own in any keyword the Master shares; Versatile and out-of-keyword hires aren't counted.
            </p>
            <div class="grid gap-3 md:grid-cols-2">
                <Card v-for="master in masters" :key="master.slug">
                    <CardHeader class="space-y-1 pb-2">
                        <div class="flex items-start justify-between gap-2">
                            <CardTitle class="text-base">
                                <Link :href="route('characters.view', { character: master.slug })" class="hover:underline">{{ master.name }}</Link>
                            </CardTitle>
                            <Badge :variant="master.total_cost >= encounter_size ? 'default' : 'secondary'" class="shrink-0 text-2xs">
                                {{ master.total_cost >= encounter_size ? 'Full crew' : `${master.total_cost} / ${encounter_size}ss` }}
                            </Badge>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            <Badge v-for="kw in master.keywords" :key="kw" variant="outline" class="text-2xs">{{ kw }}</Badge>
                        </div>
                        <div
                            class="h-1.5 overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            :aria-valuenow="progress(master)"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-label="`${master.name}: ${master.total_cost} of ${encounter_size} soulstones owned`"
                        >
                            <div class="h-full bg-primary" :style="{ width: progress(master) + '%' }" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <p v-if="master.models.length === 0" class="text-sm text-muted-foreground">
                            None of your other models share this Master's keywords yet.
                        </p>
                        <ul v-else class="space-y-0.5 text-sm">
                            <li v-for="model in master.models" :key="model.slug" class="flex items-center justify-between gap-2">
                                <span class="truncate">
                                    {{ model.name }}<span v-if="model.quantity > 1" class="text-muted-foreground"> ×{{ model.quantity }}</span>
                                </span>
                                <span class="shrink-0 text-xs text-muted-foreground">{{ model.cost }}ss</span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </template>
    </div>
</template>
