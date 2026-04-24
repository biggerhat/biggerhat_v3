<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import CardImage from '@/components/TOS/CardImage.vue';
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

interface Card_ {
    id: number;
    slug: string;
    name: string;
    type: string;
    body: string | null;
    image_path: string | null;
    allegiance: { id: number; slug: string; name: string };
    abilities: Ability[];
}

defineProps<{
    card: Card_;
}>();
</script>

<template>
    <Head :title="`${card.name} — Allegiance Card`" />
    <div class="relative">
        <PageBanner :title="card.name" class="mb-2">
            <template #subtitle>
                <div class="my-auto flex items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    <Link :href="route('tos.allegiances.view', card.allegiance.slug)" class="hover:text-foreground">
                        {{ card.allegiance.name }}
                    </Link>
                    <Badge variant="outline" class="text-[10px] capitalize">{{ card.type }}</Badge>
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

                    <CardContent class="space-y-4 px-0 pb-0">
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
                    </CardContent>
                </div>
            </Card>
        </div>
    </div>
</template>
