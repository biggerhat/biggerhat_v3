<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import CardImage from '@/components/TOS/CardImage.vue';
import TosText from '@/components/TosText.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Bot } from 'lucide-vue-next';

interface Envoy {
    id: number;
    slug: string;
    name: string;
    keyword: string | null;
    restriction: string;
    body: string | null;
    image_path: string | null;
    allegiance: { id: number; slug: string; name: string; is_syndicate: boolean };
    abilities: Array<{ id: number; name: string; body: string | null }>;
}

defineProps<{
    envoy: Envoy;
}>();
</script>

<template>
    <Head :title="`${envoy.name} — Envoy`" />
    <div class="relative">
        <PageBanner :title="envoy.name" class="mb-2">
            <template #subtitle>
                <div class="my-auto flex items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    <Link :href="route('tos.allegiances.view', envoy.allegiance.slug)" class="hover:text-foreground">
                        {{ envoy.allegiance.name }}
                    </Link>
                    <Badge v-if="envoy.keyword" class="text-[10px]">{{ envoy.keyword }}</Badge>
                    <Badge variant="outline" class="text-[10px] capitalize">Restriction: {{ envoy.restriction }}</Badge>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto space-y-4 sm:px-4">
            <Link :href="route('tos.envoys.index')" class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-3" /> All envoys
            </Link>

            <Card class="overflow-hidden">
                <div class="grid gap-4 p-4 lg:grid-cols-[minmax(0,260px)_1fr]">
                    <CardImage
                        :src="envoy.image_path"
                        :alt="envoy.name"
                        :allegiance-slug="envoy.allegiance.slug"
                        :placeholder-icon="Bot"
                    />

                    <CardContent class="space-y-4 px-0 pb-0">
                        <p v-if="envoy.body" class="text-sm text-muted-foreground"><TosText :text="envoy.body" /></p>

                        <div v-if="envoy.abilities.length">
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Abilities</p>
                            <ul class="space-y-1.5 text-sm">
                                <li v-for="a in envoy.abilities" :key="a.id">
                                    <span class="font-medium">{{ a.name }}.</span>
                                    <span v-if="a.body" class="ml-1 text-muted-foreground"><TosText :text="a.body" /></span>
                                </li>
                            </ul>
                        </div>

                        <p class="text-[11px] text-muted-foreground">
                            Hireable into Allegiances with a matching
                            <span class="capitalize">{{ envoy.restriction }}</span>
                            type.
                        </p>
                    </CardContent>
                </div>
            </Card>
        </div>
    </div>
</template>
