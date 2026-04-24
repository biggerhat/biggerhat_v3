<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import TosText from '@/components/TosText.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

interface Allegiance {
    id: number;
    slug: string;
    name: string;
    short_name: string | null;
    type: string;
    is_syndicate: boolean;
    description: string | null;
    logo_path: string | null;
    color_slug: string | null;
}

defineProps<{
    allegiance: Allegiance;
}>();
</script>

<template>
    <Head :title="allegiance.name" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner :title="allegiance.name" class="mb-2">
            <template #subtitle>
                <div class="my-auto flex items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    <span class="capitalize">{{ allegiance.type }}</span>
                    <Badge v-if="allegiance.is_syndicate" variant="outline" class="text-[10px]">Syndicate</Badge>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto space-y-4 sm:px-4">
            <Link
                :href="route('tos.allegiances.index')"
                class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-3" /> All allegiances
            </Link>

            <Card class="overflow-hidden">
                <div :class="['h-1.5 w-full', allegiance.color_slug ? `bg-${allegiance.color_slug}` : 'bg-primary/40']" />
                <CardContent class="space-y-3 p-5">
                    <div class="flex items-center gap-3">
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <AllegianceLogo :allegiance="allegiance.slug" class-name="size-6" />
                        </div>
                        <div>
                            <h2 class="font-semibold">{{ allegiance.name }}</h2>
                            <p class="text-xs capitalize text-muted-foreground">
                                {{ allegiance.type }}{{ allegiance.is_syndicate ? ' syndicate' : '' }}
                            </p>
                        </div>
                    </div>
                    <p v-if="allegiance.description" class="text-sm text-muted-foreground"><TosText :text="allegiance.description" /></p>
                    <p v-else class="text-xs italic text-muted-foreground">No description set yet.</p>
                </CardContent>
            </Card>

            <div class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                Allegiance Cards, Commanders, and Units for this allegiance land in upcoming releases.
            </div>
        </div>
    </div>
</template>
