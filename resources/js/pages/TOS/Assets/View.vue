<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import CardImage from '@/components/TOS/CardImage.vue';
import TosSuits from '@/components/TosSuits.vue';
import TosText from '@/components/TosText.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Package } from 'lucide-vue-next';

interface Limit {
    id: number;
    limit_type: string;
    parameter_type: string | null;
    parameter_value: string | null;
    notes: string | null;
    parameter_unit: { id: number; name: string; slug: string } | null;
    parameter_allegiance: { id: number; name: string; slug: string } | null;
}

interface Asset {
    id: number;
    slug: string;
    name: string;
    scrip_cost: number;
    disable_count: number | null;
    scrap_count: number | null;
    body: string | null;
    image_path: string | null;
    allegiances: Array<{ id: number; name: string; slug: string }>;
    abilities: Array<{ id: number; name: string; body: string | null }>;
    actions: Array<{
        id: number;
        name: string;
        type_links: Array<{ id: number; type: string }>;
        av: number | null;
        av_target: string | null;
        av_suits: string | null;
        range: string | null;
        strength: number | null;
        body: string | null;
        triggers: Array<{ id: number; name: string; suits: string | null; margin_cost: number | null; timing: string; body: string | null }>;
    }>;
    limits: Limit[];
}

defineProps<{
    asset: Asset;
}>();

const describeLimit = (l: Limit): string => {
    const head = l.limit_type.charAt(0).toUpperCase() + l.limit_type.slice(1);
    const tail = l.parameter_unit?.name ?? l.parameter_allegiance?.name ?? l.parameter_value;
    return tail ? `${head} — ${tail}` : head;
};
</script>

<template>
    <Head :title="`${asset.name} — Asset`" />
    <div class="relative">
        <PageBanner :title="asset.name" class="mb-2">
            <template #subtitle>
                <div class="my-auto flex items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    <span class="tabular-nums">{{ asset.scrip_cost }} Scrip</span>
                    <span v-if="asset.disable_count != null">· Disable {{ asset.disable_count }}</span>
                    <span v-if="asset.scrap_count != null">· Scrap {{ asset.scrap_count }}</span>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto space-y-4 sm:px-4">
            <Link :href="route('tos.assets.index')" class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-3" /> All assets
            </Link>

            <Card class="overflow-hidden">
                <div class="grid gap-4 p-4 lg:grid-cols-[minmax(0,260px)_1fr]">
                    <CardImage
                        :src="asset.image_path"
                        :alt="asset.name"
                        :allegiance-slug="asset.allegiances[0]?.slug ?? null"
                        :placeholder-icon="Package"
                    />

                    <CardContent class="space-y-4 px-0 pb-0">
                        <div class="flex flex-wrap gap-1">
                            <Badge v-for="a in asset.allegiances" :key="a.id" variant="outline" class="text-[10px]">{{ a.name }}</Badge>
                        </div>

                        <p v-if="asset.body" class="text-sm text-muted-foreground"><TosText :text="asset.body" /></p>

                    <div v-if="asset.limits.length">
                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Limits</p>
                        <ul class="space-y-1 text-xs">
                            <li v-for="l in asset.limits" :key="l.id">{{ describeLimit(l) }}</li>
                        </ul>
                    </div>

                    <div v-if="asset.abilities.length">
                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Abilities</p>
                        <ul class="space-y-1.5 text-xs">
                            <li v-for="a in asset.abilities" :key="a.id">
                                <span class="font-medium">{{ a.name }}.</span>
                                <span v-if="a.body" class="ml-1 text-muted-foreground"><TosText :text="a.body" /></span>
                            </li>
                        </ul>
                    </div>

                    <div v-if="asset.actions.length">
                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Actions</p>
                        <ul class="space-y-2 text-xs">
                            <li v-for="ac in asset.actions" :key="ac.id" class="rounded border bg-muted/30 p-2">
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
                                <p v-if="ac.body" class="mt-1 text-muted-foreground"><TosText :text="ac.body" /></p>
                            </li>
                        </ul>
                    </div>
                    </CardContent>
                </div>
            </Card>
        </div>
    </div>
</template>
