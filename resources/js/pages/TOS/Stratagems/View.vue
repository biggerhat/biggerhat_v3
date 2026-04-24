<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import CardImage from '@/components/TOS/CardImage.vue';
import TosText from '@/components/TosText.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Newspaper } from 'lucide-vue-next';

interface Stratagem {
    id: number;
    slug: string;
    name: string;
    tactical_cost: number;
    effect: string | null;
    image_path: string | null;
    allegiance_type: string | null;
    allegiance: { id: number; name: string; slug: string } | null;
}

defineProps<{
    stratagem: Stratagem;
}>();
</script>

<template>
    <Head :title="`${stratagem.name} — Stratagem`" />
    <div class="relative">
        <PageBanner :title="stratagem.name" class="mb-2">
            <template #subtitle>
                <div class="my-auto flex items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    <Badge variant="outline" class="text-[10px] tabular-nums">{{ stratagem.tactical_cost }} Tactics Tokens</Badge>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto space-y-4 sm:px-4">
            <Link :href="route('tos.stratagems.index')" class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-3" /> All stratagems
            </Link>

            <Card class="overflow-hidden">
                <div class="grid gap-4 p-4 lg:grid-cols-[minmax(0,260px)_1fr]">
                    <CardImage
                        :src="stratagem.image_path"
                        :alt="stratagem.name"
                        :allegiance-slug="stratagem.allegiance?.slug ?? null"
                        :placeholder-icon="Newspaper"
                    />

                    <CardContent class="space-y-4 px-0 pb-0">
                        <p class="text-[11px] text-muted-foreground">
                            <template v-if="stratagem.allegiance">
                                <Link :href="route('tos.allegiances.view', stratagem.allegiance.slug)" class="hover:text-foreground">{{ stratagem.allegiance.name }}</Link>
                            </template>
                            <template v-else-if="stratagem.allegiance_type">
                                <span class="capitalize">Any {{ stratagem.allegiance_type }} allegiance may include this Stratagem.</span>
                            </template>
                            <template v-else>Universal — available to any allegiance.</template>
                        </p>
                        <p v-if="stratagem.effect" class="text-sm text-muted-foreground"><TosText :text="stratagem.effect" /></p>
                    </CardContent>
                </div>
            </Card>
        </div>
    </div>
</template>
